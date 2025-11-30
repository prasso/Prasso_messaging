# Messaging Package – Technical Documentation

This document explains the architecture, data model, API surface, and extension points for the `prasso/messaging` package.

- Package path: `packages/prasso/messaging/`
- Service provider: `src/MessagingServiceProvider.php` (loads routes + migrations)
- Auth: Sanctum (all API routes are under `Route::middleware(['api','auth:sanctum'])->prefix('api')`)

## Architecture

- **Routes**: `routes/api.php` registers REST endpoints for guests, messages, campaigns, engagements, workflows, guest-messages, alerts, events, and voice-broadcasts. All are Sanctum-protected.
- **Controllers**: `src/Http/Controllers/Api/*Controller.php`
- **Models**: `src/Models/*`
- **Services**: `src/Services/*` (e.g., `RecipientResolver`)
- **Filament resources**: `src/Filament/Resources/*` (admin UI)
- **Migrations**: `database/migrations/*.php`

## Database Schema

Core tables (created by `2024_09_14_132125_messaging_tables.php`):

- **`msg_guests`**
  - `id`, `team_id`, `user_id`, `name`, `email` (encrypted), `email_hash` (SHA-256), `phone` (encrypted, nullable), `phone_hash` (SHA-256), consent fields, timestamps
- **`msg_messages`** (Option A)
  - `id`, `team_id`, `type` (`email|sms|push|inapp`), `subject` (nullable), `body`, timestamps
- **`msg_workflows`**
  - `id`, `name`, `description` (nullable), timestamps
- **`msg_workflow_steps`**
  - `id`, `msg_workflows_id` FK, `msg_messages_id` FK, `delay_in_minutes` (default 0), timestamps
- **`msg_guest_messages`** (legacy association)
  - `id`, `msg_guest_id` FK, `msg_message_id` FK, `is_sent` (bool), timestamps
- **`msg_engagements`**
  - `id`, `type` (`contest|survey|poll`), `title`, `description`, timestamps
- **`msg_engagement_responses`**
  - `id`, `msg_engagement_id` FK, `msg_guest_id` FK, `response`, timestamps
- **`msg_campaigns`**
  - `id`, `name`, `start_date`, `end_date`, `description`, timestamps
- **`msg_campaign_messages`**
  - `id`, `campaign_id` FK, `message_id` FK, timestamps

Alter and logging tables:

- **`2025_08_17_000001_alter_msg_messages_add_subject_body.php`**
  - Safe alter to ensure `subject` + `body` exist and drop legacy `content` if present.
- **`2025_08_17_000002_create_msg_deliveries_table.php`**
  - Creates `msg_deliveries` with delivery audit trails.
  - Enhanced by Milestone 4 to include `team_id` and `send_at`.
  
- **Team settings and security (Milestone 4)**
  - `2025_08_26_010000_add_team_id_to_core_tables.php` — adds `team_id` to core tables with indexes
  - `2025_08_26_010100_create_msg_team_settings_table.php` — creates `msg_team_settings` for per-team config
  - `2025_08_26_010200_add_hashes_to_msg_guests.php` — adds `phone_hash`, `email_hash` to `msg_guests`

`msg_deliveries` columns and indexes:

- `id`, `team_id`, `msg_message_id` FK -> `msg_messages`
- `recipient_type` (`user|guest|member`)
- `recipient_id` (FK to `users.id` or `msg_guests.id` depending on `recipient_type`)
- `channel` (`email|sms|push|inapp`)
- `status` (`queued|sent|delivered|failed|skipped`)
- `provider_message_id` (nullable)
- `error` (nullable)
- `metadata` JSON (nullable)
- `sent_at`, `delivered_at`, `failed_at` (nullable)
- timestamps
- Indexes: (`recipient_type`, `recipient_id`), (`channel`, `status`), unique (`msg_message_id`, `recipient_type`, `recipient_id`, `channel`)

Suppressions table:

