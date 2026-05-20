<?php

use App\Enums\NotificationChannel;

return [
    'strategies' => [
        NotificationChannel::EMAIL->value => App\Notifications\Strategies\EmailNotificationStrategy::class,
        NotificationChannel::SMS->value => App\Notifications\Strategies\SmsNotificationStrategy::class,
    ],
    
    'mock' => [
        'enabled' => env('NOTIFICATIONS_MOCK_ENABLED', true),
        'success_rate' => [
            'email' => env('NOTIFICATIONS_MOCK_SUCCESS_RATE_EMAIL', 0.1),
            'sms' => env('NOTIFICATIONS_MOCK_SUCCESS_RATE_SMS', 0.1),
        ],
        'average_delay_ms' => [
            'email' => env('NOTIFICATIONS_MOCK_DELAY_EMAIL', 100),
            'sms' => env('NOTIFICATIONS_MOCK_DELAY_SMS', 200),
        ],
    ],
    
    'retry' => [
        'max_attempts' => env('NOTIFICATIONS_MAX_RETRIES', 3),
        'base_delay_seconds' => env('NOTIFICATIONS_RETRY_DELAY', 60),
        'max_delay_seconds' => env('NOTIFICATIONS_MAX_RETRY_DELAY', 300),
    ],

    //  для тестирования
    // 'request_id_ttl' => env('NOTIFICATIONS_REQUEST_ID_TTL', 1),
    'request_id_ttl' => env('NOTIFICATIONS_REQUEST_ID_TTL', 3600),
];
