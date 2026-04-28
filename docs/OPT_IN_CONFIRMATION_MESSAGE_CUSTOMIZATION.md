# Opt-in Confirmation Message Customization

## Overview

Each site can now customize the SMS confirmation message sent to users when they opt-in for text messaging. This provides site-specific branding and messaging while maintaining TCPA compliance.

## Implementation Details

### Database Migration

**File:** `packages/prasso/messaging/database/migrations/2025_12_28_000001_add_opt_in_confirmation_message_to_msg_team_settings.php`

Adds `opt_in_confirmation_message` field to `msg_team_settings` table:
- Type: TEXT
- Nullable: Yes
- Purpose: Store custom opt-in confirmation message per team/site

### Model Updates

**File:** `packages/prasso/messaging/src/Models/MsgTeamSetting.php`

Added `opt_in_confirmation_message` to the fillable array.

### Controller Updates

**File:** `packages/prasso/messaging/src/Http/Controllers/Api/ConsentController.php`

#### New Validation Logic

1. **Site Registration Check**: Verifies the site is registered for SMS messaging
   - Checks if site exists
   - Validates site has at least one team
   - Confirms team has `msg_team_settings` record

2. **Custom Message Support**: Uses custom message if configured, otherwise falls back to default

3. **Placeholder Replacement**: Supports dynamic placeholders in custom messages:
   - `{business}` - Business/site name
   - `{business_name}` - Same as {business}
   - `{cap}` - Monthly message cap
   - `{monthly_cap}` - Same as {cap}

#### Error Handling

Returns appropriate HTTP status codes:
- `503 Service Unavailable` - Site not registered for SMS
- `422 Unprocessable Entity` - Validation errors

### Admin Interface

**File:** `packages/prasso/messaging/src/Filament/Resources/MsgTeamSettingResource.php`

Added new field in SMS section:
- **Field**: Opt-in Confirmation Message
- **Type**: Textarea (4 rows)
- **Helper Text**: Explains placeholder usage
- **Placeholder**: Shows default message format

## How It Works

### 1. Site Registration Check

When a user submits an opt-in form:

```php
// Get current site from host
$site = BaseController::getClientFromHost();

// Verify site exists
if (!$site) {
    return response()->json([
        'message' => 'Site not found. SMS messaging is not available.',
    ], Response::HTTP_SERVICE_UNAVAILABLE);
}

// Get site's first team
$siteTeam = $site->teams()->first();
if (!$siteTeam) {
    return response()->json([
        'message' => 'Site is not registered for SMS messaging. No team found.',
    ], Response::HTTP_SERVICE_UNAVAILABLE);
}

// Check team has SMS settings
$teamCfg = MsgTeamSetting::query()->where('team_id', $siteTeam->id)->first();
if (!$teamCfg) {
    return response()->json([
        'message' => 'Site is not registered for SMS messaging. Please contact administrator.',
    ], Response::HTTP_SERVICE_UNAVAILABLE);
}
```

### 2. Message Customization

```php
// Use custom message if available
if ($effectiveTeamCfg && !empty($effectiveTeamCfg->opt_in_confirmation_message)) {
    // Replace placeholders
    $confirmation = str_replace([
        '{business}',
        '{cap}',
        '{business_name}',
        '{monthly_cap}'
    ], [
        $business,
        $cap,
        $business,
        $cap
    ], $effectiveTeamCfg->opt_in_confirmation_message);
} else {
    // Default message
    $confirmation = "You're almost done! Reply YES to confirm your $business text notifications (up to {$cap} messages/month). You'll receive appointment reminders, service updates, and occasional offers. Reply STOP to opt out, HELP for help. Msg & data rates may apply.";
}
```

## Setup Instructions

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Configure Team Settings

1. Access admin panel: `/admin` or `/site-admin`
2. Navigate to: Settings → Team Settings
3. Edit the team's settings
4. Scroll to SMS section
5. Fill in "Opt-in Confirmation Message" field (optional)
6. Save

### 3. Default Behavior

If no custom message is configured, the system uses the default message with the site name.

## Message Examples

### Default Message
```
You're almost done! Reply YES to confirm your Faith Baptist Church text notifications (up to 10 messages/month). You'll receive appointment reminders, service updates, and occasional offers. Reply STOP to opt out, HELP for help. Msg & data rates may apply.
```

### Custom Message Example
```
Welcome to {business}! Reply YES to confirm your text message subscription (up to {monthly_cap} msgs/mo). Get service updates and important announcements. Reply STOP to cancel, HELP for info. Standard rates may apply.
```

### Church-Specific Example
```
Thanks for signing up at {business_name}! Reply YES to confirm SMS alerts ({cap} texts/month). Receive service reminders and church news. Reply STOP to unsubscribe, HELP for help. Message & data rates apply.
```

## Placeholders

| Placeholder | Replaced With | Example |
|-------------|--------------|---------|
| `{business}` | Site name | "Faith Baptist Church" |
| `{business_name}` | Site name (same as above) | "Faith Baptist Church" |
| `{cap}` | Monthly message cap | "10" |
| `{monthly_cap}` | Monthly message cap (same as above) | "10" |

## Security & Compliance

- **TCPA Compliant**: Maintains double opt-in requirement
- **Consent Logging**: All consent events are logged with timestamps and IP addresses
- **Phone Hashing**: Phone numbers are hashed in database
- **STOP/HELP**: Required unsubscribe and help instructions included
- **Rate Limiting**: Monthly message cap disclosed to users

## Error Responses

### Site Not Registered
```json
{
    "message": "Site is not registered for SMS messaging. Please contact administrator."
}
```

### No Team Found
```json
{
    "message": "Site is not registered for SMS messaging. No team found."
}
```

### Site Not Found
```json
{
    "message": "Site not found. SMS messaging is not available."
}
```

## Testing

### 1. Unregistered Site
1. Create a new site without team settings
2. Submit opt-in form
3. Verify 503 error response

### 2. Custom Message
1. Configure custom message in team settings
2. Submit opt-in form
3. Verify custom message is sent with placeholders replaced

### 3. Default Message
1. Ensure no custom message is configured
2. Submit opt-in form
3. Verify default message is sent with site name

## Troubleshooting

### Issue: "Site is not registered for SMS messaging"
**Cause**: No `msg_team_settings` record exists for the site's team
**Solution**: Create team settings record via admin panel

### Issue: Custom message not working
**Cause**: Placeholders not properly formatted or field not saved
**Solution**: Verify placeholder syntax and check team settings were saved

### Issue: Site name not appearing
**Cause**: Site not found from host or site has no name
**Solution**: Verify site configuration and DNS/host setup

## Benefits

1. **Site-Specific Branding**: Each church can customize messaging to match their voice
2. **TCPA Compliance**: Maintains all legal requirements for SMS marketing
3. **Flexible Messaging**: Placeholders allow dynamic content insertion
4. **Fallback Support**: Default message ensures functionality even without customization
5. **Security**: Prevents SMS sending from unregistered sites

## Future Enhancements

- Multi-language support
- Message templates for different types of opt-ins
- A/B testing capabilities
- Analytics on message effectiveness
- Scheduled message delivery
