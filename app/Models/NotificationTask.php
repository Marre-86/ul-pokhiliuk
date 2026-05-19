<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationTaskStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property NotificationChannel $channel
 * @property string $message
 * @property NotificationTaskStatus $status
 * @property int $priority
 * @property Carbon $created_at
 * @property Carbon|null $completed_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Notification> $notifications
 */
#[Fillable(['channel', 'message', 'status', 'priority', 'completed_at'])]
class NotificationTask extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notification_tasks';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationTaskStatus::class,
            'priority' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the notifications for the task.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'task_id');
    }

    /**
     * Scope a query to only include pending tasks.
     */
    public function scopePending($query)
    {
        return $query->where('status', NotificationTaskStatus::PENDING);
    }

    /**
     * Scope a query to only include processing tasks.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', NotificationTaskStatus::PROCESSING);
    }

    /**
     * Scope a query to only include completed tasks.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', NotificationTaskStatus::COMPLETED);
    }

    /**
     * Scope a query to only include failed tasks.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', NotificationTaskStatus::ERROR);
    }

    /**
     * Scope a query to order by priority (highest priority first).
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
