<?php

namespace Prasso\Messaging\Tests;

use Prasso\Messaging\Models\MsgDelivery;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgInboundMessage;
use Prasso\Messaging\Models\MsgTeamSetting;

class WhatsAppWebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        config()->set('messaging.whatsapp.webhook_verify_token', 'verify_token');
        config()->set('messaging.whatsapp.app_secret', null);
    }

    /** @test */
    public function verify_returns_challenge_when_token_matches(): void
    {
        $res = $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify_token&hub.challenge=abc123');
        $res->assertStatus(200);
        $this->assertSame('abc123', $res->getContent());
    }

    /** @test */
    public function verify_returns_403_when_token_does_not_match(): void
    {
        $res = $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123');
        $res->assertStatus(403);
    }

    /** @test */
    public function status_webhook_updates_delivery_to_delivered(): void
    {
        $delivery = MsgDelivery::create([
            'team_id' => 10,
            'msg_message_id' => 1,
            'recipient_type' => 'guest',
            'recipient_id' => 1,
            'channel' => 'whatsapp',
            'status' => 'sent',
            'provider_message_id' => 'wamid.123',
            'metadata' => [],
            'sent_at' => now(),
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.123',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];

        $res = $this->postJson('/api/whatsapp/webhook', $payload);
        $res->assertStatus(200);

        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }

    /** @test */
    public function inbound_message_is_persisted_and_links_guest_by_phone_hash_and_delivery_by_context_id(): void
    {
        MsgTeamSetting::create([
            'team_id' => 77,
            'whatsapp_enabled' => true,
            'whatsapp_phone_number_id' => 'pnid_777',
        ]);

        $guest = MsgGuest::create([
            'team_id' => 77,
            'user_id' => 1,
            'name' => 'WA Guest',
            'email' => 'wa@example.test',
            'phone' => '+1 (555) 222-3333',
            'is_subscribed' => true,
        ]);

        $delivery = MsgDelivery::create([
            'team_id' => 77,
            'msg_message_id' => 1,
            'recipient_type' => 'guest',
            'recipient_id' => $guest->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
            'provider_message_id' => 'wamid.orig',
            'metadata' => [],
            'sent_at' => now(),
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'pnid_777',
                            'display_phone_number' => '+15550001111',
                        ],
                        'messages' => [[
                            'from' => '15552223333',
                            'id' => 'wamid.inbound1',
                            'timestamp' => (string) now()->timestamp,
                            'text' => ['body' => 'Hello'],
                            'context' => ['id' => 'wamid.orig'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $res = $this->postJson('/api/whatsapp/webhook', $payload);
        $res->assertStatus(200);

        $inbound = MsgInboundMessage::where('provider_message_id', 'wamid.inbound1')->first();
        $this->assertNotNull($inbound);
        $this->assertSame(77, (int) $inbound->team_id);
        $this->assertSame($guest->id, (int) $inbound->msg_guest_id);
        $this->assertSame($delivery->id, (int) $inbound->msg_delivery_id);
        $this->assertSame('15552223333', $inbound->from);
        $this->assertSame('Hello', $inbound->body);
    }
}
