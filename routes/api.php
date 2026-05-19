<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

Route::post('/notifications/send-bulk', [NotificationController::class, 'sendBulk']);
