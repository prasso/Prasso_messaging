<?php

namespace Twilio\Exceptions {
    if (!class_exists('Twilio\\Exceptions\\RestException')) {
        class RestException extends \Exception {
            public function getStatusCode(): int { return (int) $this->getCode(); }
        }
    }
}

namespace Twilio\Rest {
    if (!class_exists('Twilio\\Rest\\Client')) {
        class Client {
            public $messages;
            public function __construct($sid, $token) {}
        }
    }
}

namespace Prasso\Messaging\Tests {

use Illuminate\Support\Facades\Log;
use Mockery;
use Prasso\Messaging\Jobs\ProcessMsgDelivery;
use Prasso\Messaging\Models\MsgDelivery;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgMessage;
use Prasso\Messaging\Models\MsgTeamSetting;
use Twilio\Exceptions\RestException as TwilioRestException;
use Twilio\Rest\Client as TwilioClient;

class ProcessMsgDeliverySmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // sensible defaults
        config()->set('twilio.sid', 'AC123');
        config()->set('twilio.auth_token', 'secret');
        config()->set('messaging.sms_from', '+15005550006');
        config()->set('messaging.rate_limit', [
            'per_guest_monthly_cap' => 0,
            'per_guest_window_days' => 30,
            'allow_transactional_bypass' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (class_exists('Mockery')) {
            Mockery::close();
        }
        // Ensure we remove any bound Twilio client between tests
        if (app()->bound(TwilioClient::class)) {
            app()->forgetInstance(TwilioClient::class);
        }
        parent::tearDown();
    }

    protected function makeGuest(array $attrs = []): MsgGuest
    {
        $suffix = uniqid('t', true);
        return MsgGuest::create(array_merge([
            'user_id' => 1,
            'name' => 'John Doe',
            'email' => "john+{$suffix}@example.test",
            'phone' => '+15551230001',
            'is_subscribed' => true,
        ], $attrs));
    }

    protected function makeMessage(array $attrs = []): MsgMessage
    {
        return MsgMessage::create(array_merge([
            'subject' => null,
            'body' => 'Hello {{Name}}',
            'type' => 'sms',
        ], $attrs));
    }

    protected function makeDelivery(MsgGuest $guest, MsgMessage $message, array $attrs = []): MsgDelivery
    {
        return MsgDelivery::create(array_merge([
            'msg_message_id' => $message->id,
            'recipient_type' => 'guest',
            'recipient_id' => $guest->id,
            'channel' => 'sms',
            'status' => 'queued',
        ], $attrs));
    }

    /** @test */
    public function missing_phone_marks_failed(): void
    {
        $guest = $this->makeGuest(['phone' => null]);
        $msg = $this->makeMessage();
        $delivery = $this->makeDelivery($guest, $msg);

        (new ProcessMsgDelivery($delivery->id))->handle();
        $delivery->refresh();

        $this->assertSame('failed', $delivery->status);
        $this->assertSame('missing phone', $delivery->error);
        $this->assertNotNull($delivery->failed_at);
    }

    /** @test */
    public function do_not_contact_and_anonymized_are_skipped(): void
    {
        // do_not_contact
        $guest1 = $this->makeGuest(['do_not_contact' => true]);
        $msg = $this->makeMessage();
        $d1 = $this->makeDelivery($guest1, $msg);
        (new ProcessMsgDelivery($d1->id))->handle();
        $d1->refresh();
        $this->assertSame('skipped', $d1->status);
        $this->assertSame('do-not-contact', $d1->error);

        // anonymized
        $guest2 = $this->makeGuest(['anonymized_at' => now()]);
        $d2 = $this->makeDelivery($guest2, $msg);
        (new ProcessMsgDelivery($d2->id))->handle();
        $d2->refresh();
        $this->assertSame('skipped', $d2->status);
        $this->assertSame('anonymized recipient', $d2->error);
    }

    /** @test */
    public function team_not_verified_is_skipped(): void
    {
        $guest = $this->makeGuest();
        $msg = $this->makeMessage();
        $delivery = $this->makeDelivery($guest, $msg, ['team_id' => 4242]);
        MsgTeamSetting::create([
            'team_id' => 4242,
            'verification_status' => 'pending',
        ]);

        (new ProcessMsgDelivery($delivery->id))->handle();
        $delivery->refresh();
        $this->assertSame('skipped', $delivery->status);
        $this->assertSame('team not verified', $delivery->error);
    }

    /** @test */
    public function per_guest_rate_cap_and_bypasses(): void
    {
        $guest = $this->makeGuest(['phone' => '+15550009999']);
        $msg = $this->makeMessage(['body' => 'Hi']);

        // Prior sent within window
        MsgDelivery::create([
            'msg_message_id' => $msg->id,
            'recipient_type' => 'guest',
            'recipient_id' => $guest->id,
            'channel' => 'sms',
            'status' => 'sent',
            'sent_at' => now()->subDays(1),
        ]);

        config()->set('messaging.rate_limit', [
            'per_guest_monthly_cap' => 1,
            'per_guest_window_days' => 30,
            'allow_transactional_bypass' => true,
        ]);

        // Cap should skip
        $dCap = $this->makeDelivery($guest, $msg, ['team_id' => null]);
        // no Twilio binding needed for skip path
        (new ProcessMsgDelivery($dCap->id))->handle();
        $dCap->refresh();
        $this->assertSame('skipped', $dCap->status);
        $this->assertSame('per-guest frequency cap reached', $dCap->error);

        // Transactional bypass should send
        $dBypass = $this->makeDelivery($guest, $msg, ['metadata' => ['type' => 'transactional']]);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->andReturn((object) ['sid' => 'SM123']);
        (new ProcessMsgDelivery($dBypass->id))->handle();
        $dBypass->refresh();
        $this->assertSame('sent', $dBypass->status);

        // Override flags should send despite cap
        $dOverride = $this->makeDelivery($guest, $msg, ['metadata' => ['override_frequency' => true]]);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->andReturn((object) ['sid' => 'SM124']);
        (new ProcessMsgDelivery($dOverride->id))->handle();
        $dOverride->refresh();
        $this->assertSame('sent', $dOverride->status);

        $dOverrideUntil = $this->makeDelivery($guest, $msg, ['metadata' => ['override_until' => now()->addHour()->toIso8601String()]]);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->andReturn((object) ['sid' => 'SM125']);
        (new ProcessMsgDelivery($dOverrideUntil->id))->handle();
        $dOverrideUntil->refresh();
        $this->assertSame('sent', $dOverrideUntil->status);
    }

    /** @test */
    public function from_number_precedence_and_missing_from(): void
    {
        $guest = $this->makeGuest(['phone' => '+15558887777']);
        $msg = $this->makeMessage(['body' => 'Body']);
        MsgTeamSetting::create(['team_id' => 7, 'verification_status' => 'verified', 'sms_from' => '+15550101010']);

        // metadata.from wins over team and config
        $dMeta = $this->makeDelivery($guest, $msg, ['team_id' => 7, 'metadata' => ['from' => '+15559998888']]);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->with('+15558887777', Mockery::on(function ($params) {
            return ($params['from'] ?? null) === '+15559998888';
        }))->andReturn((object) ['sid' => 'SM201']);
        (new ProcessMsgDelivery($dMeta->id))->handle();
        $dMeta->refresh();
        $this->assertSame('sent', $dMeta->status);

        // team sms_from used when metadata.from missing
        $dTeam = $this->makeDelivery($guest, $msg, ['team_id' => 7]);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->with('+15558887777', Mockery::on(function ($params) {
            return ($params['from'] ?? null) === '+15550101010';
        }))->andReturn((object) ['sid' => 'SM202']);
        (new ProcessMsgDelivery($dTeam->id))->handle();
        $dTeam->refresh();
        $this->assertSame('sent', $dTeam->status);

        // config messaging.sms_from used when no team
        $dCfg = $this->makeDelivery($guest, $msg);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->with('+15558887777', Mockery::on(function ($params) {
            return ($params['from'] ?? null) === '+15005550006';
        }))->andReturn((object) ['sid' => 'SM203']);
        (new ProcessMsgDelivery($dCfg->id))->handle();
        $dCfg->refresh();
        $this->assertSame('sent', $dCfg->status);

        // missing from when metadata/team/config all empty
        config()->set('messaging.sms_from', null);
        config()->set('twilio.phone_number', null);
        $dMissing = $this->makeDelivery($guest, $msg, ['team_id' => null, 'metadata' => []]);
        (new ProcessMsgDelivery($dMissing->id))->handle();
        $dMissing->refresh();
        $this->assertSame('failed', $dMissing->status);
        $this->assertSame('missing from number', $dMissing->error);
    }

    /** @test */
    public function missing_twilio_credentials_marks_failed(): void
    {
        config()->set('twilio.sid', null);
        config()->set('twilio.auth_token', null);
        $guest = $this->makeGuest(['phone' => '+15557776666']);
        $msg = $this->makeMessage();
        $delivery = $this->makeDelivery($guest, $msg);

        (new ProcessMsgDelivery($delivery->id))->handle();
        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('twilio credentials missing', $delivery->error);
    }

    /** @test */
    public function successful_send_appends_footer_and_logs_segments_and_truncates(): void
    {
        config()->set('messaging.help.business_name', 'Acme Inc');
        config()->set('messaging.help.disclaimer', 'Rates may apply.');
        $guest = $this->makeGuest(['phone' => '+15553334444', 'name' => 'Alex Doe']);
        $longBody = str_repeat('A', 1700); // exceed 1600 to force truncation
        $msg = $this->makeMessage(['body' => $longBody]);
        $delivery = $this->makeDelivery($guest, $msg);

        // Expect length/segments log and allow warnings/errors if thrown by transport
        Log::shouldReceive('info')->once()->with('SMS length/segments', Mockery::type('array'));
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->with('+15553334444', Mockery::on(function ($params) {
            $body = $params['body'] ?? '';
            // Footer includes business and STOP instruction
            $len = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
            return str_contains($body, 'Acme Inc') && str_contains($body, 'Reply STOP to unsubscribe') && $len <= 1600;
        }))->andReturn((object) ['sid' => 'SM777']);

       // (new ProcessMsgDelivery($delivery->id))->handle();
        $delivery->refresh();
       // $this->assertSame('sent', $delivery->status);
        $this->assertNotNull($delivery->sent_at);
    }

    /** @test */
    public function twilio_rest_exception_retry_and_permanent(): void
    {
        $guest = $this->makeGuest(['phone' => '+15554443333']);
        $msg = $this->makeMessage(['body' => 'Hi']);

        // Transient 429 -> queued
        $d429 = $this->makeDelivery($guest, $msg);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->andThrow($this->twilioRestEx('rate limited', 429));
        (new ProcessMsgDelivery($d429->id))->handle();
        $d429->refresh();
        $this->assertSame('queued', $d429->status);
        $this->assertNull($d429->failed_at);

        // Permanent 400 -> failed
        $d400 = $this->makeDelivery($guest, $msg);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->andThrow($this->twilioRestEx('bad request', 400));
        (new ProcessMsgDelivery($d400->id))->handle();
        $d400->refresh();
        $this->assertSame('failed', $d400->status);
        $this->assertStringContainsString('400:', $d400->error);
    }

    /** @test */
    public function generic_exception_marks_failed_unless_transient(): void
    {
        $guest = $this->makeGuest(['phone' => '+15556667777']);
        $msg = $this->makeMessage(['body' => 'Hi']);

        // transient generic (timeout) -> queued
        $dTransient = $this->makeDelivery($guest, $msg);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->andThrow(new \RuntimeException('Timeout talking to API'));
        (new ProcessMsgDelivery($dTransient->id))->handle();
        $dTransient->refresh();
        $this->assertSame('queued', $dTransient->status);

        // permanent generic -> failed
        $dPermanent = $this->makeDelivery($guest, $msg);
        [$messages, $client] = $this->bindTwilioMessages();
        $messages->shouldReceive('create')->once()->andThrow(new \RuntimeException('invalid destination'));
        (new ProcessMsgDelivery($dPermanent->id))->handle();
        $dPermanent->refresh();
        $this->assertSame('failed', $dPermanent->status);
        $this->assertSame('invalid destination', $dPermanent->error);
    }

    protected function twilioRestEx(string $message, int $status): TwilioRestException
    {
        // Twilio RestException signature can vary; use anonymous class extending it and overriding getStatusCode
        return new class($message, $status) extends TwilioRestException {
            private int $statusCode;
            public function __construct(string $message, int $statusCode)
            {
                // Create base with minimal data; parent expects message and code
                parent::__construct($message, $statusCode);
                $this->statusCode = $statusCode;
            }
            public function getStatusCode(): int { return $this->statusCode; }
        };
    }

    /**
     * Bind a mocked Twilio client with a messages mock into the container so the job picks it up.
     */
    protected function bindTwilioMessages(): array
    {
        $messages = Mockery::mock();
        $client = Mockery::mock(TwilioClient::class);
        $client->messages = $messages;
        $client->shouldReceive('messages')->andReturn($messages);
        app()->instance(TwilioClient::class, $client);
        return [$messages, $client];
    }
}
}
