# WhatsApp Implementation Plan

## Overview

This document outlines the plan to add WhatsApp support to the Prasso messaging package using Meta WhatsApp Business Cloud API.

**Decision:** Direct Meta API integration (not Twilio) for cost efficiency and official support.

---

## Phase 1: Core Integration (Steps 1-5)

### Step 1: Database Migration

Add WhatsApp configuration fields to `msg_team_settings` table.

**File:** `database/migrations/2025_12_14_000000_add_whatsapp_to_msg_team_settings.php`

**Fields to add:**
- `whatsapp_phone_number_id` (string, nullable) - Meta's phone number ID
- `whatsapp_business_account_id` (string, nullable) - Meta Business Account ID
- `whatsapp_enabled` (boolean, default: false) - Enable/disable WhatsApp for team
- `whatsapp_access_token` (string, nullable) - API token (encrypted)

**Notes:**
- Access token should be encrypted in transit/storage
- Phone number ID is obtained from Meta Business Manager
- Business Account ID is the WABA (WhatsApp Business Account) ID

---

### Step 2: WhatsAppService Class

Create service to handle all Meta API communication.

**File:** `src/Services/WhatsAppService.php`

**Responsibilities:**
- Send messages via Meta Cloud API
- Format phone numbers to E.164 format
- Handle template messages with parameters
- Parse API responses and errors
- Log delivery status and errors
- Validate phone numbers

**Key Methods:**
- `send(string $to, string $body, ?int $teamId = null): array` - Send message
- `sendTemplate(string $to, string $templateName, array $params, ?int $teamId = null): array` - Send template
- `formatPhoneNumber(string $phone): string` - Normalize to E.164
- `validatePhoneNumber(string $phone): bool` - Validate format

**Configuration:**
- Use `config('messaging.whatsapp_api_version')` for API version
- Use `config('messaging.whatsapp_base_url')` for Meta API endpoint
- Resolve team-specific token from `MsgTeamSetting`

---

### Step 3: ProcessMsgDelivery Job Enhancement

Add WhatsApp handler to the existing delivery job.

**File:** `src/Jobs/ProcessMsgDelivery.php` (modify)

**Changes:**
- Add `case 'whatsapp':` in the channel switch statement (line 66-81)
- Create `sendWhatsApp(MsgDelivery $delivery): void` method

**sendWhatsApp() Logic:**
1. Resolve recipient phone number (user/guest/member)
2. Validate phone number format
3. Check team verification status
4. Check rate limiting (reuse SMS logic)
5. Determine WhatsApp account from metadata or team settings
6. Call WhatsAppService to send message
7. Update delivery record with status and provider_message_id
8. Handle Meta-specific errors:
   - Invalid phone number → skip
   - Template not approved → failed
   - Rate limit exceeded → retry
   - Invalid token → failed

**Error Handling:**
- Transient errors (429, 500, 503) → retry with backoff
- Permanent errors → mark failed with error message
- Log all attempts for audit trail

---

### Step 4: Configuration Update

Add WhatsApp settings to messaging config.

**File:** `config/messaging.php` (modify)

**New Configuration:**
```php
'whatsapp' => [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.instagram.com'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
],
```

**Environment Variables Required:**
- `WHATSAPP_ENABLED` - Global enable/disable
- `WHATSAPP_API_VERSION` - Meta API version (default: v18.0)
- `WHATSAPP_PHONE_NUMBER_ID` - Default phone number ID
- `WHATSAPP_BUSINESS_ACCOUNT_ID` - Default WABA ID
- `WHATSAPP_ACCESS_TOKEN` - API token (should be encrypted)

---

### Step 5: ComposeAndSendMessage Page Update

Add WhatsApp as a message type option.

**File:** `app/Filament/Pages/ComposeAndSendMessage.php` (modify)

**Changes:**
1. Add WhatsApp to message type selector (line 194-198):
   ```php
   'whatsapp' => 'WhatsApp',
   ```

2. Update recipient filtering to include WhatsApp:
   - Filter users/guests with phone numbers (like SMS)
   - Show WhatsApp-specific help text

