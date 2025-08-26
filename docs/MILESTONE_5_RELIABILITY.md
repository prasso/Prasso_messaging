# Milestone 5 — Reliability

This milestone adds robust retries, backoff, structured logging, and consistent configuration for outbound deliveries.

## What changed

- __Job reliability__: `src/Jobs/ProcessMsgDelivery.php`
  - __Retries/backoff__: per-job `$tries = 5` and `backoff()` schedule `[60, 120, 300, 600]`.
  - __Transient vs permanent errors__:
    - SMS (Twilio): retries on HTTP 429 and 5xx; 4xx are marked failed.
    - Email/other: retries on timeouts/connection/temporary errors.
  - __Structured logging__: `logCtx()` adds `delivery_id`, `channel`, `attempt`, `team_id`, `recipient_type`, `recipient_id`.
  - __Final failure handler__: `failed(Throwable $e)` persists `status=failed`, `error`, `failed_at` and logs "ProcessMsgDelivery exhausted retries".
  - __Scheduling respect__: if `send_at` is in the future, the job re-releases itself until due.

- __Config alignment__: `src/Services/MessageService.php`
  - Uses `config('twilio.sid')`, `config('twilio.auth_token')`, `config('twilio.phone_number')` with env fallbacks to `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_PHONE_NUMBER`.

## Recommended usage pattern

- __Unify sending through the queue__
  - Create `MsgMessage` + `MsgDelivery (status=queued)` and dispatch `ProcessMsgDelivery`.
  - This centralizes retries/backoff, logging, consent enforcement, and provider error handling.
  - Consider refactoring `MessageService::sendSms()` to enqueue instead of calling Twilio directly.

## Monitoring & alerting

- __Log signal__: "ProcessMsgDelivery exhausted retries" with context (delivery/channel/attempt/team/recipient).
- __Alert examples__:
  - Datadog log-based monitor: query `@message:"ProcessMsgDelivery exhausted retries"` trigger ≥1/5m.
  - CloudWatch metric filter on the above message; alarm on ≥1/5m.
  - Sentry Issue Alert for events whose message contains the phrase.
- __Optional metrics__: increment counters like `messaging.delivery.retry` and `messaging.delivery.failed_final` if you use StatsD/Prometheus.

## Environment

- Ensure `.env` has:
  - `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_PHONE_NUMBER`.
- Check `config/twilio.php` exposes `sid`, `auth_token`, `phone_number` used by both job and service.

## Code references

- Job: `src/Jobs/ProcessMsgDelivery.php`
- Models: `src/Models/MsgDelivery.php`, `src/Models/MsgMessage.php`, `src/Models/MsgGuest.php`
- Service: `src/Services/MessageService.php`

## Notes

- Consent enforcement remains in `ProcessMsgDelivery::sendSms()` for guests; unsubscribed recipients are `status=skipped`.
- Delivery receipts, inbound storage, and multi-tenancy are covered in other milestones.
