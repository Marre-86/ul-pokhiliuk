<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class AuthenticateWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            Log::warning('Webhook request missing Authorization header', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authorization header required'
            ], 401);
        }

        // Check for Bearer token format
        if (!str_starts_with($authHeader, 'Bearer ')) {
            Log::warning('Webhook request with invalid Authorization format', [
                'ip' => $request->ip(),
                'header' => substr($authHeader, 0, 20) . '...',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid Authorization format. Use: Bearer {token}'
            ], 401);
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        $validTokens = config('services.webhooks.notification_delivery.tokens', []);

        if (empty($validTokens)) {
            Log::error('Webhook authentication misconfigured: No valid tokens defined');

            return response()->json([
                'success' => false,
                'message' => 'Server configuration error'
            ], 500);
        }

        if (!in_array($token, $validTokens, true)) {
            Log::warning('Webhook request with invalid token', [
                'ip' => $request->ip(),
                'token_prefix' => substr($token, 0, 8) . '...',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication token'
            ], 403);
        }

        // Token is valid, proceed with request
        Log::debug('Webhook request authenticated', [
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
