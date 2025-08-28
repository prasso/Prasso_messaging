<?php

namespace Prasso\Messaging\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Prasso\Messaging\Http\Controllers\Api\TwilioWebhookController;
use Prasso\Messaging\Jobs\ProcessMsgDelivery;
use Prasso\Messaging\Models\MsgDelivery;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgMessage;

class MessagingSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_delivery_skipped_when_guest_pending_confirmation()
    {
        // Arrange: create a pending (unsubscribed) guest
        $guest = MsgGuest::create([
            'name' => 'Pending Person',
            'phone' => '+15555550123',
            'is_subscribed' => false,
        ]);

        $message = MsgMessage::create([
            'subject' => 'Hello',
            'body' => 'Hi there',
            'type' => 'sms',
        ]);

        $delivery = MsgDelivery::create([
            'msg_message_id' => $message->id,
            'recipient_type' => 'guest',
            'recipient_id' => $guest->id,
            'channel' => 'sms',
            'status' => 'queued',
            'metadata' => [],
        ]);

        // Act: run the job handler directly
        (new ProcessMsgDelivery($delivery->id))->handle();

        // Refresh model and assert it was skipped due to pending/unsubscribed
        $delivery->refresh();
        $this->assertSame('skipped', $delivery->status);
        $this->assertSame('pending or unsubscribed recipient', $delivery->error);
        $this->assertNotNull($delivery->failed_at);
    }

    public function test_yes_keyword_confirms_subscription()
    {
        // Arrange: create guest pending
        $guest = MsgGuest::create([
            'phone' => '+15555550124',
            'is_subscribed' => false,
        ]);

        // Build a fake Twilio inbound webhook request with YES
        $request = Request::create('/twilio/webhook', 'POST', [
            'From' => '+15555550124',
            'To' => '+15005550006',
            'Body' => 'YES',
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'NumMedia' => '0',
        ]);

        // Act: handle incoming message
        $controller = new TwilioWebhookController();
        $response = $controller->handleIncomingMessage($request);

        // Assert: subscription flipped to true
        $guest->refresh();
        $this->assertTrue((bool) $guest->is_subscribed);

        // And the response is TwiML
        $this->assertSame('text/xml; charset=UTF-8', $response->headers->get('Content-Type'));
    }
}
