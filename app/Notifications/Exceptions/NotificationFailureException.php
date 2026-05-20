<?php

namespace App\Notifications\Exceptions;

use Exception;

class NotificationFailureException extends Exception
{
    public function __construct(
        string $message = '',
        public readonly ?string $errorCode = null,
        public readonly bool $shouldRetry = false,
        public readonly ?int $retryDelay = null
    ) {
        parent::__construct($message);
    }
}
