# Milestone 2 — Scheduling, Rate Limiting, and Templating

This milestone adds three capabilities to the messaging package:

- Scheduling: optional delayed sending using a `send_at` timestamp.
- Rate limiting: simple pacing of queued deliveries using batch size and interval.
- Templating: lightweight token replacement for recipient names in SMS and Email.

---

## Database Changes

- Migration: `2025_08_26_000003_alter_msg_deliveries_add_send_at.php`
  - Adds `send_at TIMESTAMP NULL` to `msg_deliveries` with an index.

Apply migrations:

```bash
php artisan migrate
```

---

## Configuration

File: `config/messaging.php`

- New section `rate_limit`:
  - `batch_size` (int): number of deliveries per batch.
  - `batch_interval_seconds` (int): delay added between batches.

Environment variables (optional overrides):

- `MESSAGING_BATCH_SIZE` (default: 50)
- `MESSAGING_BATCH_INTERVAL` (default: 1)

Existing compliance-related environment values (unchanged but relevant):

- `TWILIO_PHONE_NUMBER` (preferred default for `sms_from`).
- `TWILIO_NUMBER` (legacy) — still supported as a fallback via `config('messaging.sms_from')`.
- `MESSAGING_HELP_BUSINESS`, `MESSAGING_HELP_PURPOSE`, `MESSAGING_HELP_PHONE`, `MESSAGING_HELP_EMAIL`, `MESSAGING_HELP_WEBSITE`, `MESSAGING_HELP_DISCLAIMER`

Twilio config reminder:

- `config/twilio.php` expects:
  - `TWILIO_ACCOUNT_SID`
  - `TWILIO_AUTH_TOKEN`
  - `TWILIO_PHONE_NUMBER`

---

## API Changes

Endpoint: `POST /api/messages/send`

- New optional field: `send_at` (ISO 8601 datetime). If in the future, deliveries are scheduled accordingly.
- Existing fields remain the same: `message_id`, `guest_ids`, `user_ids`.

Example request (schedule for 3 PM UTC):

```bash
curl -X POST \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "message_id": 1,
    "guest_ids": [1,2,3],
    "send_at": "2025-08-27T15:00:00Z"
  }' \
  https://your-app.test/api/messages/send
```

Response:

```json
{
  "message": "Queued deliveries",
  "queued": 3,
  "skipped": 0
}
```

---

## Scheduling Behavior

- Controller: `Prasso\Messaging\Http\Controllers\Api\MessageController::send()`
  - Accepts `send_at`, stores it on `msg_deliveries.send_at`.
  - Dispatches `ProcessMsgDelivery` with an initial delay based on `send_at`.

- Job: `Prasso\Messaging\Jobs\ProcessMsgDelivery`
  - On execution, if `send_at` is in the future, the job releases itself until due (double-guarding scheduled delivery).

No cron is strictly required with this approach because the queue handles scheduled delays. You must run a queue worker (e.g., `php artisan queue:work`).

---

## Rate Limiting Behavior

- Pacing is applied during dispatch in `MessageController::send()` using simple batching.
  - For N recipients: each batch of `batch_size` gets an additional `batch_interval_seconds` delay.
  - Example with 120 recipients, `batch_size=50`, `batch_interval_seconds=1`:
    - Recipients 0–49: base delay
    - 50–99: base delay + 1s
    - 100–119: base delay + 2s

This provides a light throttle to avoid provider rate caps. For tighter controls, consider switching to Redis token bucket or a dedicated rate limiter in a future milestone.

---

## Templating Behavior

- Job performs lightweight token replacement for recipient names before sending SMS and Email.
- Supported tokens:
  - `{{FirstName}}` or `{{First Name}}` — first part of the name (split by whitespace)
  - `{{Name}}` — full name

- Applied to:
  - Email: subject and body
  - SMS: body

- Recipient name is resolved from the related `User` or `MsgGuest` record.

Example:

- Message body: `"Hi {{FirstName}}, your appointment is confirmed."`
- Recipient: `"Sam Johnson"`
- Result: `"Hi Sam, your appointment is confirmed."`

---

## Code References

- Controller: `src/Http/Controllers/Api/MessageController.php`
  - Validation of `send_at`
  - Storing `send_at`
  - Batch-based delayed dispatch

- Job: `src/Jobs/ProcessMsgDelivery.php`
  - `send_at` guard and job release
  - Token replacement for Email/SMS

- Model: `src/Models/MsgDelivery.php`
  - Added `send_at` to `$fillable` and `$casts`

- Config: `config/messaging.php`
  - `rate_limit` settings

- Migration: `database/migrations/2025_08_26_000003_alter_msg_deliveries_add_send_at.php`

---

## Operational Steps

1. Ensure `.env` contains required Twilio credentials:
   - `TWILIO_ACCOUNT_SID=...`
   - `TWILIO_AUTH_TOKEN=...`
   - `TWILIO_PHONE_NUMBER=+1...`
   - (Optional, legacy) `TWILIO_NUMBER` — used only as a fallback by `config('messaging.sms_from')`.
2. (Optional) Set rate limit overrides:
   - `MESSAGING_BATCH_SIZE=50`
   - `MESSAGING_BATCH_INTERVAL=1`
3. Migrate:
   - `php artisan migrate`
4. Start queue worker:
   - `php artisan queue:work`
5. Use `send_at` in `POST /api/messages/send` to schedule.

---

## Limitations and Future Enhancements

- Rate limiter is simplistic. Future: per-tenant caps, token bucket, dynamic ramp-up.
- Templating supports only name fields. Future: generic variable resolver, template validation.
- Consider standardizing `sms_from` to use `TWILIO_PHONE_NUMBER` consistently.
