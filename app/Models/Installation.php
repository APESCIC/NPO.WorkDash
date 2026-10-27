<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InstallationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single record that marks this app as installed.
 *
 * @property string $installation_id
 * @property string $installed_version
 * @property int|null $installed_by
 * @property CarbonImmutable $installed_at
 */
class Installation extends Model
{
    /** @use HasFactory<InstallationFactory> */
    use HasFactory;

    protected $fillable = ['installation_id', 'installed_version', 'installed_by', 'installed_at'];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }
}
