# Revised A2P 10DLC Campaign Registration for faxt development

## Campaign Information

### Description
faxt development is a custom development and SaaS provider that offers compliant business-to-customer SMS messaging services. This campaign supports multiple verified clients across industries who use our platform to send compliant text messages with proper consent management.

**Use Cases Include:**
- **Notifications & Reminders**: Appointment confirmations, event reminders, service updates, account alerts
- **Customer Service**: Order confirmations, delivery notifications, support follow-ups
- **Two-Way Communication**: Customer replies with keywords (STOP, HELP, CONFIRM)
- **Opt-in Promotional Content**: Special offers and event promotions (only for explicitly opted-in users)

**Compliance Framework:**
- All end-users must provide explicit written consent through verified web forms
- Every message includes clear opt-out instructions
- Automatic processing of standard opt-out keywords (STOP, UNSUBSCRIBE, etc.)
- Message frequency: Typically 1-4 messages per month per subscriber
- No purchased lists or third-party data sources are used

**Privacy & Data Protection:**
- All subscriber data is protected under our comprehensive privacy policy available at [YOUR_DOMAIN]/privacy-policy
- Subscribers can request data deletion by contacting privacy@faxtdevelopment.com
- Opt-out requests are processed immediately and permanently

### Sending messages with embedded links?
Yes - Messages may contain shortened links for appointment scheduling, order tracking, and event information

### Sending messages with embedded phone numbers?
No

### Sending messages with age-gated content?
No

### Sending messages with content related to direct lending or other loan arrangements?
No

## Message Samples

Note: The platform auto-appends a compliance footer to outbound SMS (business ID, "Reply STOP to unsubscribe", disclaimer). To avoid duplication, sample contents below omit explicit STOP instructions.

### Message Sample #1
**Type**: Appointment Reminder
**Content**: "Hi John, this is ABC Dental. Your appointment is scheduled for March 15 at 2:00 PM. Reply CONFIRM to confirm or call (555) 123-4567."

### Message Sample #2
**Type**: Order Notification
**Content**: "Your order #12345 from XYZ Store has shipped! Track your package: bit.ly/track12345. Expected delivery: March 20."

### Message Sample #3
**Type**: Event Reminder
**Content**: "Reminder: Community fundraiser tomorrow at 6 PM at City Hall. More info: bit.ly/event123."

### Message Sample #4
**Type**: Account Alert
**Content**: "ABC Bank Alert: Your account balance is low ($25.50). Please add funds to avoid fees. Log in at bit.ly/login123."

### Message Sample #5
**Type**: Survey Request
**Content**: "Thank you for visiting our store! Please rate your experience 1-5 by replying with a number. Your feedback helps us improve."

## End User Consent

### How do end-users consent to receive messages?

**Primary Method - Web Form Opt-in:**
End-users provide explicit consent through verified web forms hosted on client websites. The opt-in process includes:

1. **Clear Consent Language**: "By entering your phone number and clicking 'Subscribe,' you agree to receive text messages from [Business Name] including appointment reminders, updates, and occasional promotional offers. Message and data rates may apply."

2. **Required Fields**: 
   - Phone number (required)
   - Checkbox confirmation (required): "I agree to receive text messages"
   - Email address for confirmation

3. **Double Opt-in Verification**: After form submission, users receive a confirmation text: "Welcome to [Business Name] text alerts! Reply YES to confirm your subscription. Reply STOP anytime to unsubscribe. Help: HELP"

4. **Confirmation Required**: Users must reply "YES" to activate their subscription

**Example Opt-in Form URL**: https://[CLIENT-DOMAIN]/sms-signup
**Example Privacy Policy URL**: https://[CLIENT-DOMAIN]/privacy-policy

### Opt-in Message
"Welcome to [Business Name] text notifications! Reply YES to confirm your subscription and start receiving updates. Reply STOP anytime to unsubscribe. Msg&data rates apply. Help: HELP"

### Opt-in Keywords
- YES
- CONFIRM
- JOIN
- START

## Opt-out Configuration

### Opt-out Message
"You have been successfully unsubscribed from [Business Name] text messages. You will no longer receive SMS from us. To resubscribe, visit [website] or text START."

### Opt-out Keywords
- STOP
- UNSUBSCRIBE
- CANCEL
- END
- QUIT
- OPTOUT

### Help Message
"You are subscribed to [Business Name] text alerts for appointment reminders and updates. Msg&data rates may apply. Reply STOP to unsubscribe anytime. Questions? Email support@[business-domain].com or call [business phone]. Privacy: [business-domain]/privacy"

### Help Keywords
- HELP
- INFO
- SUPPORT
- ?

## Compliance Documentation

### Required Legal Pages
Each client must maintain the following pages on their domain:
- Privacy Policy (detailing SMS data collection and usage)
- Terms of Service (including SMS terms)
- Opt-out instructions page

### Contact Information
- **Business Email**: compliance@faxtdevelopment.com
- **Business Phone**: (555) 123-4567
- **Privacy Email**: privacy@faxtdevelopment.com
- **Website**: https://faxtdevelopment.com

### Data Retention
- Opt-out requests are honored immediately and permanently
- Subscriber data is retained only as long as necessary for service provision
- Users can request data deletion by contacting privacy@faxtdevelopment.com

## Additional Compliance Notes

1. **No Purchased Lists**: We do not use any purchased, rented, or third-party contact lists
2. **Client Verification**: All clients undergo verification before campaign activation
3. **Message Monitoring**: All campaigns are monitored for compliance with TCPA, CAN-SPAM, and carrier guidelines
4. **Regular Audits**: Consent mechanisms and opt-out processing are audited monthly
5. **Staff Training**: All staff are trained on SMS compliance requirements and best practices