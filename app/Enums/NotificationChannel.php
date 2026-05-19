<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::EMAIL->value => 'Email',
            self::SMS->value => 'Sms',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::SMS => 'Sms',
        };
    }
}
