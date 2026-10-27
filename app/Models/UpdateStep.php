<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $update_run_id
 * @property int $position
 * @property string $name
 * @property string $status
 * @property int $attempts
 * @property array<string, mixed>|null $state
 * @property string|null $output
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 */
class UpdateStep extends Model
{
    public const PENDING = 'pending';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UpdateRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(UpdateRun::class, 'update_run_id');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function mergeState(array $values): void
    {
        $this->state = array_merge($this->state ?? [], $values);
        $this->save();
    }
}
