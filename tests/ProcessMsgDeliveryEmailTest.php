<?php

namespace Prasso\Messaging\Tests;

use Illuminate\Support\Facades\Mail;
use Prasso\Messaging\Jobs\ProcessMsgDelivery;
use Prasso\Messaging\Models\MsgDelivery;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgMessage;

class ProcessMsgDeliveryEmailTest extends TestCase
{
    protected function tearDown(): void
    {
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        parent::tearDown();
    }

    /** @test */
    public function email_success_marks_delivery_sent_and_applies_tokens(): void
    {
        // Arrange guest and message
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Jane Smith',
            'email' => 'jane@example.test',
            'phone' => '+15550001111',
            'is_subscribed' => true,
        ]);
        $message = MsgMessage::create([
            'subject' => 'Hello {{FirstName}}',
            'body' => 'Hi {{Name}}, welcome!',
            'type' => 'email',
        ]);
        $delivery = MsgDelivery::create([
            'msg_message_id' => $message->id,
            'recipient_type' => 'guest',
            'recipient_id' => $guest->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);

        // Mock Mail::raw to capture subject and to
        $captured = ['to' => null, 'subject' => null, 'body' => null];
        Mail::shouldReceive('raw')->once()->andReturnUsing(function ($body, $closure) use (&$captured) {
            $captured['body'] = $body;
            $mailer = new class($captured) {
                public array $captured;
                public function __construct(& $captured) { $this->captured = & $captured; }
                public function to($email) { $this->captured['to'] = $email; return $this; }
                public function subject($subject) { $this->captured['subject'] = $subject; return $this; }
            };
            $closure($mailer);
        });

        // Act
        (new ProcessMsgDelivery($delivery->id))->handle();

        // Assert
        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertEquals('jane@example.test', $captured['to']);
        $this->assertEquals('Hello Jane', $captured['subject']);
        $this->assertStringContainsString('Hi Jane Smith, welcome!', $captured['body']);
    }

    /** @test */
    public function missing_email_marks_failed_with_message(): void
    {
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'No Email',
            'email' => '',
            'phone' => '+15550002222',
            'is_subscribed' => true,
        ]);
        $message = MsgMessage::create([
            'subject' => 'Test',
            'body' => 'Body',
            'type' => 'email',
        ]);
        $delivery = MsgDelivery::create([
            'msg_message_id' => $message->id,
            'recipient_type' => 'guest',
            'recipient_id' => $guest->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);

        // Ensure no mail will be actually sent
        Mail::shouldReceive('raw')->never();

        (new ProcessMsgDelivery($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('missing email', $delivery->error);
        $this->assertNotNull($delivery->failed_at);
    }

    /** @test */
    public function transient_failure_keeps_delivery_queued_for_retry(): void
    {
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Temp Down',
            'email' => 'temp@example.test',
            'phone' => '+15550003333',
            'is_subscribed' => true,
        ]);
        $message = MsgMessage::create([
            'subject' => 'Hello',
            'body' => 'Body',
            'type' => 'email',
        ]);
        $delivery = MsgDelivery::create([
            'msg_message_id' => $message->id,
            'recipient_type' => 'guest',
            'recipient_id' => $guest->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);

        // Throw an exception with 'timeout' to trigger transient path
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('connection timeout'));

        (new ProcessMsgDelivery($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame('queued', $delivery->status, 'Transient errors should not mark as failed');
        $this->assertNull($delivery->failed_at);
    }

    /** @test */
    public function permanent_failure_marks_failed_with_error_message(): void
    {
        $guest = MsgGuest::create([
            'user_id' => 1,
            'name' => 'Hard Fail',
            'email' => 'hard@example.test',
            'phone' => '+15550004444',
            'is_subscribed' => true,
        ]);
        $message = MsgMessage::create([
            'subject' => 'Hello',
            'body' => 'Body',
            'type' => 'email',
        ]);
        $delivery = MsgDelivery::create([
            'msg_message_id' => $message->id,
            'recipient_type' => 'guest',
            'recipient_id' => $guest->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);

        // Throw a generic exception that does not match transient keywords
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp rejected'));

        (new ProcessMsgDelivery($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('smtp rejected', $delivery->error);
        $this->assertNotNull($delivery->failed_at);
    }
}
