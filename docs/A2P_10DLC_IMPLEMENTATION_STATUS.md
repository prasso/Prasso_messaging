# A2P 10DLC Implementation Status

This document summarizes the A2P 10DLC compliance features implemented in the `prasso/messaging` package and provides references to code locations, configuration, and how to test.

## Implemented Items

- **Web form opt-in (primary method)**
  - Endpoint: `POST /api/consents/opt-in-web` (public)
  - Controller: `src/Http/Controllers/Api/ConsentController.php` → `optInWeb()`
  - Behavior:
    - Validates payload including `consent_checkbox`.
    - Creates/updates `MsgGuest` with `is_subscribed = false` (pending).
    - Logs `MsgConsentEvent` with `action=opt_in_request`, `method=web`, IP, user agent, `source_url` and metadata.
    - Sends confirmation SMS instructing user to reply YES.
  - Service used for SMS: `src/Services/SmsService.php` (resolves team “from” number, uses Twilio).

- **Double opt-in enforcement**
  - Model default changed: `src/Models/MsgGuest.php` → `is_subscribed` now defaults to `false`.
  - DB default changed: migration `database/migrations/2025_08_27_163500_alter_msg_guests_is_subscribed_default_false.php` sets column default to false for future inserts.
  - Pending state: Web signups remain unsubscribed until inbound keyword matches `config('twilio.opt_in_keywords')` (YES/START/etc), handled in `src/Http/Controllers/Api/TwilioWebhookController.php`.
  - Outbound enforcement: `src/Jobs/ProcessMsgDelivery.php` skips SMS when `is_subscribed === false`.

- **Outbound SMS compliance footer**
  - All outbound SMS via `src/Jobs/ProcessMsgDelivery.php` now include a concise footer:
    - Business identification, STOP instruction, disclaimer, and optional contact.
    - Footer source: per-team `MsgTeamSetting` first, otherwise `config('messaging.help.*')`.
    - Helper methods: `buildSmsFooter()` and `applySmsFooterAndLimit()`.

- **Keyword set updates**
  - Opt-out keywords now include `OPTOUT` (`config/twilio.php`).
  - HELP handling now includes `?` in addition to `HELP`, `INFO`, `SUPPORT` (`TwilioWebhookController::handleIncomingMessage()`).
  - Opt-in keywords list deduplicated to remove duplicate `UNSTOP` (`config/twilio.php`).
    - Ensures message stays within Twilio's ~1600 char limit and logs GSM/UCS-2 segment estimate.

- **Inbound keyword handling and consent logging** (pre-existing, verified)
  - Opt-in/Opt-out/Help keywords in `config/twilio.php`.
  - Processing in `src/Http/Controllers/Api/TwilioWebhookController.php`.
  - Consent events recorded in `msg_consent_events` via `src/Models/MsgConsentEvent.php`.
  - Inbound messages persisted: `msg_inbound_messages` table.
  - Webhook security: `VerifyTwilioSignature` middleware on inbound routes in `routes/web.php`.

## Configuration

- `config/messaging.php`
  - `sms_from`: default sender number (fallback if team setting not provided).
  - `help.*`: `business_name`, `disclaimer`, contact fields, and HELP template.
- `msg_team_settings` table per-team overrides (migration: `2025_08_26_010100_create_msg_team_settings_table.php`).
- `config/twilio.php`: credentials, webhook URL, and opt-in/opt-out/help keyword lists.

## New/Updated Files

- `src/Http/Controllers/Api/ConsentController.php` (new)
- `src/Services/SmsService.php` (new)
- `src/Models/MsgGuest.php` (default changed to unsubscribed)
- `database/migrations/2025_08_27_163500_alter_msg_guests_is_subscribed_default_false.php` (new)
- `src/Jobs/ProcessMsgDelivery.php` (footer injection and length handling)
- `routes/api.php` (public route for web opt-in)

## Testing Steps (Manual)

1. **Web form opt-in (pending state)**
   - POST to `/api/consents/opt-in-web` with JSON:
     ```json
     {
       "phone": "+15551234567",
       "name": "Jane Doe",
       "email": "jane@example.com",
       "consent_checkbox": true,
       "source_url": "https://example.com/subscribe",
       "team_id": 1
     }
     ```
   - Expect HTTP 202 and a confirmation SMS to the phone.
   - Verify DB: new/updated `msg_guests` row with `is_subscribed=false` and a `msg_consent_events` row with `action=opt_in_request`.

2. **Confirm opt-in via SMS**
   - Reply "YES" or configured opt-in keyword to your Twilio number.
   - `TwilioWebhookController` will mark `is_subscribed=true` and log consent.

3. **Outbound footer**
   - Send any message via existing send API; check received SMS includes footer with “Reply STOP to unsubscribe” and disclaimer.
   - Review logs for `SMS length/segments` entry.

## Notes / Next Improvements

- Add throttling and CAPTCHA to `/api/consents/opt-in-web` to mitigate abuse.
- Ensure keyword lists include all required variants (e.g., add "CONFIRM" for opt-in, "OPTOUT" for opt-out, and "?" for help) in `config/twilio.php`.
- Optional: detect existing STOP line in message body to avoid duplicating the STOP instruction in the footer.
- Document privacy/data erasure endpoint once implemented.
