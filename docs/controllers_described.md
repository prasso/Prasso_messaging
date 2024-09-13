To cover all the messaging functionality described, here’s a breakdown of the necessary controllers and their associated API endpoints for handling guests, campaigns, engagements, workflows, and messaging. I'll also include the typical actions for each controller and how they fit into the functionality you want.

---

### 1. **Guest Management**
Manages guest creation, conversion tracking, and engagement with guests.

**Controller**: `GuestController`
```php
php artisan make:controller GuestController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously
**API Endpoints**:
```bash
GET    /api/guests                   # List all guests
POST   /api/guests                   # Create a new guest
GET    /api/guests/{id}              # View a single guest
PUT    /api/guests/{id}              # Update guest details
DELETE /api/guests/{id}              # Delete a guest
POST   /api/guests/{id}/convert      # Mark guest as converted
POST   /api/guests/{id}/engage       # Engage guest with a message or campaign
```

**Actions**:
- **index**: List all guests.
- **store**: Add a new guest (e.g., through registration).
- **show**: View details for a specific guest.
- **update**: Edit guest details.
- **destroy**: Delete a guest.
- **convert**: Mark a guest as "converted."
- **engage**: Engage a guest (e.g., send a message or enroll them in a campaign).

---

### 2. **Message Management**
Handles the creation and sending of messages (SMS, email, voice) to guests.

**Controller**: `MessageController`
```php
php artisan make:controller MessageController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously
**API Endpoints**:
```bash
GET    /api/messages                # List all messages
POST   /api/messages                # Create a new message
GET    /api/messages/{id}           # View a single message
PUT    /api/messages/{id}           # Update message details
DELETE /api/messages/{id}           # Delete a message
POST   /api/messages/send           # Send a message to selected guests
```

**Actions**:
- **index**: List all messages.
- **store**: Create a new message (text, email, voice).
- **show**: View details for a specific message.
- **update**: Edit an existing message.
- **destroy**: Delete a message.
- **send**: Send a message to one or more guests.

---

### 3. **Campaign Management**
Manages campaigns, including scheduling and sending campaign messages.

**Controller**: `CampaignController`
```php
php artisan make:controller CampaignController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously

**API Endpoints**:
```bash
GET    /api/campaigns                 # List all campaigns
POST   /api/campaigns                 # Create a new campaign
GET    /api/campaigns/{id}            # View a single campaign
PUT    /api/campaigns/{id}            # Update campaign details
DELETE /api/campaigns/{id}            # Delete a campaign
POST   /api/campaigns/{id}/messages   # Add a message to a campaign
POST   /api/campaigns/{id}/launch     # Launch the campaign (send all scheduled messages)
```

**Actions**:
- **index**: List all campaigns.
- **store**: Create a new campaign (e.g., Christmas, Easter).
- **show**: View details for a specific campaign.
- **update**: Update campaign details.
- **destroy**: Delete a campaign.
- **messages**: Add messages to a campaign.
- **launch**: Start the campaign and begin sending out messages.

---

### 4. **Engagement Management**
Handles contests, polls, surveys, and similar engagement activities.

**Controller**: `EngagementController`
```php
php artisan make:controller EngagementController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously

**API Endpoints**:
```bash
GET    /api/engagements               # List all engagements (contests, polls, surveys)
POST   /api/engagements               # Create a new engagement
GET    /api/engagements/{id}          # View a specific engagement
PUT    /api/engagements/{id}          # Update an engagement
DELETE /api/engagements/{id}          # Delete an engagement
POST   /api/engagements/{id}/responses # Record a guest's response
```

**Actions**:
- **index**: List all engagement activities.
- **store**: Create a new engagement (poll, contest, etc.).
- **show**: View details for a specific engagement.
- **update**: Update engagement details.
- **destroy**: Delete an engagement.
- **responses**: Submit a guest’s response (e.g., survey answer, poll choice, contest entry).

---

### 5. **Workflow Management**
Manages automated workflows that convert guests, trigger reminders, and more.

