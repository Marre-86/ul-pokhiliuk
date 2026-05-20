<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Support\Facades\Log;

// Выставлены значения для демонстрации работы сервиса, в целях ускорения повторных запросов.
#[Tries(5)]
#[Backoff([3, 6, 9, 12, 15, 18, 21, 24, 27, 30])]

// Реальные значения для продакшна.
// #[Tries(15)]
// #[Backoff([60, 300, 900, 3600, 7200, 14400, 28800, 43200, 86400, 172800])]
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $notificationId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $notificationId)
    {
        $this->notificationId = $notificationId;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        $notification = Notification::with(['task', 'recipient'])->find($this->notificationId);

        if (!$notification) {
            Log::error("Notification {$this->notificationId} not found");
            return;
        }

        // Set the strategy based on the task's channel
        $notificationService->setStrategyByChannel($notification->task->channel->value);

        // Prepare recipient data for the strategy
        $recipientData = [
            'id' => $notification->recipient->id,
            'email' => $notification->recipient->email,
            'phone' => $notification->recipient->phone ?? null,
        ];

        // Call the notification service to send the notification
        $notificationService->notify($notification, $recipientData);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $notification = Notification::find($this->notificationId);

        if (!$notification) {
            return;
        }

        if ($exception instanceof \App\Notifications\Exceptions\NotificationFailureException) {
            // Transient failure that exhausted all retries
            $notification->update([
                'status' => \App\Enums\NotificationStatus::FAILED,
                'failed_at' => now(),
                'last_attempt' => now(),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->errorCode ?? 'transient_failure',
            ]);

            Log::warning("SendNotificationJob failed after all retries for notification {$this->notificationId}: " . $exception->getMessage());
        } else {
            // Unexpected exception
            $notification->update([
                'status' => \App\Enums\NotificationStatus::FAILED,
                'failed_at' => now(),
                'last_attempt' => now(),
                'error_message' => 'Job failed: ' . $exception->getMessage(),
                'error_code' => 'job_failed',
                'attempts' => $notification->attempts + 1,
            ]);

            Log::error("SendNotificationJob failed for notification {$this->notificationId}: " . $exception->getMessage());
        }
    }
}
