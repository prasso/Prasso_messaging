# Milestone 3 — Data, Reporting, and Inbox

This milestone adds inbound message storage and basic inbox and export capabilities, enabling audits and two‑way messaging workflows.

## What’s Included

- Inbound message storage in `msg_inbound_messages`
  - Columns: `msg_guest_id`, `from`, `to`, `body`, `media` (JSON), `provider_message_id`, `received_at`, `raw` (JSON), timestamps
  - See migration: `database/migrations/2025_08_26_000100_create_msg_inbound_messages_table.php`
- Eloquent model: `Prasso\Messaging\Models\MsgInboundMessage`
  - Casts: `media`, `raw` arrays; `received_at` datetime
  - Relation: `guest()` to `MsgGuest`
- Webhook persistence
  - Controller: `src/Http/Controllers/Api/TwilioWebhookController.php`
  - Every inbound message is saved before keyword handling (STOP/START/HELP)
  - Media URLs captured from `NumMedia`/`MediaUrl{N}`
  - Guest matched by normalized phone
- Inbox + CSV export endpoints (protected by `auth:sanctum`)
  - `GET /api/inbound-messages` — paginated list with filters: `phone`, `from_date`, `to_date`, `per_page`
  - `GET /api/inbound-messages/export` — CSV stream with optional `from_date`/`to_date`
  - Controller: `src/Http/Controllers/Api/InboundMessageController.php`
  - Routes: `routes/api.php`

## How It Works

1. Twilio posts to `/webhooks/twilio` (secured by `VerifyTwilioSignature`).
2. `TwilioWebhookController::handleIncomingMessage()` persists the message in `msg_inbound_messages` (with raw payload and media), then processes keywords:
   - STOP → opt‑out + consent event
   - START → opt‑in + consent event
   - HELP → auto‑reply using `config('messaging.help.*')`
3. You can view data via:
   - `GET /api/inbound-messages` (JSON, paginated)
   - `GET /api/inbound-messages/export` (CSV)

## Configuration

- HELP response customization in `config/messaging.php` under `help` keys:
  - `business_name`, `purpose`, `contact_phone`, `contact_email`, `contact_website`, `disclaimer`, `template`
- Twilio validation uses `TWILIO_AUTH_TOKEN`. To disable in dev:
  - `TWILIO_VALIDATE_SIGNATURE=false`

## Database

- Run migrations in your Laravel app:

```bash
php artisan migrate
```

## Notes and Roadmap

- Delivery Receipts (DLR) are handled via `/webhooks/twilio/status` and update `msg_deliveries` (Milestone 1).
- Upcoming reporting endpoints (counts for opt‑in/out and delivery funnel) will be added as part of the reporting phase.
- Future enhancements: inbox triage fields (assigned/resolved), per‑tenant scoping, advanced analytics.
