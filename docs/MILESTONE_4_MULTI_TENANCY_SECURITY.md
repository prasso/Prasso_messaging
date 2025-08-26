# Milestone 4 — Multi‑Tenancy and Security

This milestone introduces team isolation (multi‑tenancy), per‑team configuration, and stronger PII protections.

## What’s Included

- Team isolation with `team_id` on core tables
  - Tables updated: `msg_guests`, `msg_messages`, `msg_deliveries`, `msg_consent_events`, `msg_inbound_messages`, `msg_guest_messages`
- Per‑team configuration table: `msg_team_settings`
  - Fields: `sms_from`, HELP overrides (business/purpose/contact/disclaimer), `rate_batch_size`, `rate_batch_interval_seconds`, `meta`
- PII hardening in `msg_guests`
  - Encrypted casts for `email` and `phone`
  - Hashed lookups via `phone_hash` and `email_hash` (SHA‑256)
- Controller/Job updates
  - `MessageController::send()` accepts optional `team_id`, propagates to deliveries, applies per‑team rate limits
  - `ProcessMsgDelivery` chooses `from` by precedence: delivery metadata → team `sms_from` → global `messaging.sms_from` → `twilio.phone_number`
  - `TwilioWebhookController` uses `phone_hash` lookup, sets `team_id` on inbound and consent events, and uses team HELP overrides when possible

---

## Database Changes

- New/updated migrations (apply in your host app):
  - Add `team_id` to core tables (with indexes)
  - Create `msg_team_settings`
  - Add `phone_hash`, `email_hash` to `msg_guests`

Apply migrations:

```bash
php artisan migrate
```

---

## Configuration

- Global defaults still come from `config/messaging.php` and `config/twilio.php`.
- Per‑team overrides come from `msg_team_settings` rows (by `team_id`).
  - Rate limits: `rate_batch_size`, `rate_batch_interval_seconds`
  - HELP overrides: `help_business_name`, `help_purpose`, `help_contact_phone`, `help_contact_email`, `help_contact_website`, `help_disclaimer`
  - SMS From: `sms_from`

---

## API Changes

- `POST /api/messages/send`
  - New optional field: `team_id` (integer). If omitted, falls back to `MsgMessage.team_id`.
  - Per‑team rate limits applied when `team_id` is known.

Example:

```bash
curl -X POST \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "message_id": 1,
    "guest_ids": [1,2,3],
    "team_id": 42
  }' \
  https://your-app.test/api/messages/send
```

---

## Webhook Behavior

- Inbound webhook persists `msg_inbound_messages` with `team_id` when sender can be matched.
- Guest lookup uses `phone_hash` with a fallback to legacy `phone LIKE`.
- HELP response text uses team overrides when `team_id` is known; otherwise uses global defaults.
- Consent keyword handling (STOP/START) logs `MsgConsentEvent` with `team_id` and updates `is_subscribed`.

---

## Security Model

- PII at rest:
  - `MsgGuest::$casts` encrypts `phone` and `email` via Laravel encrypted casts.
  - Hashed columns `phone_hash`, `email_hash` enable lookups without exposing raw PII.
- Secrets:
  - Twilio SID/token sourced from `config/twilio.php`/env.
  - From number resolution prefers per‑team settings.

---

## Code References

- Models: `src/Models/MsgGuest.php`, `src/Models/MsgMessage.php`, `src/Models/MsgDelivery.php`, `src/Models/MsgConsentEvent.php`, `src/Models/MsgInboundMessage.php`, `src/Models/MsgTeamSetting.php`
- Controller: `src/Http/Controllers/Api/MessageController.php`, `src/Http/Controllers/Api/TwilioWebhookController.php`
- Job: `src/Jobs/ProcessMsgDelivery.php`
- Config: `config/messaging.php`, `config/twilio.php`
- Migrations: see `database/migrations/*team*` and hash additions

---

## Operational Checklist

- [ ] Migrate database: `php artisan migrate`
- [ ] Insert `msg_team_settings` rows for each team (sms_from, HELP fields, rate limits)
- [ ] Ensure guests and messages have the correct `team_id`
- [ ] Run queue worker: `php artisan queue:work`
- [ ] Test HELP/STOP/START for a known team number and verify per‑team overrides

---

## Limitations & Next Steps

- Consider a global tenant scope or middleware to automatically apply `team_id` constraints.
- Add admin UI (Filament) to manage `msg_team_settings`.
- Expand per‑team caps to include monthly counters or dynamic throttling.
