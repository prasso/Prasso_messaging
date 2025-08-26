<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgGuestMessage;
use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgConsentEvent;
use Prasso\Messaging\Models\MsgInboundMessage;
use Prasso\Messaging\Models\MsgTeamSetting;

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
        
        // Persist inbound message for inbox/history before keyword branching
        $guestId = null;
        $teamId = null;
        $normalized = $this->normalizePhoneNumber($from);
        if ($normalized) {
            $hash = hash('sha256', $normalized);
            $guest = MsgGuest::where('phone_hash', $hash)
                ->orWhere('phone', 'LIKE', "%$normalized") // fallback for legacy rows
                ->first();
            $guestId = $guest?->id;
            $teamId = $guest?->team_id;
        }

        // Collect media URLs if present
        $media = [];
        $numMedia = (int) $request->input('NumMedia', 0);
        for ($i = 0; $i < $numMedia; $i++) {
            $url = $request->input("MediaUrl{$i}");
            if (!empty($url)) $media[] = $url;
        }

        MsgInboundMessage::create([
            'team_id' => $teamId,
            'msg_guest_id' => $guestId,
            'from' => $from,
            'to' => $request->input('To'),
            'body' => $body,
            'media' => $media,
            'provider_message_id' => $request->input('SmsMessageSid') ?: $request->input('MessageSid'),
            'received_at' => now(),
            'raw' => $request->all(),
        ]);

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
            $response->message($this->buildHelpMessage($teamId));
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
        $hash = hash('sha256', $normalizedNumber);
        $guests = MsgGuest::where('phone_hash', $hash)
            ->orWhere('phone', 'LIKE', "%$normalizedNumber")
            ->get();
        foreach ($guests as $guest) {
            $guest->update([
                'is_subscribed' => false,
                'subscription_status_updated_at' => now(),
            ]);

            MsgConsentEvent::create([
                'team_id' => $guest->team_id,
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
        $hash = hash('sha256', $normalizedNumber);
        $guests = MsgGuest::where('phone_hash', $hash)
            ->orWhere('phone', 'LIKE', "%$normalizedNumber")
            ->get();
        foreach ($guests as $guest) {
            $guest->update([
                'is_subscribed' => true,
                'subscription_status_updated_at' => now(),
            ]);

            MsgConsentEvent::create([
                'team_id' => $guest->team_id,
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

    protected function buildHelpMessage(?int $teamId = null): string
    {
        // Start with global config
        $cfg = (array) config('messaging.help', []);
        $business = $cfg['business_name'] ?? config('app.name', 'Our Service');
        $purpose = $cfg['purpose'] ?? 'You receive messages you opted into.';
        $disclaimer = $cfg['disclaimer'] ?? 'Msg & data rates may apply.';
        $contact_phone = $cfg['contact_phone'] ?? '';
        $contact_email = $cfg['contact_email'] ?? '';
        $contact_website = $cfg['contact_website'] ?? '';

        // Override with team settings if available
        if ($teamId) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $teamId)->first();
            if ($teamCfg) {
                $business = $teamCfg->help_business_name ?: $business;
                $purpose = $teamCfg->help_purpose ?: $purpose;
                $disclaimer = $teamCfg->help_disclaimer ?: $disclaimer;
                $contact_phone = $teamCfg->help_contact_phone ?: $contact_phone;
                $contact_email = $teamCfg->help_contact_email ?: $contact_email;
                $contact_website = $teamCfg->help_contact_website ?: $contact_website;
            }
        }

        $contacts = [];
        if (!empty($contact_phone)) $contacts[] = $contact_phone;
        if (!empty($contact_email)) $contacts[] = $contact_email;
        if (!empty($contact_website)) $contacts[] = $contact_website;
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
