<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-docs', function () {
    return view('swagger-ui');
});


Route::get('/test-redis-cache', function () {
    $key = 'test_redis_';
    $value = 'Redis test value at ' . now()->toDateTimeString();

    // Сохраняем в кэш на 5 минут
    Cache::put($key, $value, 300);

    // Получаем из кэша
    $cachedValue = Cache::get($key);

    return response()->json([
        'success' => true,
        'message' => 'Redis через Cache работает!',
        'key' => $key,
        'stored_value' => $value,
        'retrieved_value' => $cachedValue,
        'cache_hit' => !is_null($cachedValue)
    ]);
});
