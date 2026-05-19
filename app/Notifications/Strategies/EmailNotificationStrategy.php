<?php

namespace App\Notifications\Strategies;

use App\Contracts\NotificationStrategy;
use App\Notifications\SendResult;
use Illuminate\Support\Facades\Log;

class EmailNotificationStrategy implements NotificationStrategy
{
    public function send(string $message, array $recipient): SendResult
    {
        // Stub: Log instead of sending real email
        Log::info('EMAIL STUB: Sending to ' . $recipient['email'] . ': ' . $message);

        // Simulate random success/failure for study project
        $successRate = config('notifications.mock.success_rate.email', 0.9); // 90% success rate
        $shouldSucceed = mt_rand(1, 100) <= ($successRate * 100);

        if ($shouldSucceed) {
            // Simulate random delay
            $delayMs = config('notifications.mock.average_delay_ms.email', 100);
            $actualDelay = mt_rand((int)($delayMs * 0.5), (int)($delayMs * 1.5));
            usleep($actualDelay * 1000); // Convert to microseconds

            return SendResult::success();
        } else {
            // Simulate failure
            $errorTypes = [
                'network_timeout' => 'Network timeout connecting to email service',
                'rate_limit' => 'Rate limit exceeded, try again later',
                'invalid_recipient' => 'Invalid email address',
            ];

            $errorKey = array_rand($errorTypes);
            $errorMessage = $errorTypes[$errorKey];

            // Determine if error is transient (should retry)
            $transientErrors = ['network_timeout', 'rate_limit'];
            $shouldRetry = in_array($errorKey, $transientErrors);
            $retryDelay = $shouldRetry ? mt_rand(1, 5) * 60 : null; // 1-5 minutes

            return SendResult::failure($errorMessage, $errorKey, $shouldRetry, $retryDelay);
        }
    }
}
