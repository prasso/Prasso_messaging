<?php


return [
    'providers' => [
      
    ],
    'user_model' => App\Models\User::class,
    'date_format' => env('MESSAGING_DATE_FORMAT', config('app.date_format', 'd/m/Y')),
    // Default SMS from number, typically a Twilio number in E.164 format
    'sms_from' => env('TWILIO_NUMBER'),

    // Rate limiting settings (basic caps; can be overridden per-tenant later)
    'rate_limit' => [
        // Max items per batch when dispatching queued deliveries
        'batch_size' => (int) env('MESSAGING_BATCH_SIZE', 50),
        // Seconds to wait between batches
        'batch_interval_seconds' => (int) env('MESSAGING_BATCH_INTERVAL', 1),
    ],

    // Compliance/HELP defaults (can be overridden per tenant later)
    'help' => [
        'business_name' => env('MESSAGING_HELP_BUSINESS', config('app.name', 'Your Organization')),
        'purpose' => env('MESSAGING_HELP_PURPOSE', 'You receive messages related to services and events you opted into.'),
        'contact_phone' => env('MESSAGING_HELP_PHONE', ''),
        'contact_email' => env('MESSAGING_HELP_EMAIL', ''),
        'contact_website' => env('MESSAGING_HELP_WEBSITE', ''),
        'disclaimer' => env('MESSAGING_HELP_DISCLAIMER', 'Msg & data rates may apply.'),
        // Template used for HELP auto-reply
        'template' => "{{business}}: {{purpose}} Reply STOP to unsubscribe. {{disclaimer}} {{contact}}",
    ],

];
