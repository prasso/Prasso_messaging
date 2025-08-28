<?php

namespace Prasso\Messaging\Tests;

use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgConsentEvent;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgTeamSetting;
use Prasso\Messaging\Services\SmsService;

class WebOptInTest extends TestCase
{
    protected function tearDown(): void
    {
        // Ensure Mockery expectations are verified
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        parent::tearDown();
    }

    /** @test */
    public function happy_path_creates_guest_event_and_sends_sms(): void
    {
        $mock = \Mockery::mock(SmsService::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function ($to, $body, $teamId) {
                $this->assertNotEmpty($to);
                $this->assertStringContainsString('Reply YES', $body);
                $this->assertStringContainsString('STOP', $body);
                $this->assertStringContainsString('HELP', $body);
                return true;
            });
        $this->app->instance(SmsService::class, $mock);

        $payload = [
            'phone' => '(555) 123-4567',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'checkbox' => true,
            'source_url' => 'https://example.com/consent',
            'ip' => '203.0.113.5',
            'ua' => 'Mozilla/5.0',
        ];

        $res = $this->postJson('/api/consents/opt-in-web', $payload);
        $res->assertStatus(202);

        $guest = MsgGuest::first();
        $this->assertNotNull($guest);
        $this->assertFalse($guest->is_subscribed);
        $this->assertEquals('Jane Doe', $guest->name);
        $this->assertEquals('jane@example.com', $guest->email);

        $event = MsgConsentEvent::first();
        $this->assertNotNull($event);
        $this->assertEquals('opt_in_request', $event->action);
        $this->assertEquals('web', $event->method);
        $this->assertEquals('203.0.113.5', $event->ip);
        $this->assertEquals('Mozilla/5.0', $event->user_agent);
        $this->assertEquals('https://example.com/consent', $event->source);
        $this->assertTrue((bool)($event->meta['consent_checkbox'] ?? false));
    }

    /** @test */
    public function requires_checkbox(): void
    {
        $res = $this->postJson('/api/consents/opt-in-web', [
            'phone' => '5551234567',
            'name' => 'A',
            'email' => 'a@example.com',
        ]);

        $res->assertStatus(422);
        $this->assertEquals(0, MsgGuest::count());
        $this->assertEquals(0, MsgConsentEvent::count());
    }

    /** @test */
    public function validates_phone_format(): void
    {
        $res = $this->postJson('/api/consents/opt-in-web', [
            'phone' => '12345',
            'checkbox' => true,
        ]);

        $res->assertStatus(422);
        $this->assertEquals(0, MsgGuest::count());
        $this->assertEquals(0, MsgConsentEvent::count());
    }

    /** @test */
    public function accepts_legacy_consent_checkbox(): void
    {
        $mock = \Mockery::mock(SmsService::class);
        $mock->shouldReceive('send')->once();
        $this->app->instance(SmsService::class, $mock);

        $res = $this->postJson('/api/consents/opt-in-web', [
            'phone' => '+1 555 123 4567',
            'consent_checkbox' => 'on',
        ]);

        $res->assertStatus(202);
        $this->assertEquals(1, MsgConsentEvent::count());
        $this->assertEquals(1, MsgGuest::count());
    }

    /** @test */
    public function team_scoped_lookup_and_business_name(): void
    {
        MsgTeamSetting::query()->create([
            'team_id' => 42,
            'sms_from' => '+15005550006',
            'help_business_name' => 'Acme Co',
        ]);

        $mock = \Mockery::mock(SmsService::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function ($to, $body, $teamId) {
                $this->assertSame(42, $teamId);
                $this->assertStringContainsString('Acme Co', $body);
                return true;
            });
        $this->app->instance(SmsService::class, $mock);

        $this->postJson('/api/consents/opt-in-web', [
            'team_id' => 42,
            'phone' => '5551234567',
            'checkbox' => true,
        ])->assertStatus(202);

        $this->assertEquals(1, MsgGuest::where('team_id', 42)->count());
    }

    /** @test */
    public function sms_failure_still_returns_202_and_logs(): void
    {
        Log::spy();

        $mock = \Mockery::mock(SmsService::class);
        $mock->shouldReceive('send')->andThrow(new \RuntimeException('fail'));
        $this->app->instance(SmsService::class, $mock);

        $res = $this->postJson('/api/consents/opt-in-web', [
            'phone' => '5551234567',
            'checkbox' => true,
        ]);

        $res->assertStatus(202);
        $this->assertEquals(1, MsgConsentEvent::count());
        Log::shouldHaveReceived('error')->once();
    }

    /** @test */
    public function duplicate_submissions_update_same_guest_and_create_events(): void
    {
        $mock = \Mockery::mock(SmsService::class);
        $mock->shouldReceive('send')->twice();
        $this->app->instance(SmsService::class, $mock);

        $this->post('/api/consents/opt-in-web', [
            'phone' => '(555) 123-4567',
            'name' => 'Duo Test',
            'email' => 'duo@example.com',
            'checkbox' => true,
        ], ['Accept' => 'application/json'])->assertStatus(202);

        $this->post('/api/consents/opt-in-web', [
            'phone' => '+1 555 123 4567',
            'name' => 'Duo Test',
            'email' => 'duo@example.com',
            'checkbox' => true,
        ], ['Accept' => 'application/json'])->assertStatus(202);

        $this->assertEquals(1, MsgGuest::count());
        $this->assertEquals(2, MsgConsentEvent::count());
        $this->assertFalse(MsgGuest::first()->is_subscribed);
    }

    /** @test */
    public function falls_back_to_request_headers_when_ip_ua_source_url_missing(): void
    {
        $mock = \Mockery::mock(SmsService::class);
        $mock->shouldReceive('send')->once();
        $this->app->instance(SmsService::class, $mock);

        $res = $this
            ->withHeader('Referer', 'https://ref.example')
            ->withHeader('User-Agent', 'UA/1.0')
            ->postJson('/api/consents/opt-in-web', [
                'phone' => '5551234567',
                'checkbox' => true,
            ]);

        $res->assertStatus(202);

        $event = MsgConsentEvent::first();
        $this->assertNotNull($event);
        $this->assertEquals('https://ref.example', $event->source);
        $this->assertEquals('UA/1.0', $event->user_agent);
        $this->assertNotEmpty($event->ip);
    }
}
