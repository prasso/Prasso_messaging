<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgGuestMessage;
use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgConsentEvent;

class TwilioWebhookController
{
    public function handleIncomingMessage(Request $request)
    {
        $from = $request->input('From');
        $body = trim($request->input('Body', ''));
        
        Log::info('Twilio Webhook Received', [
            'from' => $from,
            'body' => $body,
            'all' => $request->all()
        ]);

        $response = new \Twilio\TwiML\MessagingResponse();
        
        $text = strtoupper($body);
        $optOutKeywords = array_map('strtoupper', (array) config('twilio.opt_out_keywords', []));
        $optInKeywords = array_map('strtoupper', (array) config('twilio.opt_in_keywords', []));

        if (in_array($text, $optOutKeywords, true)) {
            $this->handleOptOut($from, $request);
            $response->message('You have been unsubscribed and will no longer receive messages. Reply START to resubscribe.');
        } elseif (in_array($text, $optInKeywords, true)) {
            $this->handleOptIn($from, $request);
            $response->message('You are now subscribed to receive messages. Reply STOP to unsubscribe.');
        } elseif ($text === 'HELP' || $text === 'INFO' || $text === 'SUPPORT') {
            $response->message($this->buildHelpMessage());
        } else {
            // Generic acknowledgement, maintain compliance hint
            $response->message('Thank you for your message. Reply HELP for information or STOP to unsubscribe.');
        }
        
        return response($response)->header('Content-Type', 'text/xml');
    }
    
    protected function handleOptOut($phoneNumber, Request $request = null)
    {
        // Remove +1 if present and normalize
        $normalizedNumber = $this->normalizePhoneNumber($phoneNumber);
        
        // Update all guests with this number and log consent
        $guests = MsgGuest::where('phone', 'LIKE', "%$normalizedNumber")->get();
        foreach ($guests as $guest) {
            $guest->update([
                'is_subscribed' => false,
                'subscription_status_updated_at' => now(),
            ]);

            MsgConsentEvent::create([
                'msg_guest_id' => $guest->id,
                'action' => 'opt_out',
                'method' => 'keyword',
                'source' => $phoneNumber,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'occurred_at' => now(),
                'meta' => ['keyword' => 'STOP'],
            ]);
        }
            
        Log::info("User opted out: $normalizedNumber");
    }
    
    protected function handleOptIn($phoneNumber, Request $request = null)
    {
        $normalizedNumber = $this->normalizePhoneNumber($phoneNumber);
        
        // Update all guests with this number and log consent
        $guests = MsgGuest::where('phone', 'LIKE', "%$normalizedNumber")->get();
        foreach ($guests as $guest) {
            $guest->update([
                'is_subscribed' => true,
                'subscription_status_updated_at' => now(),
            ]);

            MsgConsentEvent::create([
                'msg_guest_id' => $guest->id,
                'action' => 'opt_in',
                'method' => 'keyword',
                'source' => $phoneNumber,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'occurred_at' => now(),
                'meta' => ['keyword' => 'START'],
            ]);
        }
            
        Log::info("User opted in: $normalizedNumber");
    }
    
    protected function normalizePhoneNumber($phoneNumber)
    {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If number starts with 1, remove it (US/CA country code)
        if (strlen($number) === 11 && strpos($number, '1') === 0) {
            $number = substr($number, 1);
        }
        
        return $number;
    }

    protected function buildHelpMessage(): string
    {
        $cfg = (array) config('messaging.help', []);
        $business = $cfg['business_name'] ?? config('app.name', 'Our Service');
        $purpose = $cfg['purpose'] ?? 'You receive messages you opted into.';
        $disclaimer = $cfg['disclaimer'] ?? 'Msg & data rates may apply.';

        $contacts = [];
        if (!empty($cfg['contact_phone'])) $contacts[] = $cfg['contact_phone'];
        if (!empty($cfg['contact_email'])) $contacts[] = $cfg['contact_email'];
        if (!empty($cfg['contact_website'])) $contacts[] = $cfg['contact_website'];
        $contact = implode(' | ', $contacts);

        $template = $cfg['template'] ?? '{{business}}: {{purpose}} Reply STOP to unsubscribe. {{disclaimer}} {{contact}}';
        $replacements = [
            '{{business}}' => $business,
            '{{purpose}}' => $purpose,
            '{{disclaimer}}' => $disclaimer,
            '{{contact}}' => $contact,
        ];

        return trim(strtr($template, $replacements));
    }
}
