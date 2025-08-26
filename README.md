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
  - `TWILIO_NUMBER` (historical); prefer `TWILIO_PHONE_NUMBER` and `config('twilio.phone_number')`
  - `MESSAGING_HELP_BUSINESS`, `MESSAGING_HELP_PURPOSE`, `MESSAGING_HELP_PHONE`, `MESSAGING_HELP_EMAIL`, `MESSAGING_HELP_WEBSITE`, `MESSAGING_HELP_DISCLAIMER`

For per‑team overrides (Milestone 4), configure rows in `msg_team_settings` for each `team_id`.
