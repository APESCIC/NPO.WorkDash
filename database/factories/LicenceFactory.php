<?php

namespace Database\Factories;

use App\Models\Licence;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Licence>
 */
class LicenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key_hash' => Licence::hashKey(Str::random(32)),
            'label' => fake()->company(),
            'expires_at' => now()->addYear(),
        ];
    }

    public function forKey(string $key): static
    {
        return $this->state(['key_hash' => Licence::hashKey($key)]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