**Controller**: `WorkflowController`
```php
php artisan make:controller WorkflowController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously

**API Endpoints**:
```bash
GET    /api/workflows                 # List all workflows
POST   /api/workflows                 # Create a new workflow
GET    /api/workflows/{id}            # View a single workflow
PUT    /api/workflows/{id}            # Update workflow details
DELETE /api/workflows/{id}            # Delete a workflow
POST   /api/workflows/{id}/steps      # Add steps to a workflow
POST   /api/workflows/{id}/start      # Start the workflow
```

**Actions**:
- **index**: List all workflows.
- **store**: Create a new workflow (e.g., guest follow-up, event reminders).
- **show**: View details of a specific workflow.
- **update**: Update a workflow.
- **destroy**: Delete a workflow.
- **steps**: Add steps to a workflow (e.g., a message to send after 1 day).
- **start**: Start the workflow and begin executing the steps (e.g., send automated messages).

---

### 6. **Guest Message Management**
Tracks which messages were sent to guests.

**Controller**: `GuestMessageController`
```php
php artisan make:controller GuestMessageController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously
```

**API Endpoints**:
```bash
GET    /api/guest-messages            # List all messages sent to guests
POST   /api/guest-messages            # Log a new guest message (sent)
GET    /api/guest-messages/{id}       # View a specific guest message
PUT    /api/guest-messages/{id}       # Update guest message log (e.g., mark as sent)
DELETE /api/guest-messages/{id}       # Delete a guest message log
```

**Actions**:
- **index**: List all guest message logs.
- **store**: Log a new message sent to a guest.
- **show**: View details of a guest message log.
- **update**: Update a message log (e.g., mark as sent).
- **destroy**: Delete a message log.

---

### 7. **Emergency Alerts**
For sending emergency messages or urgent news updates.

**Controller**: `AlertController`
```php
php artisan make:controller AlertController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously

**API Endpoints**:
```bash
POST   /api/alerts/emergency           # Send emergency alert (SMS, email, voice)
POST   /api/alerts/news                # Send news update
```

**Actions**:
- **emergency**: Trigger an emergency message to all or selected guests.
- **news**: Send a news update to guests.

---

### 8. **Event Reminders**
Handles event-related messages, such as automated reminders.

**Controller**: `EventController`
```php
php artisan make:controller EventController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously
```

**API Endpoints**:
```bash
GET    /api/events                    # List all events
POST   /api/events                    # Create a new event
GET    /api/events/{id}               # View a specific event
PUT    /api/events/{id}               # Update event details
DELETE /api/events/{id}               # Delete an event
POST   /api/events/{id}/reminders     # Schedule event reminders
```

**Actions**:
- **index**: List all events.
- **store**: Create a new event (e.g., seminar, worship service).
- **show**: View details of a specific event.
- **update**: Update an event.
- **destroy**: Delete an event.
- **reminders**: Schedule and send event reminders to guests.

---

### 9. **Voice Broadcasts**
Handles the creation and sending of automated voice messages.

**Controller**: `VoiceBroadcastController`
```php
php artisan make:controller VoiceBroadcastController --api
```
## give me the route definitions and the controller code for these endpoints, Include swagger @OA details as given previously

**API Endpoints**:
```bash
POST   /api/voice-broadcasts/send      # Send voice broadcast to guests
```

**Actions**:
- **send**: Trigger an automated voice broadcast using a service like Twilio to selected guests.

---

### Summary of Controllers and API Endpoints:

- **`GuestController`**: `/api/guests`
- **`MessageController`**: `/api/messages`
- **`CampaignController`**: `/api/campaigns`
- **`EngagementController`**: `/api/engagements`
- **`WorkflowController`**: `/api/workflows`
- **`GuestMessageController`**: `/api/guest-messages`
- **`AlertController`**: `/api/alerts`
- **`EventController`**: `/api/events`
- **`VoiceBroadcastController`**: `/api/voice-broadcasts`

Each controller encapsulates the necessary logic for managing the respective part of your messaging system (guests, campaigns, engagements, workflows, etc.), and the API endpoints will serve the web or mobile clients for managing and delivering these messages