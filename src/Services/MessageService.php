<?php

namespace Prasso\Messaging\Services;

use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgMessage;
use Prasso\Messaging\Models\MsgDelivery;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

class MessageService
{
    protected $twilio;
    protected $twilioNumber;

    public function __construct()
    {
        $this->twilioNumber = config('services.twilio.phone_number');
        $this->twilio = new TwilioClient(
            config('services.twilio.sid'),
            config('services.twilio.auth_token')
        );
    }

    public function sendSms(MsgGuest $recipient, string $message, array $options = [])
    {
        // Check if recipient is subscribed
        if (!$recipient->is_subscribed) {
            Log::info("Skipping message to unsubscribed recipient: {$recipient->phone}");
            return false;
        }

        try {
            // Add opt-out message if not disabled
            if (!($options['disable_opt_out_message'] ?? false)) {
                $message = $this->appendOptOutMessage($message);
            }

            // Send the message via Twilio
            $result = $this->twilio->messages->create(
                $this->formatPhoneNumber($recipient->phone),
                [
                    'from' => $this->twilioNumber,
                    'body' => $message
                ]
            );

            // Update the recipient's last message timestamp
            $recipient->update(['last_message_at' => now()]);

            // Log the delivery
            $this->logDelivery($recipient, $message, $result->sid);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send SMS to {$recipient->phone}: " . $e->getMessage());
            return false;
        }
    }

    protected function appendOptOutMessage(string $message): string
    {
        $optOutMessage = " Reply STOP to unsubscribe.";
        
        // Ensure the message + opt-out fits in one message (160 chars)
        $maxLength = 160 - strlen($optOutMessage) - 1; // -1 for space
        
        if (strlen($message) > $maxLength) {
            $message = substr($message, 0, $maxLength - 3) . '...';
        }
        
        return $message . $optOutMessage;
    }

    protected function logDelivery(MsgGuest $recipient, string $message, string $messageSid)
    {
        // Create a message record
        $msg = MsgMessage::create([
            'subject' => 'SMS Message',
            'body' => $message,
            'type' => 'sms'
        ]);

        // Create delivery record
        MsgDelivery::create([
            'msg_message_id' => $msg->id,
            'recipient_type' => MsgGuest::class,
            'recipient_id' => $recipient->id,
            'status' => 'sent',
            'provider_message_id' => $messageSid,
            'sent_at' => now(),
            'delivered_at' => null
        ]);
    }

    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $phone);
        
        // If number starts with 1, remove it (US/CA country code)
        if (strlen($number) === 11 && strpos($number, '1') === 0) {
            $number = substr($number, 1);
        }
        
        // Add +1 for Twilio
        return '+1' . $number;
    }
}
