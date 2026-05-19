<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property int $recipient_id
 * @property NotificationStatus $status
 * @property int $attempts
 * @property Carbon|null $last_attempt
 * @property string|null $error_message
 * @property Carbon $created_at
 *
 * @property-read NotificationTask $task
 * @property-read User $recipient
 */
#[Fillable(['task_id', 'recipient_id', 'status', 'attempts', 'last_attempt', 'error_message'])]
class Notification extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'last_attempt' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the task that owns the notification.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(NotificationTask::class, 'task_id');
    }

    /**
     * Get the recipient that owns the notification.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Scope a query to only include pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', NotificationStatus::PENDING);
    }

    /**
     * Scope a query to only include sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', NotificationStatus::SENT);
    }

    /**
     * Scope a query to only include delivered notifications.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', NotificationStatus::DELIVERED);
    }

    /**
     * Scope a query to only include error notifications.
     */
    public function scopeError($query)
    {
        return $query->where('status', NotificationStatus::ERROR);
    }

    /**
     * Scope a query to only include notifications with attempts less than a given number.
     */
    public function scopeMaxAttempts($query, int $maxAttempts)
    {
        return $query->where('attempts', '<', $maxAttempts);
    }

    /**
     * Scope a query to order by last attempt (oldest first).
     */
    public function scopeByLastAttempt($query)
    {
        return $query->orderBy('last_attempt', 'asc');
    }

    /**
     * Increment the attempt count and update last_attempt timestamp.
     */
    public function incrementAttempt(string $errorMessage = null): void
    {
        $this->attempts++;
        $this->last_attempt = now();

        if ($errorMessage) {
            $this->error_message = $errorMessage;
        }

        $this->save();
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): void
    {
        $this->status = NotificationStatus::SENT;
        $this->last_attempt = now();
        $this->save();
    }

    /**
     * Mark notification as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->status = NotificationStatus::DELIVERED;
        $this->save();
    }

    /**
     * Mark notification as error with optional error message.
     */
    public function markAsError(string $errorMessage = null): void
    {
        $this->status = NotificationStatus::ERROR;
        $this->last_attempt = now();

        if ($errorMessage) {
            $this->error_message = $errorMessage;
        }

        $this->save();
    }
}
