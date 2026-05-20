<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property NotificationChannel $channel
 * @property string $message
 * @property int $priority
 * @property Carbon $created_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Notification> $notifications
 */
#[Fillable(['channel', 'message', 'priority'])]
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
            'priority' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
     * Scope a query to order by priority (highest priority first).
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
