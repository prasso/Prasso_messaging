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
  - `id`, `user_id`, `name`, `email` (unique), `phone` (nullable), timestamps
- **`msg_messages`** (Option A)
  - `id`, `type` (`email|sms|push|inapp`), `subject` (nullable), `body`, timestamps
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

`msg_deliveries` columns and indexes:

- `id`, `msg_message_id` FK -> `msg_messages`
- `recipient_type` (`user|guest`)
- `recipient_id` (FK to `users.id` or `msg_guests.id` depending on `recipient_type`)
- `channel` (`email|sms|push|inapp`)
- `status` (`queued|sent|delivered|failed|skipped`)
- `provider_message_id` (nullable)
- `error` (nullable)
- `metadata` JSON (nullable)
- `sent_at`, `delivered_at`, `failed_at` (nullable)
- timestamps
- Indexes: (`recipient_type`, `recipient_id`), (`channel`, `status`)

## Key Models and Relations

- `MsgMessage` (`src/Models/MsgMessage.php`)
  - `$fillable = ['subject','body','type']`
  - `guests()` many-to-many via `msg_guest_messages`
  - `workflows()` many-to-many via `msg_workflow_steps` (note: columns `msg_messages_id`, `msg_workflows_id`)
  - `deliveries()` hasMany to `MsgDelivery`
- `MsgDelivery` (`src/Models/MsgDelivery.php`)
  - Tracks per-recipient delivery attempts
  - Casts `metadata` to array; timestamp casts
- `MsgGuest` (`src/Models/MsgGuest.php`)
  - Represents external recipients (not registered users)

## Unified Recipient Abstraction

- `RecipientResolver` (`src/Services/RecipientResolver.php`)
  - Input: `user_ids[]` and/or `guest_ids[]`
  - Output: normalized recipients: `{ recipient_type: 'user'|'guest', recipient_id, email?, phone? }`
  - Sources: `App\Models\User` and `Prasso\Messaging\Models\MsgGuest`

## Send Flow and Delivery Logging

- Endpoint: `POST /api/messages/send`
- Controller: `MessageController@send()` (`src/Http/Controllers/Api/MessageController.php`)
- Flow:
  1. Validates `message_id`, `user_ids[]` (exists: `users`), `guest_ids[]` (exists: `msg_guests`);
     requires at least one of `user_ids` or `guest_ids`.
  2. Resolves recipients via `RecipientResolver`.
  3. For each recipient, determines channel availability:
     - `email`: requires `email`
     - `sms`: requires `phone`
     - `push`: currently skipped (not configured)
     - `inapp`: only supported for `recipient_type = user`
  4. Creates a `msg_deliveries` row with `status = queued` when viable, otherwise `skipped` with reason in `error`.
  5. Returns counts of `queued` vs `skipped`.

Note: Actual dispatch to providers (Mail/Twilio/Push) should be implemented by background jobs that process queued deliveries and update `status`, `provider_message_id`, and timestamps.

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
- `src/Http/Controllers/Api/MessageController.php`
- `src/Services/RecipientResolver.php`
- `src/Models/MsgMessage.php`, `src/Models/MsgDelivery.php`, `src/Models/MsgGuest.php`
