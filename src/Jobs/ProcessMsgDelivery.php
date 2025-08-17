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

        try {
            switch ($delivery->channel) {
                case 'email':
                    $this->sendEmail($delivery);
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
        if ($delivery->recipient_type === 'user') {
            $userModel = config('messaging.user_model');
            if (class_exists($userModel)) {
                $user = $userModel::query()->find($delivery->recipient_id);
                $email = $user?->email;
            }
        } elseif ($delivery->recipient_type === 'guest') {
            $guest = MsgGuest::query()->find($delivery->recipient_id);
            $email = $guest?->email;
        }

        if (empty($email)) {
            $delivery->update([
                'status' => 'failed',
                'error' => 'missing email',
                'failed_at' => now(),
            ]);
            return;
        }

        // Send raw email for now; can be swapped for a Mailable later.
        Mail::raw($message->body, function ($mail) use ($email, $message) {
            $mail->to($email)->subject($message->subject ?? '');
        });

        $delivery->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
