 # Meta WhatsApp Business API Registration Guide

This document describes what you need to do (outside of code) to get approved and configured to send WhatsApp messages using the **Meta WhatsApp Business Platform (Cloud API)**.

---

## What you will end up with (deliverables)

By the end of this process you should have:

- A **Meta Business Manager** (Business Portfolio)
- A **WhatsApp Business Account (WABA)** attached to that Business Manager
- A **WhatsApp Business Phone Number** added and verified
- A **Meta App** with WhatsApp enabled
- A long-lived **Access Token** (or a token refresh strategy)
- The following identifiers needed by the API:
  - `WHATSAPP_BUSINESS_ACCOUNT_ID` (WABA ID)
  - `WHATSAPP_PHONE_NUMBER_ID` (Phone Number ID)
  - `WHATSAPP_ACCESS_TOKEN`
- A working **Webhook endpoint** (optional for sending, required for receipts/inbound)
- At least one **approved message template** (required for business-initiated messages)

---

## 0) Decide how you will register: direct vs. partner

You have two common paths:

- **Direct (recommended for cost control)**
  - You manage the Meta Business Manager, app, tokens, and compliance yourself.
- **Through an official Business Solution Provider (BSP)**
  - They can streamline onboarding/verification, but usually add cost or platform constraints.

This guide assumes **direct Meta Cloud API**.

---

## 1) Create / confirm your Meta Business Manager

1. Go to Meta Business Manager and create a Business Portfolio.
2. Ensure you have:
   - Admin access
   - Your org legal name, address, website, etc.
3. Add at least one additional admin as a backup (recommended).

---

## 2) Business verification (often required for production)

Production WhatsApp sending usually requires **business verification**.

General expectations:

- Use a **company domain email** (avoid free webmail like Gmail for verification steps when possible).
- Prepare documentation:
  - Legal business name documents
  - Address verification
  - Website that matches your business name
- The review process can take **several business days**.

If verification is denied:

- Fix mismatches between legal name, website, documents.
- Resubmit.

---

## 3) Create a Meta App for WhatsApp

1. Go to Meta for Developers.
2. Create an app (commonly type: **Business**).
3. Add the **WhatsApp** product to the app.
4. Configure basic app settings:
   - App domains
   - Privacy policy URL
   - Data deletion URL (required for many Meta apps)

---

## 4) Create or attach a WhatsApp Business Account (WABA)

Inside WhatsApp Manager:

1. Create a **WhatsApp Business Account** (WABA) or attach an existing one.
2. Record the **WABA ID** (this becomes `WHATSAPP_BUSINESS_ACCOUNT_ID`).

---

## 5) Add and verify a phone number for WhatsApp

1. In WhatsApp Manager, add a phone number.
2. Complete verification (SMS/voice).
3. Record the **Phone Number ID** (this becomes `WHATSAPP_PHONE_NUMBER_ID`).

Notes:

- The **Phone Number ID** is not the same as the phone number itself.
- Use a dedicated number for production.

---

## 6) Configure messaging limits and quality (production readiness)

WhatsApp enforces:

- Messaging limits (varies by account history)
- Quality rating (users can block/report)

Best practices:

- Start with smaller volumes
- Send relevant, expected content
- Include clear opt-out guidance in your business processes

---

## 7) Access tokens and permissions (critical)

### Token types you will see

- **Temporary tokens** (short-lived) for development
- **System User tokens** (recommended for servers)
- Long-lived tokens are generally created via Business Manager with a **System User**

### Recommended production approach

1. In Business Manager:
   - Create a **System User** for your server
   - Assign the System User to the WhatsApp account
   - Grant required permissions
2. Generate an access token for that System User.

### Permissions

At minimum, you will typically need permissions equivalent to WhatsApp messaging management (names vary by Meta setup and product evolution). Ensure your token can:

- Send messages
- Read message status webhooks (if you use them)
- Manage templates (if you automate template workflows)

Security:

- Never commit tokens to git
- Store in `.env` and/or a secrets manager
- Rotate tokens if you suspect exposure

---

## 7b) Prasso environment variables (what they are + how to obtain them)

Prasso reads WhatsApp configuration from environment variables (with optional per-team overrides in `msg_team_settings`).

The following values map to `config('messaging.whatsapp.*')` in `packages/prasso/messaging/config/messaging.php`.

### `WHATSAPP_ENABLED`

- Set to `true` to enable WhatsApp sending in the application.
- This is a Prasso toggle (not a Meta value).

### `WHATSAPP_API_VERSION`

- Example: `v18.0`
- This controls which Meta Graph API version Prasso calls.
- You choose this value (it is not an ID you “find”). Typically you keep it aligned with the version your integration was developed against.

### `WHATSAPP_BASE_URL`