3. Add helper text:
   - "WhatsApp requires pre-approved message templates"
   - "Messages sent to phone numbers in E.164 format"

**UI Behavior:**
- WhatsApp option visible only if team has `whatsapp_enabled = true`
- Recipient filtering shows only contacts with phone numbers
- Display warning about template requirements

---

### Step 5b: MsgTeamSetting Editor (Filament, in messaging package)

Provide an admin UI for managing team-level messaging credentials and toggles, including WhatsApp fields.

**Location:** `packages/prasso/messaging/src/Filament/...` (new)

**Responsibilities:**
- Allow authorized team admins to view/update `msg_team_settings`
- Manage WhatsApp fields added in Step 1
- Ensure sensitive fields (like access tokens) are handled safely (masked in UI)
- Provide a consistent place to enable/disable WhatsApp per team

**Fields to include:**
- `whatsapp_enabled` (boolean)
- `whatsapp_phone_number_id` (string)
- `whatsapp_business_account_id` (string)
- `whatsapp_access_token` (text)

**Access Control / Policies:**
- Restrict to authenticated users with appropriate team admin permissions
- Optionally gate enabling WhatsApp on `verification_status === 'verified'` (align with delivery enforcement)

**Integration Notes:**
- `ComposeAndSendMessage` should rely on `MsgTeamSetting::whatsapp_enabled` to decide whether to show WhatsApp as a message type.
- `WhatsAppService` should resolve per-team credentials from `MsgTeamSetting` (with config fallbacks).

---

## Phase 2: Advanced Features (Steps 6-7)

### Step 6: Webhook Controller

Handle delivery receipts and inbound messages from Meta.

**File:** `src/Http/Controllers/WhatsAppWebhookController.php` (NEW)

**Responsibilities:**
- Verify webhook signature from Meta
- Process delivery status updates (sent, delivered, read, failed)
- Handle inbound messages from customers
- Update `msg_deliveries` status
- Create `msg_inbound_messages` for replies

**Webhook Events to Handle:**
- `message_status_update` - Update delivery status
- `message_template_status_update` - Template approval status
- `message` - Inbound customer message

**Routes:**
- `POST /api/whatsapp/webhook` - Webhook endpoint
- `GET /api/whatsapp/webhook` - Webhook verification (Meta challenge)

---

### Step 7: Message Template Management (Optional)

Support WhatsApp message templates for business messages.

**Database Table:** `msg_whatsapp_templates`
- `id`
- `team_id`
- `name` - Template name (e.g., "order_confirmation")
- `category` - marketing | utility | authentication | service
- `status` - pending | approved | rejected
- `body` - Template text with {{param}} placeholders
- `parameters` - JSON array of parameter definitions
- `meta_template_id` - Meta's template ID
- `approved_at`
- `rejected_reason`
- `timestamps`

**Features:**
- Store approved templates
- Parameter substitution for personalization
- Track approval status
- Admin UI for template management

---

## Phase 3: Testing & Polish (Step 8)

### Step 8: Comprehensive Tests

**Unit Tests:** `tests/Unit/WhatsAppServiceTest.php`
- Phone number formatting
- E.164 validation
- API request building
- Error parsing

**Feature Tests:** `tests/Feature/WhatsAppDeliveryTest.php`
- End-to-end delivery flow
- Rate limiting enforcement
- Team verification checks
- Webhook signature verification
- Status update handling

**Test Coverage:**
- Happy path: successful message delivery
- Error cases: invalid phone, rate limit, API errors
- Webhook handling: delivery receipts, inbound messages
- Retry logic: transient vs permanent errors

---

## Key Design Decisions

| Aspect | Decision | Reason |
|--------|----------|--------|
| **Provider** | Meta Cloud API (direct) | Cost savings ($0.0052/msg vs Twilio markup), official support, future-proof |
| **Phone Format** | E.164 (like SMS) | Consistent with existing SMS handling, standard format |
| **Rate Limiting** | Reuse SMS logic | Meta has similar per-account limits |
| **Templates** | Support but optional | Meta requires templates for business messages |
| **Webhooks** | Phase 2 | Core delivery works without webhooks; webhooks enhance status tracking |
| **Encryption** | Encrypt access token | Security best practice for API credentials |

