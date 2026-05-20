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
 * @property string|null $error_code
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $delivery_failed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read NotificationTask $task
 * @property-read User $recipient
 */
#[Fillable([
    'task_id',
    'recipient_id',
    'status',
    'attempts',
    'last_attempt',
    'error_message',
    'error_code',
    'sent_at',
    'failed_at',
    'delivered_at',
    'delivery_failed_at'
])]
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
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'delivery_failed_at' => 'datetime',
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

}
