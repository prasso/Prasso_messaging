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
        $delivery = MsgDelivery::query()->find($this->deliveryId);
        if (! $delivery) {
            return;
        }

        // Only process queued deliveries
        if ($delivery->status !== 'queued') {
            return;
        }

        // Respect scheduling: if send_at is in the future, release the job until due
        if ($delivery->send_at && now()->lt($delivery->send_at)) {
            $this->release(now()->diffInSeconds($delivery->send_at));
            return;
        }

        switch ($delivery->channel) {
            case 'email':
                $this->sendEmail($delivery);
                break;
            case 'sms':
                $this->sendSms($delivery);
                break;
            default:
                $delivery->update([
                    'status' => 'failed',
                    'error' => 'channel not implemented',
                    'failed_at' => now(),
                ]);
        }
    }

    protected function sendEmail(MsgDelivery $delivery): void
    {
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
            $delivery->update([
                'status' => 'failed',
                'error' => 'missing email',
                'failed_at' => now(),
            ]);
            return;
        }

        // Simple templating
        $subject = $this->replaceTokens($message->subject ?? '', $recipientName);
        $body = $this->replaceTokens($message->body ?? '', $recipientName);

        try {
            // Send raw email for now; can be swapped for a Mailable later.
            Mail::raw($body, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $ctx = $this->logCtx($delivery, 'email');
            Log::warning('Email send error', $ctx + ['error' => $e->getMessage()]);

            if ($this->isTransientException($e)) {
                // Release for retry using backoff
                $this->release($this->nextBackoffSeconds());
                return;
            }

            // Permanent error: mark failed and stop retrying
            $delivery->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now(),
            ]);
        }
    }

    protected function sendSms(MsgDelivery $delivery): void
    {
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
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'do-not-contact',
                    'failed_at' => now(),
                ]);
                return;
            }
            if ($guest && !is_null($guest->anonymized_at ?? null)) {
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'anonymized recipient',
                    'failed_at' => now(),
                ]);
                return;
            }
        }

        if (empty($phone)) {
            $delivery->update([
                'status' => 'failed',
                'error' => 'missing phone',
                'failed_at' => now(),
            ]);
            return;
        }

        if (! $isSubscribed) {
            $delivery->update([
                'status' => 'skipped',
                'error' => 'pending or unsubscribed recipient',
                'failed_at' => now(),
            ]);
            return;
        }

        // Team verification enforcement: require verified status before sending for a team
        if (!empty($delivery->team_id)) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $delivery->team_id)->first();
            $status = $teamCfg?->verification_status;
            if (!$teamCfg || strtolower((string)$status) !== 'verified') {
                $ctx = $this->logCtx($delivery, 'sms');
                Log::info('Team not verified; skipping SMS', $ctx + [
                    'team_id' => $delivery->team_id,
                    'verification_status' => $status,
                ]);
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'team not verified',
                    'failed_at' => now(),
                ]);
                return;
            }
        }

        // Per-guest frequency governance (cap messages in a rolling window)
        $rateCfg = (array) config('messaging.rate_limit', []);
        $cap = (int) ($rateCfg['per_guest_monthly_cap'] ?? 0);
        $windowDays = (int) ($rateCfg['per_guest_window_days'] ?? 30);
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
        if ($cap > 0 && $windowDays > 0 && !($allowBypass && $isTransactional) && !($overrideAlways || $overrideActive)) {
            $windowStart = now()->subDays($windowDays);
            $priorCount = MsgDelivery::query()
                ->where('channel', 'sms')
                ->where('status', 'sent')
                ->where('team_id', $delivery->team_id)
                ->where('recipient_type', $delivery->recipient_type)
                ->where('recipient_id', $delivery->recipient_id)
                ->whereNot('id', $delivery->id)
                ->where('sent_at', '>=', $windowStart)
                ->count();

            if ($priorCount >= $cap) {
                $ctx = $this->logCtx($delivery, 'sms');
                Log::info('Per-guest frequency cap reached; skipping SMS', $ctx + [
                    'cap' => $cap,
                    'window_days' => $windowDays,
                    'prior_count' => $priorCount,
                ]);
                $delivery->update([
                    'status' => 'skipped',
                    'error' => 'per-guest frequency cap reached',
                    'failed_at' => now(),
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
            $delivery->update([
                'status' => 'failed',
                'error' => 'missing from number',
                'failed_at' => now(),
            ]);
            return;
        }

        // Normalize phone numbers to E.164 if possible (prepend '+' when missing)
        $to = str_starts_with($phone, '+') ? $phone : ('+' . preg_replace('/\D+/', '', $phone));

        // Standardize Twilio config usage
        $sid = config('twilio.sid') ?: env('TWILIO_ACCOUNT_SID');
        $token = config('twilio.auth_token') ?: env('TWILIO_AUTH_TOKEN');
        if (empty($sid) || empty($token)) {
            $delivery->update([
                'status' => 'failed',
                'error' => 'twilio credentials missing',
                'failed_at' => now(),
            ]);
            return;
        }

        try {
            $client = new Client($sid, $token);
            $baseBody = $this->replaceTokens($message->body ?? '', $recipientName);
            $footer = $this->buildSmsFooter($delivery->team_id);
            $body = $this->applySmsFooterAndLimit($baseBody, $footer, $this->logCtx($delivery, 'sms'));
            $twilioMessage = $client->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $delivery->update([
                'status' => 'sent',
                'provider_message_id' => $twilioMessage->sid ?? null,
                'sent_at' => now(),
            ]);
        } catch (TwilioRestException $e) {
            $ctx = $this->logCtx($delivery, 'sms');
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
                'failed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $ctx = $this->logCtx($delivery, 'sms');
            Log::error('SMS send unexpected error', $ctx + ['error' => $e->getMessage()]);

            if ($this->isTransientException($e)) {
                $this->release($this->nextBackoffSeconds());
                return;
            }

            $delivery->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now(),
            ]);
        }
    }

    /**
     * Perform simple token replacement on message text.
     * Supports {{FirstName}} and {{Name}} using recipient's name when available.
     */
    protected function replaceTokens(string $text, ?string $recipientName): string
    {
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
     * Build a standard logging context for this job/delivery.
     */
    protected function logCtx(MsgDelivery $delivery, string $channel): array
    {
        return [
            'delivery_id' => $delivery->id,
            'channel' => $channel,
            'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
            'team_id' => $delivery->team_id,
            'recipient_type' => $delivery->recipient_type,
            'recipient_id' => $delivery->recipient_id,
        ];
    }

    /**
     * Determine if an exception is likely transient (network/timeouts, 5xx, rate limits).
     */
    protected function isTransientException(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        if (str_contains($msg, 'timeout') || str_contains($msg, 'connection') || str_contains($msg, 'temporarily')) {
            return true;
        }
        return false;
    }

    /**
     * Decide Twilio HTTP status codes that should be retried.
     */
    protected function isTransientTwilioStatus(?int $status): bool
    {
        if ($status === null) return true; // be conservative and retry once if unknown
        if ($status === 429) return true; // rate limited
        if ($status >= 500) return true; // server errors
        return false; // 4xx are permanent (auth/validation)
    }

    /**
     * Compute the next backoff seconds based on current attempt.
     */
    protected function nextBackoffSeconds(): int
    {
        $schedule = $this->backoff();
        $attempt = max(1, (int) (method_exists($this, 'attempts') ? $this->attempts() : 1));
        // attempts() starts at 1; map to index (attempt-1), clamp to last value
        $idx = min($attempt - 1, count($schedule) - 1);
        return (int) $schedule[$idx];
    }

    /**
     * Build a short compliance footer for SMS messages including business identification,
     * STOP instructions, and disclaimer. Pull values from per-team settings when present,
     * otherwise fall back to messaging config.
     */
    protected function buildSmsFooter(?int $teamId): string
    {
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
        if (mb_strlen($joined, 'UTF-8') > $max) {
            $joined = mb_substr($joined, 0, $max - 1, 'UTF-8') . '…';
        }

        // Rough segment estimation: GSM-7 uses 160 for 1 segment then 153, UCS-2 uses 70 then 67
        $isUcs2 = (bool) preg_match('/[^\x00-\x7F]/u', $joined);
        $len = mb_strlen($joined, 'UTF-8');
        if ($isUcs2) {
            $segments = $len <= 70 ? 1 : (int) ceil($len / 67);
        } else {
            $segments = $len <= 160 ? 1 : (int) ceil($len / 153);
        }
        Log::info('SMS length/segments', $logCtx + ['chars' => $len, 'ucs2' => $isUcs2, 'segments' => $segments]);

        return $joined;
    }

    /**
     * Called when the job has exhausted all retries.
     */
    public function failed(\Throwable $e): void
    {
        $delivery = MsgDelivery::query()->find($this->deliveryId);
        if (! $delivery) return;

        // Only set failed if not already marked sent/skipped/failed
        if ($delivery->status === 'queued' || $delivery->status === 'sending') {
            $delivery->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now(),
            ]);
        }

        Log::error('ProcessMsgDelivery exhausted retries', $this->logCtx($delivery, (string) $delivery->channel) + ['error' => $e->getMessage()]);
    }
}
