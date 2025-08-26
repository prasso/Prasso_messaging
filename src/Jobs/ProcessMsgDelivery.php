<?php

namespace Prasso\Messaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Prasso\Messaging\Models\MsgDelivery;
use Prasso\Messaging\Models\MsgGuest;
use Twilio\Rest\Client;

class ProcessMsgDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        try {
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
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
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

        // Send raw email for now; can be swapped for a Mailable later.
        Mail::raw($body, function ($mail) use ($email, $subject) {
            $mail->to($email)->subject($subject);
        });

        $delivery->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
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
            $phone = $guest?->phone;
            $recipientName = $guest?->name ?? null;
            // Enforce consent for guests
            if ($guest && property_exists($guest, 'is_subscribed') && $guest->is_subscribed === false) {
                $isSubscribed = false;
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
                'error' => 'unsubscribed recipient',
                'failed_at' => now(),
            ]);
            return;
        }

        $from = $delivery->metadata['from'] ?? config('messaging.sms_from');
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

        $client = new Client($sid, $token);
        $body = $this->replaceTokens($message->body ?? '', $recipientName);
        $twilioMessage = $client->messages->create($to, [
            'from' => $from,
            'body' => $body,
        ]);

        $delivery->update([
            'status' => 'sent',
            'provider_message_id' => $twilioMessage->sid ?? null,
            'sent_at' => now(),
        ]);
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
}
