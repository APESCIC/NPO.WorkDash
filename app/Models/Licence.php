<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LicenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key_hash
 * @property string $label
 * @property string|null $installation_id
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 */
class Licence extends Model
{
    /** @use HasFactory<LicenceFactory> */
    use HasFactory;

    protected $fillable = ['key_hash', 'label', 'installation_id', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashKey(string $key): string
    {
        return hash('sha256', trim($key));
    }

    /**
     * Status of a presented key: valid, missing, invalid or expired.
     */
    public static function statusFor(?string $key): string
    {
        if ($key === null || trim($key) === '') {
            return 'missing';
        }

        $licence = self::query()->where('key_hash', self::hashKey($key))->first();

        if ($licence === null || $licence->revoked_at !== null) {
            return 'invalid';
        }

        if ($licence->expires_at !== null && $licence->expires_at->isPast()) {
            return 'expired';
        }

        return 'valid';
    }
}
