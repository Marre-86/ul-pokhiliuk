<?php

namespace App\Notifications;

use App\Contracts\NotificationStrategy;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected ?NotificationStrategy $strategy = null;

    private const MAX_RETRIES = 3;

    public function setStrategyByChannel(string $channel): void
    {
        $strategies = config('notifications.strategies');

        if (!isset($strategies[$channel])) {
            throw new \InvalidArgumentException("Unknown channel: $channel");
        }

        $this->strategy = app($strategies[$channel]);
    }

    /**
     * Send a notification and update its status accordingly.
     */
    public function notify(Notification $notification, array $recipient): void
    {
        if (!$this->strategy) {
            throw new \RuntimeException('Notification strategy not set');
        }

        $task = $notification->task;

        try {
            // Send notification using strategy with message from task
            $result = $this->strategy->send($task->message, $recipient);

            if ($result->success) {
                // Success: update to SENT
                $notification->update([
                    'status' => NotificationStatus::SENT,
                    'sent_at' => now(),
                    'error_message' => null,
                    'error_code' => null,
                    'attempts' => $notification->attempts + 1,
                ]);

                Log::info("Notification {$notification->id} sent successfully via {$task->channel->value}");
            } else {
                // Failure: update to ERROR
                $notification->update([
                    'status' => NotificationStatus::ERROR,
                    'failed_at' => now(),
                    'error_message' => $result->errorMessage,
                    'error_code' => $result->errorCode,
                    'attempts' => $notification->attempts + 1,
                ]);

                Log::warning("Notification {$notification->id} failed: {$result->errorMessage}");

                // Check if we should retry
                if ($result->shouldRetry && $notification->attempts < self::MAX_RETRIES) {
                    $this->scheduleRetry($notification, $result->retryDelay);
                }
            }
        } catch (\Exception $e) {
            // Unexpected exception
            $notification->update([
                'status' => NotificationStatus::ERROR,
                'failed_at' => now(),
                'error_message' => 'Unexpected error: ' . $e->getMessage(),
                'error_code' => 'unexpected_error',
                'attempts' => $notification->attempts + 1,
            ]);

            Log::error("Notification {$notification->id} unexpected error: " . $e->getMessage());

            // Check if we should retry (for unexpected errors, we retry once)
            if ($notification->attempts < self::MAX_RETRIES) {
                $this->scheduleRetry($notification, 60); // 1 minute delay
            }
        }
    }

    /**
     * Schedule a retry for a failed notification.
     */
    protected function scheduleRetry(Notification $notification, ?int $delaySeconds = null): void
    {
        $delay = $delaySeconds ?? config('notifications.retry.base_delay_seconds', 60);

        // In a real application, you would dispatch a job with delay
        // For this test project, we'll just log the retry
        Log::info(
            "Notification {$notification->id} scheduled for retry " .
            "in {$delay} seconds (attempt {$notification->attempts})"
        );

        // You could implement a job dispatch here:
        // SendNotificationJob::dispatch($notification)->delay(now()->addSeconds($delay));
    }
}
