<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationTaskStatus;
use App\Enums\NotificationStatus;
use App\Models\NotificationTask;
use App\Models\Notification;
use App\Models\User;
use App\Jobs\SendNotificationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
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
     *                     type="integer",
     *                     example=1
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
     *             @OA\Property(property="message", type="string",
     *                 example="A request with the same X-Request-ID is already being processed or was recently processed."),
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
            'recipients.*' => ['required', 'integer', 'min:1'],
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

        // Create individual notifications for each recipient
        $recipientIds = [];

        // Convert recipient strings to user IDs
        foreach ($validated['recipients'] as $recipient) {
            // Try to find user by ID or email
            $user = User::where('id', $recipient)
                ->orWhere('email', $recipient)
                ->first();

            if ($user) {
                $recipientIds[] = $user->id;
            }
        }

        // Batch insert notifications
        if (!empty($recipientIds)) {
            $now = now();
            $notificationData = [];

            foreach ($recipientIds as $recipientId) {
                $notificationData[] = [
                    'task_id' => $task->id,
                    'recipient_id' => $recipientId,
                    'status' => NotificationStatus::PENDING,
                    'attempts' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Insert batch
            Notification::insert($notificationData);

            // Get the inserted notifications to dispatch jobs
            $insertedNotifications = Notification::where('task_id', $task->id)
                ->whereIn('recipient_id', $recipientIds)
                ->get();

            // Determine queue based on priority (priority 5 and lower → high-priority)
            $queue = $validated['priority'] < 6 ? 'high-priority' : 'low-priority';

            // Dispatch jobs for each notification
            foreach ($insertedNotifications as $notification) {
                SendNotificationJob::dispatch($notification->id)->onQueue($queue);
            }

            $notificationCount = count($insertedNotifications);
        } else {
            $notificationCount = 0;
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification task created successfully',
            'task_id' => $task->id,
            'request_id' => $requestId,
            'notifications_created' => $notificationCount,
        ], 201);
    }
    /**
     * Webhook endpoint for notification delivery status updates
     *
     * @OA\Post(
     *     path="/api/notifications/webhook/delivery",
     *     summary="Update notification delivery status via webhook",
     *     description="External providers call this endpoint to report delivery status (delivered, delivery_failed). Requires Bearer token authentication.",
     *     operationId="updateDeliveryStatus",
     *     tags={"Notifications"},
     *     security={{"bearerAuth": {}}},
     *     @OA\SecurityScheme(
     *         securityScheme="bearerAuth",
     *         type="http",
     *         scheme="bearer",
     *         bearerFormat="JWT",
     *         description="Use a valid bearer token for webhook authentication"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Delivery status update",
     *         @OA\JsonContent(
     *             required={"notification_id", "status", "timestamp"},
     *             @OA\Property(
     *                 property="notification_id",
     *                 type="integer",
     *                 example=123,
     *                 description="ID of the notification to update"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"delivered", "delivery_failed"},
     *                 example="delivered",
     *                 description="Delivery status"
     *             ),
     *             @OA\Property(
     *                 property="timestamp",
     *                 type="string",
     *                 format="date-time",
     *                 example="2026-05-20T12:30:00Z",
     *                 description="When the delivery event occurred (ISO 8601)"
     *             ),
     *             @OA\Property(
     *                 property="error_message",
     *                 type="string",
     *                 example="Recipient unavailable",
     *                 description="Error message for delivery failures (optional)"
     *             ),
     *             @OA\Property(
     *                 property="error_code",
     *                 type="string",
     *                 example="RECIPIENT_UNAVAILABLE",
     *                 description="Error code for delivery failures (optional)"
     *             ),
     *             @OA\Property(
     *                 property="provider_reference",
     *                 type="string",
     *                 example="msg-abc123",
     *                 description="Provider's reference ID for this notification (optional)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery status updated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Authorization header required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid authentication token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid authentication token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notification not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Notification not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid status or missing required fields")
     *         )
     *     )
     * )
     */
    public function updateDeliveryStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_id' => 'required|integer|exists:notifications,id',
            'status' => 'required|string|in:delivered,delivery_failed',
            'timestamp' => 'required|date',
            'error_message' => 'nullable|string|max:500',
            'error_code' => 'nullable|string|max:50',
            'provider_reference' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        $validated = $validator->validated();

        $notification = Notification::find($validated['notification_id']);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        // Map string status to enum
        $statusMap = [
            'delivered' => NotificationStatus::DELIVERED,
            'delivery_failed' => NotificationStatus::DELIVERY_FAILED,
        ];

        $statusEnum = $statusMap[$validated['status']];

        // Update notification
        $updateData = [
            'status' => $statusEnum->value,
            'delivered_at' => $validated['status'] === 'delivered'
                ? $validated['timestamp']
                : null,
            'delivery_failed_at' => $validated['status'] === 'delivery_failed'
                ? $validated['timestamp']
                : null,
        ];

        // Only update error fields for delivery failures
        if ($validated['status'] === 'delivery_failed') {
            $updateData['error_message'] = $validated['error_message'] ?? 'Delivery failed';
            $updateData['error_code'] = $validated['error_code'] ?? 'DELIVERY_FAILED';
        }

        $notification->update($updateData);

        Log::info("Notification {$notification->id} delivery status updated to {$validated['status']} via webhook");

        return response()->json([
            'success' => true,
            'message' => 'Delivery status updated'
        ]);
    }
}
