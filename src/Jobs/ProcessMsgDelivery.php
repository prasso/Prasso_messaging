<?php

namespace Prasso\Messaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Prasso\Messaging\Models\MsgDelivery;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgTeamSetting;
use Twilio\Exceptions\RestException as TwilioRestException;
use Twilio\Rest\Client;

class ProcessMsgDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum attempts before the job is marked as failed.
     */
    public int $tries = 5;

    /**
     * Backoff schedule in seconds for retries (exponential-ish).
     */
    public function backoff(): array
    {
        return [60, 120, 300, 600];
    }

    public int $deliveryId;

    public function __construct(int $deliveryId)
    {
        $this->deliveryId = $deliveryId;
    }

    public function handle(): void
    {
        info('ProcessMsgDelivery: Starting job for delivery ID: ' . $this->deliveryId);
        $delivery = MsgDelivery::query()->find($this->deliveryId);
        if (! $delivery) {
            info('ProcessMsgDelivery: Delivery not found for ID: ' . $this->deliveryId);
            return;
        }

        // Only process queued deliveries
        if ($delivery->status !== 'queued') {
            info('ProcessMsgDelivery: Delivery already processed, status: ' . $delivery->status . ' for ID: ' . $this->deliveryId);
            return;
        }

        // Respect scheduling: if send_at is in the future, release the job until due
        if ($delivery->send_at && now()->lt($delivery->send_at)) {
            $this->release(now()->diffInSeconds($delivery->send_at));
            info('ProcessMsgDelivery: Delivery scheduled for future, releasing job for ID: ' . $this->deliveryId);
            return;
        }

        info('ProcessMsgDelivery: Determining channel for delivery ID: ' . $this->deliveryId);
        switch ($delivery->channel) {
            case 'email':
                info('ProcessMsgDelivery: Sending email for delivery ID: ' . $this->deliveryId);
                $this->sendEmail($delivery);
                break;
            case 'sms':
                info('ProcessMsgDelivery: Sending SMS for delivery ID: ' . $this->deliveryId);
                $this->sendSms($delivery);
                break;
            default:
                info('ProcessMsgDelivery: Skipping unsupported message type: ' . $delivery->channel . ' for delivery ID: ' . $this->deliveryId);
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'Unsupported message type: ' . $delivery->channel,
                ]);
        }
    }

    protected function sendEmail(MsgDelivery $delivery): void
    {
        info('ProcessMsgDelivery: Starting email send process for delivery ID: ' . $delivery->id);
        $message = $delivery->message; // relation

        // Resolve recipient email
        $email = null;
        $recipientName = null;
        if ($delivery->recipient_type === 'user') {
            $userModel = config('messaging.user_model');
            if (class_exists($userModel)) {
                $user = $userModel::query()->find($delivery->recipient_id);
                $email = $user?->email;
                $recipientName = $user?->name ?? null;
            }
        } elseif ($delivery->recipient_type === 'guest') {
            $guest = MsgGuest::query()->find($delivery->recipient_id);
            $email = $guest?->email;
            $recipientName = $guest?->name ?? null;
        }

        if (empty($email)) {
            info('ProcessMsgDelivery: No email address found for recipient, delivery ID: ' . $delivery->id);
            $delivery->update([
                'status' => 'skipped',
                'error' => 'No email address found for recipient',
            ]);
            return;
        }

        // Replace tokens
        if ($recipientName) {
            info('ProcessMsgDelivery: Replacing tokens with recipient name');
        }
        $subject = $this->replaceTokens($message->subject ?? '', $recipientName);
        $body = $this->replaceTokens($message->body ?? '', $recipientName);

        try {
            // Send raw email for now; can be swapped for a Mailable later.
            Mail::raw($body, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });

            info('ProcessMsgDelivery: Email sent successfully to: ' . $email . ' for delivery ID: ' . $delivery->id);
            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            info('ProcessMsgDelivery: Email send error for delivery ID: ' . $delivery->id . ' - ' . $e->getMessage());
            info($e);
            $delivery->update([
                'status' => 'failed',
                'error' => 'Email send error: ' . $e->getMessage(),
                'failed_at' => now(),
            ]);
        }
    }

    protected function sendSms(MsgDelivery $delivery): void
    {
        info('ProcessMsgDelivery: Starting SMS send process for delivery ID: ' . $delivery->id);
        $message = $delivery->message;

        // Resolve recipient phone
        $phone = null;
        $isSubscribed = true;
        $recipientName = null;
        if ($delivery->recipient_type === 'user') {
            $userModel = config('messaging.user_model');
            if (class_exists($userModel)) {
                $user = $userModel::query()->find($delivery->recipient_id);
                $phone = $user?->getAttribute('phone');
                $recipientName = $user?->name ?? null;
            }
        } elseif ($delivery->recipient_type === 'guest') {
            $guest = MsgGuest::query()->find($delivery->recipient_id);
            // Use raw original to avoid decrypting plaintext when custom mutator bypasses encrypted cast.
            $phone = $guest?->getRawOriginal('phone') ?: ($guest?->phone);
            $recipientName = $guest?->name ?? null;
            // Enforce consent for guests: pending/unsubscribed guests must be skipped
            if ($guest && ($guest->is_subscribed ?? false) !== true) {
                $isSubscribed = false;
            }
            // Respect privacy flags: do-not-contact and anonymized
            if ($guest && (bool) ($guest->do_not_contact ?? false)) {
                info('ProcessMsgDelivery: Recipient has do-not-contact flag enabled, delivery ID: ' . $delivery->id);
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'Recipient has do-not-contact flag enabled',
                ]);
                return;
            }
            if ($guest && !is_null($guest->anonymized_at ?? null)) {
                info('ProcessMsgDelivery: Recipient is anonymized, delivery ID: ' . $delivery->id);
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'Recipient is anonymized',
                ]);
                return;
            }
        }

        if (empty($phone)) {
            info('ProcessMsgDelivery: Invalid phone number format, delivery ID: ' . $delivery->id);
            $delivery->update([
                'status' => 'skipped',
                'error' => 'Invalid phone number format',
            ]);
            return;
        }

        if (! $isSubscribed) {
            info('ProcessMsgDelivery: Recipient is not subscribed, delivery ID: ' . $delivery->id);
            $delivery->update([
                'status' => 'skipped',
                'error' => 'Recipient is not subscribed',
            ]);
            return;
        }

        // Team verification enforcement: require verified status before sending for a team
        if (!empty($delivery->team_id)) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $delivery->team_id)->first();
            $status = $teamCfg?->verification_status;
            if (!$teamCfg || strtolower((string)$status) !== 'verified') {
                info('ProcessMsgDelivery: Team not verified, delivery ID: ' . $delivery->id);
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'Team not verified',
                ]);
                return;
            }
        }

        // Per-guest frequency governance (cap messages in a rolling window)
        $rateCfg = (array) config('messaging.rate_limit', []);
        $monthlyCap = config('messaging.rate_limit.per_guest_monthly_cap', 30);
        info('ProcessMsgDelivery: Rate limit monthly cap: ' . $monthlyCap . ' for delivery ID: ' . $delivery->id);
        $windowDays = config('messaging.rate_limit.per_guest_window_days', 30);
        info('ProcessMsgDelivery: Rate limit window days: ' . $windowDays . ' for delivery ID: ' . $delivery->id);
        $allowBypass = (bool) ($rateCfg['allow_transactional_bypass'] ?? true);
        $isTransactional = strtolower((string) ($delivery->metadata['type'] ?? '')) === 'transactional';
        $overrideAlways = (bool) ($delivery->metadata['override_frequency'] ?? false);
        $overrideUntil = $delivery->metadata['override_until'] ?? null; // ISO string or timestamp
        $overrideActive = false;
        if (!empty($overrideUntil)) {
            try {
                $overrideActive = now()->lt(\Carbon\Carbon::parse($overrideUntil));
            } catch (\Throwable $e) {
                $overrideActive = false;
            }
        }
        if ($monthlyCap > 0 && $windowDays > 0 && !($allowBypass && $isTransactional) && !($overrideAlways || $overrideActive)) {
            $windowStart = now()->subDays($windowDays);
            $recentCount = MsgDelivery::query()
                ->where('channel', 'sms')
                ->where('status', 'sent')
                ->where('team_id', $delivery->team_id)
                ->where('recipient_type', $delivery->recipient_type)
                ->where('recipient_id', $delivery->recipient_id)
                ->whereNot('id', $delivery->id)
                ->where('sent_at', '>=', $windowStart)
                ->count();

            info('ProcessMsgDelivery: Recent message count for recipient: ' . $recentCount . ' of ' . $monthlyCap . ' for delivery ID: ' . $delivery->id);

            if ($recentCount >= $monthlyCap) {
                info('ProcessMsgDelivery: Per-guest frequency cap reached, delivery ID: ' . $delivery->id);
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'Per-guest frequency cap reached',
                ]);
                return;
            }
        }

        // Determine from number with precedence: delivery metadata -> team settings -> app config
        $from = $delivery->metadata['from'] ?? null;
        if (empty($from) && !empty($delivery->team_id)) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $delivery->team_id)->first();
            $from = $teamCfg?->sms_from ?: null;
        }
        if (empty($from)) {
            $from = config('messaging.sms_from') ?: config('twilio.phone_number');
        }
        if (empty($from)) {
            info('ProcessMsgDelivery: Missing from number, delivery ID: ' . $delivery->id);
            $delivery->update([
                'status' => 'failed',
                'error' => 'Missing from number',
            ]);
            return;
        }

        // Normalize phone numbers to E.164 if possible (prepend '+' when missing)
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        info('ProcessMsgDelivery: Cleaned phone number: ' . $phone);

        // Standardize Twilio config usage
        $sid = config('twilio.sid') ?: env('TWILIO_ACCOUNT_SID');
        $token = config('twilio.auth_token') ?: env('TWILIO_AUTH_TOKEN');
        
        // Log Twilio configuration
        info('ProcessMsgDelivery: Twilio config check', [
            'sid_exists' => !empty($sid),
            'token_exists' => !empty($token),
            'config_sid' => config('twilio.sid'),
            'env_sid' => env('TWILIO_ACCOUNT_SID') ? 'set' : 'not set'
        ]);
        
        if (empty($sid) || empty($token)) {
            info('ProcessMsgDelivery: Twilio credentials missing, delivery ID: ' . $delivery->id);
            $delivery->update([
                'status' => 'failed',
                'error' => 'Twilio credentials missing',
            ]);
            return;
        }

        try {
            // Create Twilio client directly with credentials
            $client = new Client($sid, $token);
            $baseBody = $this->replaceTokens($message->body ?? '', $recipientName);
            $footer = $this->buildSmsFooter($delivery->team_id);
            
            // Create log context array directly to avoid method call issues
            $logContext = [
                'delivery_id' => $delivery->id,
                'message_id' => $delivery->message_id,
                'team_id' => $delivery->team_id,
                'channel' => 'sms',
                'recipient_type' => $delivery->recipient_type,
                'recipient_id' => $delivery->recipient_id,
            ];
            
            $body = $this->applySmsFooterAndLimit($baseBody, $footer, $logContext);
            
            // Debug logging for Twilio client
            info('ProcessMsgDelivery: Twilio client initialized', [
                'client_type' => is_object($client) ? get_class($client) : gettype($client),
                'sid_length' => $sid ? strlen($sid) : 0,
                'token_length' => $token ? strlen($token) : 0
            ]);
            
            // Direct access to messages API
            info('ProcessMsgDelivery: Sending SMS to ' . $phone);
            $twilioResponse = $client->messages->create($phone, [
                'from' => $from,
                'body' => $body,
            ]);

            info('ProcessMsgDelivery: SMS sent successfully to: ' . $phone . ' for delivery ID: ' . $delivery->id . ', Twilio SID: ' . $twilioResponse->sid);
            $delivery->update([
                'status' => 'sent',
                'provider_message_id' => $twilioResponse->sid,
                'sent_at' => now(),
            ]);
        } catch (TwilioRestException $e) {
            info('ProcessMsgDelivery: Twilio REST error for delivery ID: ' . $delivery->id . ' - ' . $e->getMessage());
            
            // Create log context array directly to avoid method call issues
            $ctx = [
                'delivery_id' => $delivery->id,
                'message_id' => $delivery->message_id,
                'team_id' => $delivery->team_id,
                'channel' => 'sms',
                'recipient_type' => $delivery->recipient_type,
                'recipient_id' => $delivery->recipient_id,
            ];
            
            $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null;
            Log::warning('Twilio REST error', $ctx + ['error' => $e->getMessage(), 'status_code' => $code]);

            if ($this->isTransientTwilioStatus($code)) {
                $this->release($this->nextBackoffSeconds());
                return;
            }

            // Permanent error from Twilio: mark failed, include code
            $delivery->update([
                'status' => 'failed',
                'error' => trim(($code ? "$code: " : '') . $e->getMessage()),
            ]);
        } catch (\Throwable $e) {
            info('ProcessMsgDelivery: SMS send unexpected error for delivery ID: ' . $delivery->id . ' - ' . $e->getMessage());
            info($e);
            $delivery->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Perform simple token replacement on message text.
     * Supports {{FirstName}} and {{Name}} using recipient's name when available.
     */
    protected function replaceTokens(string $text, ?string $recipientName): string
    {
        info('ProcessMsgDelivery: Preparing message body with recipient name: ' . ($recipientName ?? 'null'));
        if ($text === '') {
            return $text;
        }
        $name = trim((string) ($recipientName ?? ''));
        $firstName = $name !== '' ? preg_split('/\s+/', $name)[0] : '';
        $replacements = [
            '{{FirstName}}' => $firstName,
            '{{First Name}}' => $firstName,
            '{{Name}}' => $name,
        ];
        return strtr($text, $replacements);
    }

    /**
     * Build a short compliance footer for SMS messages including business identification,
     * STOP instructions, and disclaimer. Pull values from per-team settings when present,
     * otherwise fall back to messaging config.
     */
    protected function buildSmsFooter(?int $teamId): string
    {
        info('ProcessMsgDelivery: Building SMS footer for delivery ID: ' . $this->deliveryId);
        $business = config('messaging.help.business_name', config('app.name', 'Your Organization'));
        $disclaimer = config('messaging.help.disclaimer', 'Msg & data rates may apply.');
        $contact = '';

        if (!empty($teamId)) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $teamId)->first();
            if ($teamCfg) {
                if (!empty($teamCfg->help_business_name)) {
                    $business = $teamCfg->help_business_name;
                }
                if (!empty($teamCfg->help_disclaimer)) {
                    $disclaimer = $teamCfg->help_disclaimer;
                }
                // Prefer phone, then website, then email for a compact contact reference
                $contact = $teamCfg->help_contact_phone
                    ?: ($teamCfg->help_contact_website ?: ($teamCfg->help_contact_email ?: ''));
            }
        }

        $parts = [];
        // Business identification
        $parts[] = $business;
        // Mandatory STOP instruction
        $parts[] = 'Reply STOP to unsubscribe';
        // Disclaimer
        if (!empty($disclaimer)) {
            $parts[] = $disclaimer;
        }
        // Optional concise contact
        if (!empty($contact)) {
            $parts[] = $contact;
        }

        // Join with separators to keep concise; single line footer
        return implode(' · ', $parts);
    }

    /**
     * Append footer and ensure message stays within Twilio's 1600-char hard limit.
     * Log estimated segments for observability.
     */
    protected function applySmsFooterAndLimit(string $base, string $footer, array $logCtx): string
    {
        $joined = trim($base) !== '' ? (rtrim($base) . "\n" . $footer) : $footer;

        // Twilio hard limit is ~1600 chars; trim if necessary
        $max = 1600;
        $hasMb = function_exists('mb_strlen') && function_exists('mb_substr');
        $length = $hasMb ? mb_strlen($joined, 'UTF-8') : strlen($joined);
        if ($length > $max) {
            if ($hasMb) {
                $joined = mb_substr($joined, 0, $max - 1, 'UTF-8') . '…';
            } else {
                $joined = substr($joined, 0, $max - 3) . '...';
            }
            $length = $hasMb ? mb_strlen($joined, 'UTF-8') : strlen($joined);
        }

        // Rough segment estimation: GSM-7 uses 160 for 1 segment then 153, UCS-2 uses 70 then 67
        $isUcs2 = (bool) preg_match('/[^\x00-\x7F]/u', $joined);
        $len = $length;
        if ($isUcs2) {
            $segments = $len <= 70 ? 1 : (int) ceil($len / 67);
        } else {
            $segments = $len <= 160 ? 1 : (int) ceil($len / 153);
        }
        Log::info('SMS length/segments', $logCtx + ['chars' => $len, 'ucs2' => $isUcs2, 'segments' => $segments]);

        return $joined;
    }

    /**
     * Determine if a Twilio status code is transient and should be retried.
     */
    protected function isTransientTwilioStatus(?int $code): bool
    {
        // Twilio status codes that indicate temporary issues
        $transientCodes = [
            429, // Too Many Requests
            500, // Server Error
            503, // Service Unavailable
        ];
        return $code !== null && in_array($code, $transientCodes, true);
    }

    /**
     * Get the next backoff time in seconds based on the current attempt.
     */
    protected function nextBackoffSeconds(): int
    {
        $backoffs = $this->backoff();
        $attempt = $this->attempts();
        return $backoffs[min($attempt - 1, count($backoffs) - 1)] ?? 60;
    }

    /**
     * Create a context array for logging.
     */
    protected function logCtx(MsgDelivery $delivery, string $channel): array
    {
        return [
            'delivery_id' => $delivery->id,
            'message_id' => $delivery->message_id,
            'team_id' => $delivery->team_id,
            'channel' => $channel,
            'recipient_type' => $delivery->recipient_type,
            'recipient_id' => $delivery->recipient_id,
        ];
    }

    /**
     * Called when the job has exhausted all retries.
     */
    public function failed(\Throwable $e): void
    {
        info('ProcessMsgDelivery: Job failed with exception for delivery ID: ' . $this->deliveryId);
        info('ProcessMsgDelivery error details: ' . $e->getMessage());
        info($e);

        $delivery = MsgDelivery::query()->find($this->deliveryId);
        if (! $delivery) {
            info('ProcessMsgDelivery: Delivery not found for ID: ' . $this->deliveryId);
            return;
        }

        // Only set failed if not already marked sent/skipped/failed
        if ($delivery->status === 'queued' || $delivery->status === 'sending') {
            $delivery->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now(),
            ]);
        }
        
        // Create log context array directly to avoid method call issues
        $logContext = [
            'delivery_id' => $delivery->id,
            'message_id' => $delivery->message_id,
            'team_id' => $delivery->team_id,
            'channel' => (string) $delivery->channel,
            'recipient_type' => $delivery->recipient_type,
            'recipient_id' => $delivery->recipient_id,
        ];

        Log::error('ProcessMsgDelivery exhausted retries', $logContext + ['error' => $e->getMessage()]);
    }
}
