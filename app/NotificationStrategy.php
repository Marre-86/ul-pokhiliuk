<?php

namespace App\Contracts;

use App\Notifications\SendResult;

interface NotificationStrategy
{
    public function send(string $message, array $recipient): SendResult;
}
