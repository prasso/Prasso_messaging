<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Twilio Account SID
    |--------------------------------------------------------------------------
    |
    | This is your Twilio Account SID. You can find it in your Twilio Console.
    |
    */
    'sid' => env('TWILIO_ACCOUNT_SID'),

    /*
    |--------------------------------------------------------------------------
    | Twilio Auth Token
    |--------------------------------------------------------------------------
    |
    | This is your Twilio Auth Token. You can find it in your Twilio Console.
    |
    */
    'auth_token' => env('TWILIO_AUTH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Twilio Phone Number
    |--------------------------------------------------------------------------
    |
    | This is the phone number you've purchased from Twilio.
    |
    */
    'phone_number' => env('TWILIO_PHONE_NUMBER'),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | This is the URL where Twilio will send incoming message webhooks.
    | You'll need to set this in your Twilio console.
    |
    */
    'webhook_url' => env('TWILIO_WEBHOOK_URL', '/api/webhooks/twilio'),

    /*
    |--------------------------------------------------------------------------
    | Opt-Out Keywords
    |--------------------------------------------------------------------------
    |
    | Keywords that will trigger an opt-out when received from a user.
    |
    */
    'opt_out_keywords' => ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT'],

    /*
    |--------------------------------------------------------------------------
    | Opt-In Keywords
    |--------------------------------------------------------------------------
    |
    | Keywords that will trigger an opt-in when received from a user.
    |
    */
    'opt_in_keywords' => ['START', 'YES', 'UNSTOP', 'SUBSCRIBE', 'JOIN','HELLO'],
];