- Recommended: `https://graph.facebook.com`
- This is the base URL for the Meta Graph API.
- You choose this value (it is not an ID you “find”).

### `WHATSAPP_BUSINESS_ACCOUNT_ID`

- Also called **WABA ID**.
- Where to get it:
  - In **WhatsApp Manager**, open your WhatsApp Business Account details and copy the **WhatsApp Business Account ID**.
  - You can also see it in Meta Business settings depending on your account setup.

### `WHATSAPP_PHONE_NUMBER_ID`

- This is the Meta **Phone Number ID** (not the phone number itself).
- Where to get it:
  - In **WhatsApp Manager**, open the phone number you added/verified and copy the **Phone Number ID**.
- Common pitfall: confusing the phone number (e.g. `+1...`) with the Phone Number ID.

### `WHATSAPP_ACCESS_TOKEN`

- This is the bearer token used by the server to call the WhatsApp Cloud API.
- Recommended way to obtain (production-ready):
  - In **Meta Business Manager**:
    - Create a **System User**
    - Assign the System User to the WhatsApp account
    - Grant required permissions
    - Generate an access token for that System User
- Local development option:
  - You may use a short-lived developer token for initial testing, but do not use short-lived tokens in production.

### `WHATSAPP_WEBHOOK_VERIFY_TOKEN` (only if using webhooks)

- This is a shared secret used only during Meta’s webhook verification handshake.
- How to obtain:
  - You create this value (it is not provided by Meta).
  - In the Meta webhook configuration screen, set the same verify token value you set here.

### `WHATSAPP_APP_SECRET` (optional; only if you want request signature verification)

- When set, Prasso will verify incoming webhook POSTs using the `X-Hub-Signature-256` header.
- Where to get it:
  - In **Meta for Developers** for your app, go to **Settings** (Basic) and copy the **App Secret**.
- If you leave this blank, Prasso will accept webhook POSTs without signature validation.

---

## 8) Webhooks (optional for sending; needed for receipts/inbound)

You can send WhatsApp messages without webhooks, but for a real messaging system you usually want:

- Delivery receipts (`sent`, `delivered`, `read`, `failed`)
- Inbound messages / replies

### What you need

- A public HTTPS endpoint (no localhost unless tunneling)
- A verify token (shared secret for verification)
- Ability to respond to the Meta webhook verification challenge

### Meta verification flow

- Meta will send a GET request with `hub.challenge`
- Your endpoint must echo back the challenge when the verify token matches

Operational note:

- For local dev, use a tunneling tool (ngrok, Cloudflare Tunnel, etc.).

---

## 9) Message templates (required for business-initiated messages)

WhatsApp has two broad patterns:

- **User-initiated conversations** (service window)
  - User messages you first, you can reply freely within the allowed time window.
- **Business-initiated messages**
  - Typically requires **approved templates**.

### Template approval

1. Create templates in WhatsApp Manager.
2. Choose category:
   - Marketing
   - Utility
   - Authentication
   - Service
3. Submit for approval.
4. Once approved, you can use them in API calls.

Plan for:

- Approval delays
- Rejections due to promotional language in non-marketing categories

---

## 10) Basic smoke test checklist

Before wiring into the application:

- Confirm you have:
  - `WABA ID`
  - `Phone Number ID`
  - Valid access token
- Test sending a message to a known WhatsApp-enabled phone number
- Confirm you can receive webhook events (if webhooks enabled)
- Confirm at least one template is approved (for business-initiated tests)

---

## 11) Prasso configuration mapping

Once you have the Meta values, these are the environment variables you’ll use in Prasso:

- `WHATSAPP_ENABLED=true`
- `WHATSAPP_API_VERSION=v18.0` (example)
- `WHATSAPP_BASE_URL=https://graph.facebook.com` (recommended)
- `WHATSAPP_PHONE_NUMBER_ID=...`
- `WHATSAPP_BUSINESS_ACCOUNT_ID=...`
- `WHATSAPP_ACCESS_TOKEN=...`

Webhook-related (optional; needed for receipts/inbound):

- `WHATSAPP_WEBHOOK_VERIFY_TOKEN=...`
- `WHATSAPP_APP_SECRET=...` (optional)

Team-level overrides (stored in `msg_team_settings`) are planned for multi-tenant support.

---

## 12) Common pitfalls

- Confusing **Phone Number** vs **Phone Number ID**
- Trying to use templates before they’re approved
- Using short-lived dev tokens in production
- Missing required app URLs (privacy policy, data deletion)
- Webhook endpoint not publicly reachable or not HTTPS
- Poor early-message quality causing account quality degradation

---

## Status

This document is a registration/onboarding guide only.

- It does not implement WhatsApp in code.
- For implementation steps, see `WHATSAPP_IMPLEMENTATION_PLAN.md`.
