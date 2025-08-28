* Prasso Messaging

## A2P Compliance Docs

See Milestone 1 (Consent, HELP, Webhook Verification, Delivery Receipts):

- docs/MILESTONE_1_A2P_COMPLIANCE.md

See Milestone 2 (Scheduling, Rate Limiting, Templating):

- docs/MILESTONE_2_SCHEDULING_RATE_LIMITING_TEMPLATING.md

See Milestone 3 (Data, Reporting, Inbox):

- docs/MILESTONE_3_DATA_REPORTING_INBOX.md

See Milestone 4 (Multi‑Tenancy & Security):

- docs/MILESTONE_4_MULTI_TENANCY_SECURITY.md

See Milestone 5 (Reliability — Retries, Backoff, Logging, Config Alignment):

- docs/MILESTONE_5_RELIABILITY.md

### Environment Variables

- Required for Twilio (see `config/twilio.php`):
  - `TWILIO_ACCOUNT_SID`
  - `TWILIO_AUTH_TOKEN`
  - `TWILIO_PHONE_NUMBER`

- Optional (Messaging config overrides in `config/messaging.php`):
  - `MESSAGING_BATCH_SIZE` (default: 50)
  - `MESSAGING_BATCH_INTERVAL` (default: 1)
  - `TWILIO_NUMBER` (legacy fallback) — prefer `TWILIO_PHONE_NUMBER` and `config('twilio.phone_number')`. `config('messaging.sms_from')` will use `TWILIO_PHONE_NUMBER` first, then fall back to `TWILIO_NUMBER` if not set.
  - `MESSAGING_HELP_BUSINESS`, `MESSAGING_HELP_PURPOSE`, `MESSAGING_HELP_PHONE`, `MESSAGING_HELP_EMAIL`, `MESSAGING_HELP_WEBSITE`, `MESSAGING_HELP_DISCLAIMER`
  - `MESSAGING_PER_GUEST_MONTHLY_CAP` (default: 4)
  - `MESSAGING_PER_GUEST_WINDOW_DAYS` (default: 30)
  - `MESSAGING_ALLOW_TRANSACTIONAL_BYPASS` (default: true)

For per‑team overrides (Milestone 4), configure rows in `msg_team_settings` for each `team_id`.

## Configuration

- **Twilio Console Webhooks**
  - Inbound messages: point to `POST /webhooks/twilio` (see `routes/web.php`).
  - Delivery status (DLR): point to `POST /webhooks/twilio/status`.
  - Signature validation: routes are protected by `VerifyTwilioSignature` middleware; ensure Twilio credentials are correct so signatures verify.

- **Environment variables**
  - Twilio: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_PHONE_NUMBER`.
  - Messaging help defaults (used in SMS footer/HELP response): set in `config/messaging.php` or via env
    - `MESSAGING_HELP_BUSINESS`, `MESSAGING_HELP_DISCLAIMER`, plus optional `MESSAGING_HELP_PHONE`, `MESSAGING_HELP_EMAIL`, `MESSAGING_HELP_WEBSITE`.

- **Per‑team settings (`msg_team_settings`)**
  - Create a row per `team_id` with optional overrides:
    - `sms_from` (sender number)
    - `help_business_name`, `help_disclaimer`, `help_contact_phone`, `help_contact_email`, `help_contact_website`
    - Optional rate limits: `rate_batch_size`, `rate_batch_interval_seconds`

- **Public web opt‑in endpoint**
  - Endpoint: `POST /api/consents/opt-in-web` (see `routes/api.php` and `src/Http/Controllers/Api/ConsentController.php`).
  - Expected fields: `phone` (required), `consent_checkbox` (required, accepted), optional `name`, `email`, `source_url`, `team_id`.
  - Security: consider adding throttling and CAPTCHA at the app level.

- **Keywords** (`config/twilio.php`)
  - Review and adjust lists:
    - `opt_in_keywords` (e.g., `START`, `YES`, `UNSTOP`, optionally `CONFIRM`)
    - `opt_out_keywords` (e.g., `STOP`, `UNSUBSCRIBE`, optionally `OPTOUT`)
  - Help/INFO keywords are handled in the webhook controller; you can extend the set (e.g., add `?`).

- **Migrations**
  - Run `php artisan migrate` in your Laravel app to apply package migrations, including the default change for `msg_guests.is_subscribed` (now defaults to false for double opt‑in).

- **Footer behavior**
  - All outbound SMS get a compliance footer (business ID, “Reply STOP to unsubscribe”, disclaimer, optional contact). Values are sourced from `msg_team_settings` when present, otherwise from `config/messaging.php`.

- **Rate/Frequency governance**
  - Per‑subscriber cap enforced in `ProcessMsgDelivery::sendSms()` using a rolling window.
  - Configure in `config/messaging.php` → `rate_limit` or via env:
    - `per_guest_monthly_cap` (`MESSAGING_PER_GUEST_MONTHLY_CAP`, default 4)
    - `per_guest_window_days` (`MESSAGING_PER_GUEST_WINDOW_DAYS`, default 30)
    - `allow_transactional_bypass` (`MESSAGING_ALLOW_TRANSACTIONAL_BYPASS`, default true)
  - Overrides per delivery via metadata:
    - `type: transactional` to bypass when allowed
    - `override_frequency: true` or `override_until: <ISO timestamp>`

## Privacy & Deletion (Admin API)

- Fields on `msg_guests`:
  - `do_not_contact` (boolean, default false)
  - `anonymized_at` (timestamp, nullable)

- Sending enforcement:
  - `src/Jobs/ProcessMsgDelivery.php` skips SMS when `do_not_contact` is true or `anonymized_at` is set.

- Endpoints (authenticated; see `routes/api.php` and `src/Http/Controllers/Api/PrivacyController.php`):
  - `POST /api/guests/{id}/privacy/dnc` → mark do‑not‑contact and unsubscribe.
  - `DELETE /api/guests/{id}/privacy/dnc` → clear do‑not‑contact.
  - `POST /api/guests/{id}/privacy/anonymize` → anonymize PII (name/email/phone cleared and hashed fields null), mark DNC, set `anonymized_at`.
  - `DELETE /api/guests/{id}/privacy` → delete guest (detaches messages and deletes engagement responses first).

- Logging:
  - Operations are logged via `Log::info`. Consider adding an audit table in your app for stricter compliance.

## Team Verification & Auditability

- Fields on `msg_team_settings`:
  - `verification_status` (verified | pending | rejected | suspended)
  - `verified_at` (timestamp, nullable)
  - `verification_notes` (text, nullable)

- Enforcement:
  - `src/Jobs/ProcessMsgDelivery.php` checks `team_id` on each delivery; if the team's `verification_status` is not `verified` (or settings missing), the send is skipped with `error='team not verified'`.

- Audit table:
  - `msg_team_verification_audits` records status changes: `team_id`, `status`, `notes`, `changed_by_user_id`, `created_at`.

- Admin endpoints (authenticated; see `routes/api.php` and `src/Http/Controllers/Api/TeamVerificationController.php`):
  - `GET /api/teams/{teamId}/verification/status` → current status/details
  - `POST /api/teams/{teamId}/verification/status` → set status (`verified|pending|rejected|suspended`), optional `notes`
  - `GET /api/teams/{teamId}/verification/audits` → list audit history

- Workflow:
  1. Admin sets status to `verified` when the team passes checks; `verified_at` auto-populates.
  2. Any other status unsets `verified_at` and blocks outbound SMS from that team.
