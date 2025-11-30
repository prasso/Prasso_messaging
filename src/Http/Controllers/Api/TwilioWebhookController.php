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

        $response = $this->newTwiml();
        
        // Persist inbound message for inbox/history before keyword branching
        $guestId = null;
        $teamId = null;
        $normalized = $this->normalizePhoneNumber($from);
        Log::info('Phone normalization', [
            'from' => $from,
            'normalized' => $normalized,
        ]);
        
        if ($normalized) {
            $hash = hash('sha256', $normalized);
            $guest = MsgGuest::where('phone_hash', $hash)
                ->orWhere('phone', 'LIKE', "%$normalized") // fallback for legacy rows
                ->first();
            $guestId = $guest?->id;
            $teamId = $guest?->team_id;
            
            Log::info('Guest lookup', [
                'normalized' => $normalized,
                'hash' => $hash,
                'found_guest_id' => $guestId,
                'team_id' => $teamId,
            ]);
        }

        // Collect media URLs if present
        $media = [];
        $numMedia = (int) $request->input('NumMedia', 0);
        for ($i = 0; $i < $numMedia; $i++) {
            $url = $request->input("MediaUrl{$i}");
            if (!empty($url)) $media[] = $url;
        }

        // Find the most recent delivery to this guest to link as the reply target
        $msgDeliveryId = null;
        if ($guestId) {
            // Log all deliveries for this guest to debug
            $allDeliveries = \Prasso\Messaging\Models\MsgDelivery::query()
                ->where('recipient_type', 'guest')
                ->where('recipient_id', $guestId)
                ->orderBy('sent_at', 'desc')
                ->get(['id', 'status', 'sent_at', 'recipient_type', 'recipient_id']);
            
            Log::info('All deliveries for guest', [
                'guest_id' => $guestId,
                'delivery_count' => $allDeliveries->count(),
                'deliveries' => $allDeliveries->map(fn($d) => [
                    'id' => $d->id,
                    'status' => $d->status,
                    'sent_at' => $d->sent_at,
                ])->toArray(),
            ]);
            
            $recentDelivery = $allDeliveries
                ->where('status', 'sent')
                ->first();
            $msgDeliveryId = $recentDelivery?->id;
            
            Log::info('Delivery lookup for reply', [
                'guest_id' => $guestId,
                'found_delivery_id' => $msgDeliveryId,
                'delivery_status' => $recentDelivery?->status,
            ]);
        }

        MsgInboundMessage::create([
            'team_id' => $teamId,
            'msg_guest_id' => $guestId,
            'msg_delivery_id' => $msgDeliveryId,
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
            $this->handleOptOut($from, $request, $text);
            $business = $this->resolveBusinessName($teamId);
            $response->message("You've been unsubscribed from {$business} text alerts. No more messages will be sent. Reply START to resubscribe.");
        } elseif (in_array($text, $optInKeywords, true)) {
            $subscribed = $this->handleOptIn($from, $request, $text);
            if ($subscribed) {
                $response->message('You are now subscribed to receive messages. Reply STOP to unsubscribe.');
            } else {
                $response->message('We could not verify a recent subscription request. Please submit the web form to opt in, then reply YES within 24 hours to confirm.');
            }
        } elseif ($text === 'HELP' || $text === 'INFO' || $text === 'SUPPORT' || $text === '?') {
            $response->message($this->buildHelpMessage($teamId));
        } else {
            // Generic acknowledgement, maintain compliance hint
            $response->message('Thank you for your message. Reply HELP for information or STOP to unsubscribe.');
        }
        
        return response((string) $response)->header('Content-Type', 'text/xml; charset=UTF-8');
    }
    
    protected function handleOptOut($phoneNumber, Request $request = null, ?string $keyword = null)
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
                'meta' => ['keyword' => $keyword ?: 'STOP'],
            ]);
        }
            
        Log::info("User opted out: $normalizedNumber");
    }
    
    protected function handleOptIn($phoneNumber, Request $request = null, ?string $keyword = null): bool
    {
        $normalizedNumber = $this->normalizePhoneNumber($phoneNumber);
        
        // Update all guests with this number and log consent
        $hash = hash('sha256', $normalizedNumber);
        $guests = MsgGuest::where('phone_hash', $hash)
            ->orWhere('phone', 'LIKE', "%$normalizedNumber")
            ->get();
        
        // If no guests found with this phone number, create a new one with default team
        if ($guests->isEmpty()) {
            // Get the default team ID (using the first team in the system as fallback)
            $defaultTeam = MsgTeamSetting::first();
            $defaultTeamId = $defaultTeam ? $defaultTeam->team_id : 1; // Fallback to ID 1 if no teams exist
            
            // Create new guest with minimal required fields
            $guest = MsgGuest::create([
                'team_id' => $defaultTeamId,
                'user_id' => 0, // Required by DB schema but not logically connected to a user
                'name' => 'SMS Subscriber', // Placeholder name
                'email' => 'sms_' . substr(md5($phoneNumber), 0, 10) . '@placeholder.local', // Unique placeholder email
                'phone' => $phoneNumber, // This will automatically set phone_hash via mutator
                'is_subscribed' => true,
                'subscription_status_updated_at' => now(),
            ]);
            
            // Create opt-in consent record
            MsgConsentEvent::create([
                'team_id' => $defaultTeamId,
                'msg_guest_id' => $guest->id,
                'action' => 'opt_in',
                'method' => 'keyword',
                'source' => $phoneNumber,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'occurred_at' => now(),
                'meta' => ['keyword' => $keyword ?: 'START'],
            ]);
            
            Log::info("Created new guest and opted in: $normalizedNumber", [
                'guest_id' => $guest->id,
                'team_id' => $defaultTeamId
            ]);
            
            return true;
        }
        
        $subscribedAny = false;
        foreach ($guests as $guest) {
            // Enforce double opt-in window: must have opt_in_request within last 24 hours
            $recentRequest = MsgConsentEvent::query()
                ->where('msg_guest_id', $guest->id)
                ->where('action', 'opt_in_request')
                ->where('occurred_at', '>=', now()->subHours(24))
                ->exists();
            if (!$recentRequest) {
                // Do not subscribe this guest; continue to next
                continue;
            }
            $guest->update([
                'is_subscribed' => true,
                'subscription_status_updated_at' => now(),
            ]);
            $subscribedAny = true;

            MsgConsentEvent::create([
                'team_id' => $guest->team_id,
                'msg_guest_id' => $guest->id,
                'action' => 'opt_in',
                'method' => 'keyword',
                'source' => $phoneNumber,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'occurred_at' => now(),
                'meta' => ['keyword' => $keyword ?: 'START'],
            ]);
        }
            
        Log::info("User opted in: $normalizedNumber", ['subscribed' => $subscribedAny]);
        return $subscribedAny;
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

    protected function resolveBusinessName(?int $teamId = null): string
    {
        $business = config('messaging.help.business_name', config('app.name', 'Our Service'));
        if ($teamId) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $teamId)->first();
            if ($teamCfg && !empty($teamCfg->help_business_name)) {
                $business = $teamCfg->help_business_name;
            }
        }
        return $business;
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

    /**
     * Create a TwiML response object. Falls back to a lightweight stub when twilio/sdk isn't installed.
     */
    protected function newTwiml()
    {
        if (class_exists('\\Twilio\\TwiML\\MessagingResponse')) {
            $klass = '\\Twilio\\TwiML\\MessagingResponse';
            return new $klass();
        }
        // Minimal stub with message() and __toString() to build TwiML
        return new class {
            private array $messages = [];
            public function message(string $text): void
            {
                $this->messages[] = $text;
            }
            public function __toString(): string
            {
                $xml = '<?xml version="1.0" encoding="UTF-8"?>'.'<Response>';
                foreach ($this->messages as $m) {
                    $xml .= '<Message>' . htmlspecialchars($m, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Message>';
                }
                $xml .= '</Response>';
                return $xml;
            }
        };
    }
}
