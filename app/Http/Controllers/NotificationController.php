<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationTaskStatus;
use App\Models\NotificationTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;

class NotificationController extends Controller
{
    /**
     * Send bulk notifications
     *
     * @OA\Post(
     *     path="/api/notifications/send-bulk",
     *     summary="Send notifications to multiple recipients",
     *     description="Creates a notification task for sending messages via specified channel to a list of recipients",
     *     operationId="sendBulkNotifications",
     *     tags={"Notifications"},
     *     @OA\Parameter(
     *         name="X-Request-ID",
     *         in="header",
     *         required=true,
     *         description="Unique request identifier for idempotency and tracing. Must be a valid UUID v4 (36 characters, lowercase).",
     *         @OA\Schema(
     *             type="string",
     *             format="uuid",
     *             pattern="^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$",
     *             example="f47ac10b-58cc-4372-a567-0e02b2c3d479"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Notification data",
     *         @OA\JsonContent(
     *             required={"channel", "message", "recipients", "priority"},
     *             @OA\Property(
     *                 property="channel",
     *                 type="string",
     *                 enum={"email", "sms"},
     *                 example="sms",
     *                 description="Notification channel"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 maxLength=1000,
     *                 example="Ваш код подтверждения: 123456",
     *                 description="Message text to send"
     *             ),
     *             @OA\Property(
     *                 property="recipients",
     *                 type="array",
     *                 @OA\Items(
     *                     type="string",
     *                     maxLength=255,
     *                     example="1"
     *                 ),
     *                 minItems=1,
     *                 example={"1", "13", "124"},
     *                 description="List of user IDs to receive the notification"
     *             ),
     *             @OA\Property(
     *                 property="priority",
     *                 type="integer",
     *                 minimum=0,
     *                 maximum=10,
     *                 example=1,
     *                 description="Priority level (0=highest, 10=lowest)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Notification task created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notification task created successfully"),
     *             @OA\Property(property="task_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="request_id",
     *                 type="string",
     *                 example="req-123e4567-e89b-12d3-a456-426614174000"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Missing required header",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Missing required header: X-Request-ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "channel": {"The channel field is required."},
     *                     "recipients": {"The recipients field is required."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Duplicate request detected",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Duplicate request detected"),
     *             @OA\Property(property="message", type="string", example="A request with the same X-Request-ID is already being processed or was recently processed."),
     *             @OA\Property(property="request_id", type="string", example="f47ac10b-58cc-4372-a567-0e02b2c3d479")
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function sendBulk(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');

        // Duplication check using Redis
        $cacheKey = 'request_id:' . $requestId;
        $ttl = config('notifications.request_id_ttl', 3600); // 1 hour by default
        if (!Cache::add($cacheKey, true, $ttl)) {
            return response()->json([
                'error' => 'Duplicate request detected',
                'message' => 'A request with the same X-Request-ID is already being processed or was recently processed.',
                'request_id' => $requestId,
            ], 409);
        }

        // Validate request payload
        $validator = Validator::make($request->all(), [
            'channel' => ['required', 'string', Rule::in(NotificationChannel::values())],
            'message' => ['required', 'string', 'max:1000'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Create notification task
        $task = NotificationTask::create([
            'channel' => NotificationChannel::from($validated['channel']),
            'message' => $validated['message'],
            'status' => NotificationTaskStatus::PENDING,
            'priority' => $validated['priority'],
        ]);

        // Here you would typically create individual notifications for each recipient
        // and associate them with the task, but as per requirement we just save the task

        return response()->json([
            'success' => true,
            'message' => 'Notification task created successfully',
            'task_id' => $task->id,
            'request_id' => $requestId,
        ], 201);
    }
}
