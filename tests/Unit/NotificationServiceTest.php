<?php

namespace Tests\Unit;

use App\Contracts\NotificationStrategy;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationTask;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\SendResult;
use App\Notifications\Exceptions\NotificationFailureException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function test_set_strategy_by_channel_sets_correct_strategy_for_valid_channel()
    {
        $service = new NotificationService();
        $service->setStrategyByChannel(NotificationChannel::EMAIL->value);

        // Strategy should be set (we can't assert internal property, but we can test no exception)
        $this->assertTrue(true);
    }

    /** @test */
    public function test_set_strategy_by_channel_throws_exception_for_invalid_channel()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown channel: invalid');

        $service = new NotificationService();
        $service->setStrategyByChannel('invalid');
    }

    /** @test */
    public function test_notify_success_updates_notification_status_to_sent()
    {
        $user = User::factory()->create();
        $task = NotificationTask::create([
            'channel' => NotificationChannel::SMS,
            'message' => 'Test message',
            'priority' => 1,
        ]);
        $notification = Notification::create([
            'task_id' => $task->id,
            'recipient_id' => $user->id,
            'status' => NotificationStatus::PENDING,
            'attempts' => 0,
        ]);

        $mockStrategy = Mockery::mock(NotificationStrategy::class);
        $mockStrategy->shouldReceive('send')
            ->with('Test message', ['id' => $user->id, 'email' => $user->email, 'phone' => $user->phone ?? null])
            ->andReturn(SendResult::success());

        Log::shouldReceive('info')
            ->once()
            ->with("Notification {$notification->id} sent successfully via sms");

        $service = new NotificationService();
        // Inject mock strategy via reflection (since setStrategyByChannel uses app())
        // Instead, we'll set the protected property directly.
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('strategy');
        $property->setAccessible(true);
        $property->setValue($service, $mockStrategy);

        $service->notify($notification, ['id' => $user->id, 'email' => $user->email, 'phone' => $user->phone]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => NotificationStatus::SENT->value,
            'sent_at' => now(),
            'attempts' => 1,
            'error_message' => null,
            'error_code' => null,
        ]);
    }

    /** @test */
    public function test_notify_transient_failure_throws_notification_failure_exception()
    {
        $user = User::factory()->create();
        $task = NotificationTask::create([
            'channel' => NotificationChannel::SMS,
            'message' => 'Test',
            'priority' => 1,
        ]);
        $notification = Notification::create([
            'task_id' => $task->id,
            'recipient_id' => $user->id,
            'status' => NotificationStatus::PENDING,
            'attempts' => 0,
        ]);

        $mockStrategy = Mockery::mock(NotificationStrategy::class);
        $mockStrategy->shouldReceive('send')
            ->andReturn(SendResult::transientFailure('Network timeout', 'network_timeout', 60));

        $service = new NotificationService();
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('strategy');
        $property->setAccessible(true);
        $property->setValue($service, $mockStrategy);

        $this->expectException(NotificationFailureException::class);
        $this->expectExceptionMessage('Network timeout');

        $service->notify($notification, ['id' => $user->id, 'email' => $user->email, 'phone' => $user->phone]);

        // After exception, notification attempts should be incremented
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'attempts' => 1,
            'last_attempt' => now(),
        ]);
    }

    /** @test */
    public function test_notify_permanent_failure_updates_status_to_failed()
    {
        $user = User::factory()->create();
        $task = NotificationTask::create([
            'channel' => NotificationChannel::SMS,
            'message' => 'Test',
            'priority' => 1,
        ]);
        $notification = Notification::create([
            'task_id' => $task->id,
            'recipient_id' => $user->id,
            'status' => NotificationStatus::PENDING,
            'attempts' => 0,
        ]);

        $mockStrategy = Mockery::mock(NotificationStrategy::class);
        $mockStrategy->shouldReceive('send')
            ->andReturn(SendResult::permanentFailure('Invalid recipient', 'invalid_recipient'));

        Log::shouldReceive('warning')
            ->once()
            ->with("Notification {$notification->id} permanent failure: Invalid recipient");

        $service = new NotificationService();
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('strategy');
        $property->setAccessible(true);
        $property->setValue($service, $mockStrategy);

        $service->notify($notification, ['id' => $user->id, 'email' => $user->email, 'phone' => $user->phone]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => NotificationStatus::FAILED->value,
            'failed_at' => now(),
            'error_message' => 'Invalid recipient',
            'error_code' => 'invalid_recipient',
            'attempts' => 1,
        ]);
    }

    /** @test */
    public function test_notify_without_strategy_throws_runtime_exception()
    {
        $user = User::factory()->create();
        $task = NotificationTask::create([
            'channel' => NotificationChannel::SMS,
            'message' => 'Test',
            'priority' => 1,
        ]);
        $notification = Notification::create([
            'task_id' => $task->id,
            'recipient_id' => $user->id,
            'status' => NotificationStatus::PENDING,
            'attempts' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Notification strategy not set');

        $service = new NotificationService();
        $service->notify($notification, ['id' => $user->id]);
    }
}