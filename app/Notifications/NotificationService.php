<?php

namespace App\Notifications;

use App\Contracts\NotificationStrategy;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Notifications\Exceptions\NotificationFailureException;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected ?NotificationStrategy $strategy = null;

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
     *
     * @throws NotificationFailureException for transient failures (should retry)
     * @throws \Exception for unexpected errors
     */
    public function notify(Notification $notification, array $recipient): void
    {
        if (!$this->strategy) {
            throw new \RuntimeException('Notification strategy not set');
        }

        $task = $notification->task;

        // Send notification using strategy with message from task
        $result = $this->strategy->send($task->message, $recipient);

        if ($result->success) {
            // Success: update to SENT
            $notification->update([
                'status' => NotificationStatus::SENT,
                'sent_at' => now(),
                'last_attempt' => now(),
                'error_message' => null,
                'error_code' => null,
                'attempts' => $notification->attempts + 1,
            ]);

            Log::info("Notification {$notification->id} sent successfully via {$task->channel->value}");
        } else {
            // Failure - increment attempts for both transient and permanent failures
            $notification->update([
                'attempts' => $notification->attempts + 1,
                'last_attempt' => now(),
            ]);

            if ($result->shouldRetry) {
                // Transient failure: throw exception to trigger Laravel job retry
                throw new NotificationFailureException(
                    $result->errorMessage,
                    $result->errorCode,
                    true,
                    $result->retryDelay
                );
            } else {
                // Permanent failure: update to FAILED and don't retry
                $notification->update([
                    'status' => NotificationStatus::FAILED,
                    'failed_at' => now(),
                    'last_attempt' => now(),
                    'error_message' => $result->errorMessage,
                    'error_code' => $result->errorCode,
                ]);

                Log::warning("Notification {$notification->id} permanent failure: {$result->errorMessage}");
            }
        }
    }
}