- **`2025_09_25_150000_create_msg_suppressions_table.php`**
  - Creates `msg_suppressions` to store per-recipient per-channel opt-outs and blocks.
  - Columns: `id`, `recipient_type` (`user|guest|member`), `recipient_id`, `channel` (`email|sms`), `reason`, `source`, `metadata`, timestamps.
  - Indexes: unique (`recipient_type`, `recipient_id`, `channel`) and standard lookups.

## Key Models and Relations

- `MsgMessage` (`src/Models/MsgMessage.php`)
  - `$fillable = ['team_id','subject','body','type']`
  - `guests()` many-to-many via `msg_guest_messages`
  - `workflows()` many-to-many via `msg_workflow_steps` (note: columns `msg_messages_id`, `msg_workflows_id`)
  - `deliveries()` hasMany to `MsgDelivery`
- `MsgDelivery` (`src/Models/MsgDelivery.php`)
  - Tracks per-recipient delivery attempts
  - Casts `metadata` to array; timestamp casts
- `MsgGuest` (`src/Models/MsgGuest.php`)
  - Represents external recipients (not registered users)
  - Encrypted casts for `email` and `phone`; maintains `email_hash` and `phone_hash` mutators
- `MsgTeamSetting` (`src/Models/MsgTeamSetting.php`)
  - Per-team overrides: `sms_from`, HELP components, and per-team rate limits

## Unified Recipient Abstraction

- `RecipientResolver` (`src/Services/RecipientResolver.php`)
  - Input: `user_ids[]` and/or `guest_ids[]` and/or `member_ids[]`
  - Output: normalized recipients: `{ recipient_type: 'user'|'guest'|'member', recipient_id, email?, phone? }`
  - Sources: `App\Models\User`, `Prasso\Messaging\Models\MsgGuest`, `Prasso\Church\Models\Member`

## Send Flow and Delivery Logging

- Endpoint: `POST /api/messages/send`
- Controller: `MessageController@send()` (`src/Http/Controllers/Api/MessageController.php`)
- Flow:
  1. Validates `message_id`, `user_ids[]` (exists: `users`), `guest_ids[]` (exists: `msg_guests`), `member_ids[]` (exists: `chm_members`);
     requires at least one of `user_ids`, `guest_ids`, or `member_ids`.
  2. Resolves recipients via `RecipientResolver`.
  3. Checks `msg_suppressions` per recipient/channel; suppressed recipients are marked `skipped` with `error = 'suppressed'`.
  4. For each recipient, determines channel availability:
     - `email`: requires `email`
     - `sms`: requires `phone`
     - `push`: currently skipped (not configured)
     - `inapp`: only supported for `recipient_type = user`
  5. Creates a `msg_deliveries` row (with `team_id` when known) with `status = queued` when viable, otherwise `skipped` with reason in `error`.
  6. Dispatches `ProcessMsgDelivery` job for each queued delivery.
  7. Returns counts of `queued` vs `skipped`.

### Queue Processing

After deliveries are created, the `ProcessMsgDelivery` job processes them asynchronously:

- **Job class**: `Prasso\Messaging\Jobs\ProcessMsgDelivery` (`src/Jobs/ProcessMsgDelivery.php`)
- **Trigger**: Automatically dispatched when `msg_deliveries` are created with `status = queued`
- **Processing**:
  1. Checks if delivery is still in `queued` status (may have been processed already)
  2. Respects `send_at` scheduling: if in future, releases job back to queue
  3. Resolves recipient contact info (email/phone) based on `recipient_type`
  4. Applies rate limiting and compliance checks
  5. Sends via appropriate channel (email via Mail, SMS via Twilio)
  6. Updates delivery status to `sent`, `failed`, or `skipped` with error details
- **Retries**: 5 attempts with exponential backoff (60s, 120s, 300s, 600s)
- **Failure handling**: After all retries exhausted, marks delivery as `failed` and logs error

**Critical:** The queue worker must be running continuously for deliveries to be processed:

```bash
php artisan queue:work
```

Without a running queue worker, deliveries remain in `queued` status indefinitely.

Rate limiting can be overridden by team via `MsgTeamSetting`.

## API Surface (Summary)

All routes are prefixed with `/api` and require Sanctum auth.

