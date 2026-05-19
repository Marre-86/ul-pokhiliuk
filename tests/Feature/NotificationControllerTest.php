<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationTaskStatus;
use App\Models\NotificationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_UUID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    /** @test */
    public function test_requires_x_request_id_header()
    {
        $response = $this->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'X-Request-ID header is required for idempotency'
        ]);
    }

    /** @test */
    public function test_validates_x_request_id_cannot_be_empty_or_whitespace()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => '   ',
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'X-Request-ID cannot be empty or contain only whitespace'
        ]);
    }

    /** @test */
    public function test_validates_x_request_id_length()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => 'too-short',
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'X-Request-ID must be exactly 36 characters long (UUID v4 format)'
        ]);
    }

    /** @test */
    public function test_validates_x_request_id_uuid_format()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => '12345678-1234-1234-1234-123456789abc', // valid length but not v4
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'X-Request-ID must be a valid UUID v4 format'
        ]);
    }

    /** @test */
    public function test_validates_channel_is_required()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'message' => 'Test message',
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channel']);
    }

    /** @test */
    public function test_validates_channel_must_be_valid_enum()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'invalid',
            'message' => 'Test message',
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channel']);
    }

    /** @test */
    public function test_validates_message_is_required()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    /** @test */
    public function test_validates_message_max_length()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => str_repeat('a', 1001),
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    /** @test */
    public function test_validates_recipients_is_required()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'priority' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipients']);
    }

    /** @test */
    public function test_validates_recipients_must_be_array()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => 'not-an-array',
            'priority' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipients']);
    }

    /** @test */
    public function test_validates_recipients_must_have_at_least_one_item()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => [],
            'priority' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipients']);
    }

    /** @test */
    public function test_validates_each_recipient_is_string()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => [1, 2],
            'priority' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipients.0']);
    }

    /** @test */
    public function test_validates_priority_is_required()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => ['1'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    /** @test */
    public function test_validates_priority_must_be_integer()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => ['1'],
            'priority' => 'high',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    /** @test */
    public function test_validates_priority_range()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipients' => ['1'],
            'priority' => 11,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    /** @test */
    public function test_creates_notification_task_on_valid_request()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Ваш код подтверждения: 123456',
            'recipients' => ['1', '13', '124'],
            'priority' => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Notification task created successfully',
            'request_id' => self::VALID_UUID,
        ]);

        $this->assertDatabaseHas('notification_tasks', [
            'channel' => NotificationChannel::SMS->value,
            'message' => 'Ваш код подтверждения: 123456',
            'status' => NotificationTaskStatus::PENDING->value,
            'priority' => 1,
        ]);

        $task = NotificationTask::first();
        $this->assertNotNull($task);
        $this->assertEquals(NotificationChannel::SMS, $task->channel);
        $this->assertEquals(NotificationTaskStatus::PENDING, $task->status);
        $this->assertEquals(1, $task->priority);
    }

    /** @test */
    public function test_creates_notification_task_for_email_channel()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'email',
            'message' => 'Test email message',
            'recipients' => ['5'],
            'priority' => 5,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notification_tasks', [
            'channel' => NotificationChannel::EMAIL->value,
            'message' => 'Test email message',
            'status' => NotificationTaskStatus::PENDING->value,
            'priority' => 5,
        ]);
    }

    /** @test */
    public function test_returns_task_id_in_response()
    {
        $response = $this->withHeaders([
            'X-Request-ID' => self::VALID_UUID,
        ])->postJson('/api/notifications/send-bulk', [
            'channel' => 'sms',
            'message' => 'Test',
            'recipients' => ['1'],
            'priority' => 1,
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertArrayHasKey('task_id', $data);
        
        $task = NotificationTask::find($data['task_id']);
        $this->assertNotNull($task);
    }
}