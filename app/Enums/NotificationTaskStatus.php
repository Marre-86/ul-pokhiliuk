<?php

namespace App\Enums;

enum NotificationTaskStatus: int
{
    case PENDING = 0;
    case PROCESSING = 1;
    case COMPLETED = 2;
    case ERROR = 3;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::PENDING->value => 'pending',
            self::PROCESSING->value => 'processing',
            self::COMPLETED->value => 'completed',
            self::ERROR->value => 'error',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'pending',
            self::PROCESSING => 'processing',
            self::COMPLETED => 'completed',
            self::ERROR => 'error',
        };
    }
}
