<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateRequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID');

        // 1. Проверка наличия
        if (!$requestId) {
            return response()->json([
                'error' => 'X-Request-ID header is required for idempotency'
            ], 400);
        }

        // 2. Проверка на пустоту/пробелы
        if (empty(trim($requestId))) {
            return response()->json([
                'error' => 'X-Request-ID cannot be empty or contain only whitespace'
            ], 400);
        }

        // 3. Проверка длины
        if (strlen($requestId) !== 36) {
            return response()->json([
                'error' => 'X-Request-ID must be exactly 36 characters long (UUID v4 format)'
            ], 400);
        }

        // 4. Приведение к нижнему регистру
        $requestId = strtolower($requestId);

        // 5. Проверка формата UUID v4
        $uuidV4Pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        if (!preg_match($uuidV4Pattern, $requestId)) {
            return response()->json([
                'error' => 'X-Request-ID must be a valid UUID v4 format'
            ], 400);
        }

        // Сохраняем нормализованный ID в запросе для дальнейшего использования
        $request->headers->set('X-Request-ID', $requestId);

        return $next($request);
    }
}