- Guests: `GET/POST /guests`, `GET/PUT/DELETE /guests/{id}`, `POST /guests/{id}/convert`, `POST /guests/{id}/engage`
- Messages: `GET/POST /messages`, `GET/PUT/DELETE /messages/{id}`, `POST /messages/send`
- Campaigns: `GET/POST /campaigns`, `GET/PUT/DELETE /campaigns/{id}`, `POST /campaigns/{id}/messages`, `POST /campaigns/{id}/launch`
- Engagements: `GET/POST /engagements`, `GET/PUT/DELETE /engagements/{id}`, `POST /engagements/{id}/responses`
- Workflows: `GET/POST /workflows`, `GET/PUT/DELETE /workflows/{id}`, `POST /workflows/{id}/steps`, `POST /workflows/{id}/start`
- Guest Messages: `GET/POST /guest-messages`, `GET/PUT/DELETE /guest-messages/{id}`
- Alerts: `POST /alerts/emergency`, `POST /alerts/news`
- Events: `GET/POST /events`, `GET/PUT/DELETE /events/{id}`, `POST /events/{id}/reminders`
- Voice Broadcasts: `POST /voice-broadcasts/send`

See `routes/api.php` for authoritative definitions.

## Security

- All endpoints require a valid Sanctum token via `Authorization: Bearer <token>`.
- Consider adding ability/role gates (e.g., `can:manage-messaging`) for finer control.

### PII Protection
- `MsgGuest` uses Laravel encrypted casts for `email` and `phone`.
- Hash columns `phone_hash`, `email_hash` (SHA‑256) enable privacy-preserving lookups.
- Webhook guest resolution uses `phone_hash` with legacy fallback to `phone LIKE`.

### Secrets and From Number Resolution
- Twilio credentials loaded from `config/twilio.php`/env.
- SMS `from` resolution precedence: delivery metadata → `MsgTeamSetting.sms_from` → `config('messaging.sms_from')` → `config('twilio.phone_number')`.

## Multi‑Tenancy (Team Isolation)
- `team_id` added to core tables to scope data by tenant team.
- `MsgTeamSetting` stores per-team HELP content, rate limits, and `sms_from`.
- `MessageController::send()` accepts optional `team_id` and applies per-team rate limits.
- Webhooks set `team_id` on inbound and consent events when sender matches a guest.

## Extensibility and Next Steps

- **Channel processors**: Add jobs/listeners that consume `msg_deliveries` with `status = queued` and perform actual sends via:
  - Mail (Laravel Mail)
  - SMS (Twilio SDK)
  - Push (FCM/OneSignal)
  - In-app (Laravel Notifications)
- **Preferences/Opt-outs**: Add tables (e.g., `msg_preferences`, `msg_opt_outs`) and consult before queuing deliveries.
- **Provider config**: Store from-addresses/numbers and API tokens in config + env.
- **Observability**: Add events/logging around delivery lifecycle; dashboards in Filament.
- **RBAC**: Add policies and middleware.

## Installation Notes (within host app)

- The package is included as a path repository and service provider is auto-discovered (see `packages/prasso/messaging/composer.json`).
- Ensure `laravel/sanctum` is installed and configured.
- Run migrations: `php artisan migrate`

## File References

- `database/migrations/2024_09_14_132125_messaging_tables.php`
- `database/migrations/2025_08_17_000001_alter_msg_messages_add_subject_body.php`
- `database/migrations/2025_08_17_000002_create_msg_deliveries_table.php`
- `database/migrations/2025_08_26_010000_add_team_id_to_core_tables.php`
- `database/migrations/2025_08_26_010100_create_msg_team_settings_table.php`
- `database/migrations/2025_08_26_010200_add_hashes_to_msg_guests.php`
- `src/Http/Controllers/Api/MessageController.php`
- `src/Http/Controllers/Api/TwilioWebhookController.php`
- `src/Services/RecipientResolver.php`
- `src/Models/MsgMessage.php`, `src/Models/MsgDelivery.php`, `src/Models/MsgGuest.php`, `src/Models/MsgTeamSetting.php`, `src/Models/MsgConsentEvent.php`, `src/Models/MsgInboundMessage.php`
