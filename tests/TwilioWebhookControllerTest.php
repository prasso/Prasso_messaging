<?php

namespace Prasso\Messaging\Tests;

use Illuminate\Support\Facades\DB;
use Prasso\Messaging\Models\MsgConsentEvent;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgInboundMessage;
use Prasso\Messaging\Models\MsgTeamSetting;

class TwilioWebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure we bypass Twilio signature middleware for ease of testing
        $this->withoutMiddleware();
    }

    /** @test */
    public function join_keyword_confirms_only_with_recent_opt_in_request_and_logs_keyword(): void
    {
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Opt Join',
            'email' => 'join@example.test',
            'phone' => '+1 555 100 2000',
            'is_subscribed' => false,
        ]);

        // No recent request -> should not subscribe
        $resNoReq = $this->post('/webhooks/twilio', [
            'From' => '+15551002000', 'To' => '+15005550006', 'Body' => 'JOIN', 'MessageSid' => 'SM_join1',
        ], ['Accept' => 'text/xml']);
        $resNoReq->assertStatus(200);
        $guest->refresh();
        $this->assertFalse((bool) $guest->is_subscribed);

        // Create recent opt_in_request to satisfy 24h rule
        \Prasso\Messaging\Models\MsgConsentEvent::create([
            'team_id' => $guest->team_id,
            'msg_guest_id' => $guest->id,
            'action' => 'opt_in_request',
            'method' => 'web',
            'source' => 'phpunit',
            'occurred_at' => now(),
            'meta' => ['consent_checkbox' => true],
        ]);

        // Now JOIN should subscribe and log opt_in with keyword
        $res = $this->post('/webhooks/twilio', [
            'From' => '+15551002000', 'To' => '+15005550006', 'Body' => 'JOIN', 'MessageSid' => 'SM_join2',
        ], ['Accept' => 'text/xml']);
        $res->assertStatus(200);
        $guest->refresh();
        $this->assertTrue((bool) $guest->is_subscribed);
        $evt = MsgConsentEvent::where('msg_guest_id', $guest->id)->where('action', 'opt_in')->latest('id')->first();
        $this->assertNotNull($evt);
        $this->assertSame('JOIN', strtoupper($evt->meta['keyword'] ?? ''));
    }

    /** @test */
    public function stop_reply_includes_business_name_and_logs_keyword_meta(): void
    {
        $guest = MsgGuest::create([
            'team_id' => 808,
            'user_id' => 1,
            'name' => 'Stop Me',
            'email' => 'stop@example.test',
            'phone' => '+1 555 300 4000',
            'is_subscribed' => true,
        ]);
        MsgTeamSetting::create(['team_id' => 808, 'help_business_name' => 'Beta Biz']);

        $res = $this->post('/webhooks/twilio', [
            'From' => '+15553004000', 'To' => '+15005550006', 'Body' => 'STOP', 'MessageSid' => 'SM_stopBiz',
        ], ['Accept' => 'text/xml']);

        $res->assertStatus(200);
        $xml = $res->getContent();
        $this->assertStringContainsString('Beta Biz', $xml);

        $evt = MsgConsentEvent::where('msg_guest_id', $guest->id)->where('action', 'opt_out')->latest('id')->first();
        $this->assertNotNull($evt);
        $this->assertSame('STOP', strtoupper($evt->meta['keyword'] ?? ''));
    }

    /** @test */
    public function yes_requires_recent_opt_in_request_within_24h(): void
    {
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Yes Person',
            'email' => 'yes@example.test',
            'phone' => '+1 555 600 7000',
            'is_subscribed' => false,
        ]);

        // Old request beyond 24h should not count
        \Prasso\Messaging\Models\MsgConsentEvent::create([
            'team_id' => $guest->team_id,
            'msg_guest_id' => $guest->id,
            'action' => 'opt_in_request',
            'method' => 'web',
            'source' => 'phpunit',
            'occurred_at' => now()->subDays(2),
            'meta' => ['consent_checkbox' => true],
        ]);

        $resOld = $this->post('/webhooks/twilio', [
            'From' => '+15556007000', 'To' => '+15005550006', 'Body' => 'YES', 'MessageSid' => 'SM_yes_old',
        ], ['Accept' => 'text/xml']);
        $resOld->assertStatus(200);
        $guest->refresh();
        $this->assertFalse((bool) $guest->is_subscribed);

        // Recent request -> YES should subscribe
        \Prasso\Messaging\Models\MsgConsentEvent::create([
            'team_id' => $guest->team_id,
            'msg_guest_id' => $guest->id,
            'action' => 'opt_in_request',
            'method' => 'web',
            'source' => 'phpunit',
            'occurred_at' => now()->subHours(1),
            'meta' => ['consent_checkbox' => true],
        ]);
        $resYes = $this->post('/webhooks/twilio', [
            'From' => '+15556007000', 'To' => '+15005550006', 'Body' => 'YES', 'MessageSid' => 'SM_yes_new',
        ], ['Accept' => 'text/xml']);
        $resYes->assertStatus(200);
        $guest->refresh();
        $this->assertTrue((bool) $guest->is_subscribed);
        $evt = MsgConsentEvent::where('msg_guest_id', $guest->id)->where('action', 'opt_in')->latest('id')->first();
        $this->assertSame('YES', strtoupper($evt->meta['keyword'] ?? ''));
    }

    /** @test */
    public function stop_keyword_updates_guest_to_unsubscribed_and_logs_consent(): void
    {
        // Given a subscribed guest identified by phone_hash
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Hash Path',
            'email' => 'hash@example.test',
            'phone' => '+1 (555) 123-4567',
            'is_subscribed' => true,
        ]);

        // When Twilio webhook posts STOP
        $res = $this->post('/webhooks/twilio', [
            'From' => '+15551234567',
            'To' => '+15005550006',
            'Body' => 'STOP',
            'MessageSid' => 'SM_hashPath',
        ], ['Accept' => 'text/xml']);

        // Then response is TwiML and guest unsubscribed, consent event recorded
        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $guest->refresh();
        $this->assertFalse((bool) $guest->is_subscribed);
        $this->assertEquals(1, MsgConsentEvent::where('msg_guest_id', $guest->id)->where('action', 'opt_out')->count());

        // And inbound message persisted
        $inbound = MsgInboundMessage::first();
        $this->assertNotNull($inbound);
        $this->assertSame($guest->id, $inbound->msg_guest_id);
        $this->assertSame('SM_hashPath', $inbound->provider_message_id);
    }

    /** @test */
    public function legacy_phone_like_lookup_is_used_when_phone_hash_is_missing(): void
    {
        // Create a legacy-style row: clear phone_hash so only LIKE matches
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Legacy Path',
            'email' => 'legacy@example.test',
            'phone' => '555-123-4567',
            'is_subscribed' => true,
        ]);
        DB::table('msg_guests')->where('id', $guest->id)->update(['phone_hash' => null, 'phone' => '+15551234567']);

        // Post STOP from a number that normalizes to 5551234567
        $res = $this->post('/webhooks/twilio', [
            'From' => '(555) 123-4567',
            'To' => '+15005550006',
            'Body' => 'STOP',
            'MessageSid' => 'SM_legacyPath',
        ], ['Accept' => 'text/xml']);

        $res->assertStatus(200);
        $guest->refresh();
        $this->assertFalse((bool) $guest->is_subscribed);
        $this->assertEquals(1, MsgConsentEvent::where('msg_guest_id', $guest->id)->where('action', 'opt_out')->count());

        $inbound = MsgInboundMessage::where('provider_message_id', 'SM_legacyPath')->first();
        $this->assertNotNull($inbound);
        $this->assertSame($guest->id, $inbound->msg_guest_id);
    }

    /** @test */
    public function help_branch_returns_message_with_team_overrides(): void
    {
        // Given guest and team settings with overrides
        $guest = MsgGuest::create([
            'team_id' => 4242,
            'user_id' => 1,
            'name' => 'Team Person',
            'email' => 'team@example.test',
            'phone' => '+1 555 222 3333',
            'is_subscribed' => true,
        ]);
        MsgTeamSetting::create([
            'team_id' => 4242,
            'help_business_name' => 'Acme Widgets',
            'help_purpose' => 'We send important alerts.',
            'help_disclaimer' => 'Rates may apply.',
            'help_contact_phone' => '555-000-1111',
            'help_contact_email' => 'support@example.com',
            'help_contact_website' => 'https://acme.example',
        ]);

        $res = $this->post('/webhooks/twilio', [
            'From' => '+15552223333',
            'To' => '+15005550006',
            'Body' => 'HELP',
            'MessageSid' => 'SM_help',
        ], ['Accept' => 'text/xml']);

        $res->assertStatus(200);
        $xml = $res->getContent();
        $this->assertStringContainsString('Acme Widgets', $xml);
        $this->assertStringContainsString('We send important alerts.', $xml);
        $this->assertStringContainsString('Rates may apply.', $xml);
        $this->assertStringContainsString('555-000-1111', $xml);
        $this->assertStringContainsString('support@example.com', $xml);
        $this->assertStringContainsString('https://acme.example', $xml);
    }

    /** @test */
    public function info_support_question_mark_all_map_to_help_message(): void
    {
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Help Person',
            'email' => 'help@example.test',
            'phone' => '+1 555 444 7777',
            'is_subscribed' => true,
        ]);

        foreach (['INFO', 'SUPPORT', '?'] as $kw) {
            $res = $this->post('/webhooks/twilio', [
                'From' => '+15554447777',
                'To' => '+15005550006',
                'Body' => $kw,
                'MessageSid' => 'SM_'.$kw,
            ], ['Accept' => 'text/xml']);

            $res->assertStatus(200);
            $xml = $res->getContent();
            $this->assertStringContainsString('Reply STOP to unsubscribe', $xml);
        }
    }

    /** @test */
    public function default_branch_returns_generic_acknowledgement(): void
    {
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Generic',
            'email' => 'generic@example.test',
            'phone' => '+1 555 999 0000',
            'is_subscribed' => true,
        ]);

        $res = $this->post('/webhooks/twilio', [
            'From' => '+15559990000',
            'To' => '+15005550006',
            'Body' => 'hello world',
            'MessageSid' => 'SM_default',
        ], ['Accept' => 'text/xml']);

        $res->assertStatus(200);
        $xml = $res->getContent();
        $this->assertStringContainsString('Thank you for your message', $xml);
        $this->assertStringContainsString('Reply HELP for information or STOP to unsubscribe', $xml);
    }

    /** @test */
    public function media_attachments_are_ingested_into_array(): void
    {
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Media',
            'email' => 'media@example.test',
            'phone' => '+1 555 111 2222',
            'is_subscribed' => true,
        ]);

        $res = $this->post('/webhooks/twilio', [
            'From' => '+15551112222',
            'To' => '+15005550006',
            'Body' => 'Here are files',
            'NumMedia' => '2',
            'MediaUrl0' => 'https://example.com/a.jpg',
            'MediaUrl1' => 'https://example.com/b.png',
            'MessageSid' => 'SM_media',
        ], ['Accept' => 'text/xml']);

        $res->assertStatus(200);
        $inbound = MsgInboundMessage::where('provider_message_id', 'SM_media')->first();
        $this->assertNotNull($inbound);
        $this->assertEquals(['https://example.com/a.jpg', 'https://example.com/b.png'], $inbound->media);
    }
}
