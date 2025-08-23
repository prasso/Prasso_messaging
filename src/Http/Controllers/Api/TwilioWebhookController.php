<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgGuestMessage;
use Illuminate\Support\Facades\Log;

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
        
        switch (strtoupper($body)) {
            case 'STOP':
            case 'STOPALL':
            case 'UNSUBSCRIBE':
            case 'CANCEL':
            case 'END':
            case 'QUIT':
                $this->handleOptOut($from);
                $response->message('You have been unsubscribed from all messages. Reply START to resubscribe.');
                break;
                
            case 'START':
            case 'YES':
            case 'UNSTOP':
                $this->handleOptIn($from);
                $response->message('You are now subscribed to receive messages. Reply STOP to unsubscribe.');
                break;
                
            case 'HELP':
                $response->message("Reply STOP to unsubscribe, START to resubscribe, or HELP for this message.");
                break;
                
            default:
                // Handle other incoming messages if needed
                $response->message('Thank you for your message. Reply HELP for help, STOP to unsubscribe.');
        }
        
        return response($response)->header('Content-Type', 'text/xml');
    }
    
    protected function handleOptOut($phoneNumber)
    {
        // Remove +1 if present and normalize
        $normalizedNumber = $this->normalizePhoneNumber($phoneNumber);
        
        // Update all guests with this number
        MsgGuest::where('phone', 'LIKE', "%$normalizedNumber")
            ->update(['is_subscribed' => false]);
            
        Log::info("User opted out: $normalizedNumber");
    }
    
    protected function handleOptIn($phoneNumber)
    {
        $normalizedNumber = $this->normalizePhoneNumber($phoneNumber);
        
        // Update all guests with this number
        MsgGuest::where('phone', 'LIKE', "%$normalizedNumber")
            ->update(['is_subscribed' => true]);
            
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
}
