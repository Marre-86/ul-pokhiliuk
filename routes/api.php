<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;
use App\Http\Middleware\ValidateRequestId;

Route::post('/notifications/send-bulk', [NotificationController::class, 'sendBulk'])
    ->middleware(ValidateRequestId::class);

Route::post('/notifications/webhook/delivery', [NotificationController::class, 'updateDeliveryStatus'])
    ->middleware(\App\Http\Middleware\AuthenticateWebhook::class);
