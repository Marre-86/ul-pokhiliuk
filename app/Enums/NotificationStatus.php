<?php

namespace App\Enums;

enum NotificationStatus: int
{
    case PENDING = 0;
    case SENT = 1;
    case DELIVERED = 2;
    case ERROR = 3;
    case DELIVERY_FAILED = 4;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::PENDING->value => 'pending',
            self::SENT->value => 'sent',
            self::DELIVERED->value => 'delivered',
            self::ERROR->value => 'error',
            self::DELIVERY_FAILED->value => 'delivery_failed',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'pending',
            self::SENT => 'sent',
            self::DELIVERED => 'delivered',
            self::ERROR => 'error',
            self::DELIVERY_FAILED => 'delivery_failed',
        };
    }
}
