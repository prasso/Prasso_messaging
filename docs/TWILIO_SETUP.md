# Twilio A2P 10DLC Setup Guide

This guide will help you set up and configure the Twilio A2P 10DLC messaging system for your application.

## Prerequisites

1. Twilio Account with A2P 10DLC enabled
2. A Twilio phone number with SMS capabilities
3. Your Twilio Account SID and Auth Token

## Installation

1. Publish the configuration files:

```bash
php artisan vendor:publish --tag=config --provider="Prasso\Messaging\MessagingServiceProvider"
```

2. Add your Twilio credentials to your `.env` file:

```env
TWILIO_ACCOUNT_SID=your_account_sid_here
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_PHONE_NUMBER=+1234567890  # Your Twilio phone number
```

3. Run the database migrations:

```bash
php artisan migrate
```

## Twilio Webhook Setup

1. Log in to your Twilio Console
2. Go to Phone Numbers > Manage > Active numbers
3. Select your Twilio phone number
4. Under "Messaging", set the following:
   - Configure With: Webhook
   - A MESSAGE COMES IN: `POST` to `https://your-domain.com/api/webhooks/twilio`
   - STATUS CALLBACK URL: (Optional) `https://your-domain.com/api/webhooks/twilio/status`

## Usage

### Sending Messages

```php
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Services\MessageService;

// Get a guest
$guest = MsgGuest::first();

// Send a message
app('messaging.message')->sendSms($guest, 'Hello from our church!');
```

### Checking Subscription Status

```php
// Check if a guest is subscribed
if ($guest->is_subscribed) {
    // Send message
}

// Get all subscribed guests
$subscribedGuests = MsgGuest::where('is_subscribed', true)->get();
```

### Handling Incoming Messages

Incoming messages are automatically handled by the `TwilioWebhookController`. It supports the following commands:

- `STOP`, `STOPALL`, `UNSUBSCRIBE`, `CANCEL`, `END`, `QUIT` - Unsubscribes the user
- `START`, `YES`, `UNSTOP` - Subscribes the user
- `HELP` - Shows help information

## Compliance Notes

1. All messages will automatically include an opt-out message
2. Unsubscribed users will not receive messages
3. Message delivery is logged for compliance

## Testing

You can test the webhook locally using ngrok:

```bash
ngrok http 8000
```

Then update your Twilio webhook URL to point to your ngrok URL (e.g., `https://your-ngrok-url.ngrok.io/api/webhooks/twilio`).
