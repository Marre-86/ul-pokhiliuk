<?php

namespace App\Notifications;

class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
        public readonly bool $shouldRetry = false,
        public readonly ?int $retryDelay = null,
    ) {
    }

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(
        string $errorMessage,
        ?string $errorCode = null,
        bool $shouldRetry = false,
        ?int $retryDelay = null
    ): self {
        return new self(false, $errorMessage, $errorCode, $shouldRetry, $retryDelay);
    }

    public static function transientFailure(
        string $errorMessage,
        ?string $errorCode = null,
        ?int $retryDelay = null
    ): self {
        return new self(false, $errorMessage, $errorCode, true, $retryDelay);
    }

    public static function permanentFailure(string $errorMessage, ?string $errorCode = null): self
    {
        return new self(false, $errorMessage, $errorCode, false);
    }
}
