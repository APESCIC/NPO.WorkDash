<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One update attempt: the audit record of who updated from and to which
 * version, how long it took and how it ended.
 *
 * @property int $id
 * @property string $from_version
 * @property string $to_version
 * @property string $channel
 * @property string $source
 * @property string $status
 * @property int|null $started_by
 * @property array<string, mixed> $release
 * @property array<string, mixed>|null $state
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 */
class UpdateRun extends Model
{
    public const PENDING = 'pending';

    public const RUNNING = 'running';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const ROLLED_BACK = 'rolled_back';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'release' => 'array',
            'state' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<UpdateStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(UpdateStep::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @param  Builder<UpdateRun>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [self::PENDING, self::RUNNING]);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::FAILED, self::ROLLED_BACK], true);
    }

    public function stateValue(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function mergeState(array $values): void
    {
        $this->state = array_merge($this->state ?? [], $values);
        $this->save();
    }

    public function durationSeconds(): ?int
    {
        return $this->started_at && $this->finished_at
            ? (int) $this->started_at->diffInSeconds($this->finished_at)
            : null;
    }
}
