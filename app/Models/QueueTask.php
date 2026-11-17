<?php

namespace App\Models;

use App\Enums\QueueTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A task dispatched to the BullMQ queue service. The id doubles as the BullMQ job id.
 *
 * @property string $id
 * @property string $queue
 * @property string $name
 * @property QueueTaskStatus $status
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $options
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property int|null $user_id
 * @property mixed $progress
 * @property mixed $result
 * @property string|null $error
 * @property int $attempts_made
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $subject
 * @property-read User|null $user
 */
#[Fillable([
    'queue', 'name', 'status', 'payload', 'options', 'user_id', 'progress', 'result', 'error',
    'attempts_made', 'dispatched_at', 'started_at', 'finished_at',
])]
class QueueTask extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts_made' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueTaskStatus::class,
            'payload' => 'array',
            'options' => 'array',
            'progress' => 'json',
            'result' => 'json',
            'attempts_made' => 'integer',
            'dispatched_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * The model this task is about (e.g. the Team a report is generated for).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who dispatched the task, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<QueueTask>  $query
     */
    public function scopeUnfinished(Builder $query): void
    {
        $query->whereNotIn('status', array_filter(
            QueueTaskStatus::cases(),
            fn (QueueTaskStatus $status) => $status->isTerminal(),
        ));
    }
}