---

## Files to Create/Modify

### New Files
```
packages/prasso/messaging/
├── database/migrations/
│   └── 2025_12_14_000000_add_whatsapp_to_msg_team_settings.php
├── src/
│   ├── Services/
│   │   └── WhatsAppService.php
│   └── Http/
│       └── Controllers/
│           └── WhatsAppWebhookController.php (Phase 2)
└── tests/
    ├── Unit/
    │   └── WhatsAppServiceTest.php
    └── Feature/
        └── WhatsAppDeliveryTest.php
```

### Modified Files
```
packages/prasso/messaging/
├── src/
│   ├── Jobs/
│   │   └── ProcessMsgDelivery.php (add sendWhatsApp method)
│   └── Models/
│       └── MsgTeamSetting.php (add fillable fields)
├── config/
│   └── messaging.php (add WhatsApp config)
└── routes/
    └── api.php (add webhook route - Phase 2)

app/
└── Filament/Pages/
    └── ComposeAndSendMessage.php (add WhatsApp option)
```

---

## Implementation Checklist

### Phase 1: Core Integration
- [ ] Create database migration
- [ ] Create WhatsAppService class
- [ ] Add sendWhatsApp() to ProcessMsgDelivery
- [ ] Update messaging.php config
- [ ] Update ComposeAndSendMessage page
- [ ] Test basic message delivery

### Phase 2: Advanced Features
- [ ] Create WhatsAppWebhookController
- [ ] Implement webhook signature verification
- [ ] Handle delivery status updates
- [ ] Handle inbound messages
- [ ] Create message template table (optional)
- [ ] Test webhook handling

### Phase 3: Testing & Polish
- [ ] Write unit tests
- [ ] Write feature tests
- [ ] Test error scenarios
- [ ] Test rate limiting
- [ ] Documentation updates

---

## Environment Setup

### Prerequisites
1. Meta Business Account with WhatsApp Business API access
2. Business verification (3-7 business days)
3. Phone number ID and Business Account ID from Meta
4. Access token with `whatsapp_business_messaging` permission

### .env Variables
```
WHATSAPP_ENABLED=true
WHATSAPP_API_VERSION=v18.0
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_BUSINESS_ACCOUNT_ID=your_waba_id
WHATSAPP_ACCESS_TOKEN=your_access_token
```

### Testing with Meta Sandbox
- Meta provides a test phone number for development
- Sandbox mode allows testing without business verification
- Switch to production phone number after verification

---

## Cost Considerations

### Meta Pricing (as of 2024)
- **Service conversations:** FREE (user-initiated)
- **Utility messages:** $0.0052-0.0054 per message
- **Marketing messages:** $0.0052-0.0054 per message
- **Authentication messages:** $0.0052-0.0054 per message

### Comparison with Twilio
- Meta: ~$50-54 for 10,000 marketing messages
- Twilio: ~$100-150+ for same volume (with markup)
- **Savings:** 50-66% with Meta direct integration

---

## Future Enhancements

1. **Message Templates UI** - Admin interface for template management
2. **Delivery Analytics** - Track open rates, delivery status, response times
3. **Conversation Management** - Full chat interface for customer support
4. **Media Support** - Send images, documents, audio via WhatsApp
5. **Interactive Messages** - Buttons, lists, quick replies
6. **Broadcast Lists** - Send to multiple recipients efficiently
7. **Direct Meta Integration** - Webhook for real-time status updates

---

## References

- [Meta WhatsApp Business Cloud API Docs](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [WhatsApp Message Types](https://developers.facebook.com/docs/whatsapp/message-types)
- [Webhook Documentation](https://developers.facebook.com/docs/whatsapp/webhooks)
- [Rate Limiting](https://developers.facebook.com/docs/whatsapp/messaging-limits)

---

**Last Updated:** December 14, 2025
**Status:** Planning Phase
**Next Step:** Implement Phase 1 - Database Migration
