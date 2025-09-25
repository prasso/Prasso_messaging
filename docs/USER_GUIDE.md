# Messaging Package – User Guide

This guide shows how to use the Messaging APIs to manage recipients (guests), create messages, and send them to guests, registered users, and CHM members.

All examples assume your app has Sanctum configured and that you pass a Bearer token in `Authorization`.

- Package path: `packages/prasso/messaging/`
- Auth: Sanctum (all endpoints require `Authorization: Bearer <token>`)

## Getting a token (Sanctum)

Your application defines how tokens are issued (e.g., a Login endpoint or personal access tokens). A common development approach via Tinker:

```bash
php artisan tinker
>>> $u = App\Models\User::first();
>>> $token = $u->createToken('api')->plainTextToken;
```
Use the printed token in Authorization headers below.

## Endpoints overview

Routes are defined in `packages/prasso/messaging/routes/api.php` and are all prefixed with `/api`.

- Guests: `GET/POST /api/guests`, `GET/PUT/DELETE /api/guests/{id}`, `POST /api/guests/{id}/convert`, `POST /api/guests/{id}/engage`
- Messages: `GET/POST /api/messages`, `GET/PUT/DELETE /api/messages/{id}`, `POST /api/messages/send`
- Campaigns: `GET/POST /api/campaigns`, `GET/PUT/DELETE /api/campaigns/{id}`, `POST /api/campaigns/{id}/messages`, `POST /api/campaigns/{id}/launch`
- Engagements: `GET/POST /api/engagements`, `GET/PUT/DELETE /api/engagements/{id}`, `POST /api/engagements/{id}/responses`
- Workflows: `GET/POST /api/workflows`, `GET/PUT/DELETE /api/workflows/{id}`, `POST /api/workflows/{id}/steps`, `POST /api/workflows/{id}/start`
- Guest Messages: `GET/POST /api/guest-messages`, `GET/PUT/DELETE /api/guest-messages/{id}`
- Alerts: `POST /api/alerts/emergency`, `POST /api/alerts/news`
- Events: `GET/POST /api/events`, `GET/PUT/DELETE /api/events/{id}`, `POST /api/events/{id}/reminders`
- Voice Broadcasts: `POST /api/voice-broadcasts/send`

## Create a guest

```bash
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Guest",
    "email": "jane@example.com",
    "phone": "+15551234567"
  }' \
  http://localhost/api/guests
```

## Create a message

`type` can be `email`, `sms`, `push`, or `inapp`.

```bash
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "email",
    "subject": "Welcome",
    "body": "Hello and welcome!"
  }' \
  http://localhost/api/messages
```

Response includes the new message `id`.

## Send a message

You can send to guests, registered users, and CHM members by providing arrays of IDs. At least one of `guest_ids`, `user_ids`, or `member_ids` must be present.

- Email requires recipients to have `email`.
- SMS requires recipients to have `phone`.
- Push is currently not configured and will be skipped.
- In-app is only supported for `user_ids` (registered users).

```bash
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message_id": 1,
    "guest_ids": [1,2],
    "user_ids": [10,11],
    "member_ids": [100,101]
  }' \
  http://localhost/api/messages/send
```

Example response:

```json
{
  "message": "Queued deliveries",
  "queued": 3,
  "skipped": 1
}
```

Queued items will be written to `msg_deliveries` with status `queued`. Items may be skipped if the required contact info is missing, the channel is not available, or the recipient is suppressed for the channel.

### Suppressions

To prevent sending to a recipient on a channel, insert a record into `msg_suppressions`:

```sql
INSERT INTO msg_suppressions (recipient_type, recipient_id, channel, reason, source, metadata, created_at, updated_at)
VALUES ('member', 100, 'sms', 'unsubscribed', 'admin', NULL, NOW(), NOW());
```

Suppressed recipients will be counted as `skipped` with `error = "suppressed"`.

## Inspect delivery logs

Delivery logs are stored in the `msg_deliveries` table. You can query via your DB client or the Eloquent relation:

```php
$message = Prasso\Messaging\Models\MsgMessage::find($id);
$deliveries = $message->deliveries; // collection of MsgDelivery
```

Fields include: `recipient_type` (`user|guest`), `recipient_id`, `channel`, `status`, `error` (when skipped/failed), `provider_message_id`, and `metadata`.

## Common errors

- 401 Unauthorized: Missing/invalid token. Ensure `Authorization: Bearer <token>` header is set.
- 422 Validation error: Check that `message_id` exists and that provided `user_ids` and `guest_ids` are valid.
- 404 Not found: Message does not exist.

## Admin UI (Filament)

If your app uses Filament, the messaging resources are registered automatically by the service provider. You should see resources for Messages, Guests, Campaigns, Engagements, and Workflows in your Filament admin panel.

## Tips

- Start with `email` or `sms` channels. Ensure recipients have `email` or `phone` populated.
- Use `GET /api/messages/{id}` after creation to confirm message fields before sending.
- For testing, create a few guests and a simple email message, then use `/api/messages/send` with `guest_ids` only.
