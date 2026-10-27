<?php

namespace Database\Factories;

use App\Models\Installation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Installation>
 */
class InstallationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'installation_id' => (string) Str::uuid(),
            'installed_version' => (string) config('app.version'),
            'installed_at' => now()->subDay(),
        ];
    }
}
