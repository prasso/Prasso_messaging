# Milestone 1: A2P Compliance (Consent, HELP, Webhook Verification, Delivery Receipts)

This guide explains the changes delivered in Milestone 1 and how to configure and test them.

## What’s included
- Enforce consent checks on all SMS send paths (`MessageController`, `ProcessMsgDelivery`, `MessageService`).
- Log consent events (`msg_consent_events` table + `MsgConsentEvent` model).
- Compliant and configurable HELP auto-reply (business info, purpose, opt-out, contact, disclaimer).
- Twilio webhook signature verification middleware for inbound webhooks.
- Delivery Status Callback (DLR) processing to update `msg_deliveries`.

## Environment variables
Set these in your app environment (e.g., `.env`).

- TWILIO_ACCOUNT_SID
- TWILIO_AUTH_TOKEN
- TWILIO_PHONE_NUMBER
- TWILIO_VALIDATE_SIGNATURE=true   # Set false only for local testing

Optional HELP customization (defaults are provided):
- MESSAGING_HELP_BUSINESS="My Company"
- MESSAGING_HELP_PURPOSE="You receive messages related to services and events you opted into."
- MESSAGING_HELP_PHONE="+1XXXXXXXXXX"
- MESSAGING_HELP_EMAIL="support@example.com"
- MESSAGING_HELP_WEBSITE="https://example.com"
- MESSAGING_HELP_DISCLAIMER="Msg & data rates may apply."

## Migrations
Apply the new migration for consent events.

```bash
php artisan migrate
```

Tables affected:
- msg_consent_events (new)
- msg_deliveries (existing; used by DLR updates)
- msg_guests (existing; uses `is_subscribed` and `subscription_status_updated_at`)

## Webhook endpoints
All webhooks are protected with Twilio signature verification.

- Inbound SMS: POST `/webhooks/twilio`
- Delivery Status Callback (DLR): POST `/webhooks/twilio/status`

Notes:
- Ensure these exact URLs are configured in Twilio Console for your numbers/messaging services.
- Signature validation must see the exact URL Twilio posts (including scheme/host/port).

## HELP auto-reply (config)
`config/messaging.php` exposes a `help` section:

- business_name
- purpose
- contact_phone, contact_email, contact_website
- disclaimer
- template (default: "{{business}}: {{purpose}} Reply STOP to unsubscribe. {{disclaimer}} {{contact}}")

Keywords triggering HELP: HELP, INFO, SUPPORT.

## Consent keywords and logging
- Opt-out keywords read from `config/twilio.php` (e.g., STOP, STOPALL, CANCEL, END, QUIT, UNSUBSCRIBE).
- Opt-in keywords read from `config/twilio.php` (e.g., START, YES, UNSTOP).
- On STOP: set `is_subscribed=false`, update `subscription_status_updated_at`, and create a `MsgConsentEvent` with action `opt_out`.
- On START: set `is_subscribed=true`, update `subscription_status_updated_at`, and create a `MsgConsentEvent` with action `opt_in`.

Consent event fields:
- msg_guest_id
- action (opt_in | opt_out)
- method (keyword | web | api | form)
- source (e.g., the phone number, campaign, etc.)
- ip, user_agent
- occurred_at
- meta (JSON)

## Delivery receipts (DLR)
- Twilio status callback updates `msg_deliveries` where `provider_message_id` == Twilio `MessageSid`.
- Status mapping:
  - queued/accepted/sending/sent → `status=sent`, `sent_at` set if missing
  - delivered → `status=delivered`, `delivered_at` set
  - undelivered/failed → `status=failed`, `failed_at` set, `error` populated

## Sending behavior with consent
- SMS to a guest with `is_subscribed=false` is skipped at controller and job levels.
- Inbound START re-enables sending by flipping `is_subscribed=true`.

## Setup checklist
- [ ] Configure env vars (Twilio + HELP fields if desired)
- [ ] Run `php artisan migrate`
- [ ] In Twilio Console, set webhooks:
  - [ ] Messaging (incoming) → `POST /webhooks/twilio`
  - [ ] Status callback → `POST /webhooks/twilio/status`
- [ ] Test STOP/START/HELP flows
- [ ] Test sending to subscribed vs unsubscribed
- [ ] Verify DLR status updates

## Troubleshooting
- 403 from webhook: signature check failed. Ensure the exact URL matches Twilio’s configured webhook and `TWILIO_AUTH_TOKEN` is correct.
- HELP response: adjust values in `config/messaging.php` or env variables.
- No DLR updates: confirm the status callback URL and that `provider_message_id` is being saved (check `msg_deliveries`).

## Security notes
- Webhooks are signed by Twilio. Keep `TWILIO_AUTH_TOKEN` secret.
- You can disable signature validation locally by setting `TWILIO_VALIDATE_SIGNATURE=false`.

## Reference files
- `routes/web.php`
- `src/Http/Middleware/VerifyTwilioSignature.php`
- `src/Http/Controllers/Api/TwilioWebhookController.php`
- `src/Http/Controllers/Api/TwilioStatusWebhookController.php`
- `src/Services/MessageService.php`
- `src/Jobs/ProcessMsgDelivery.php`
- `config/messaging.php`
- `config/twilio.php`
- `database/migrations/2025_08_26_000000_create_msg_consent_events.php`
