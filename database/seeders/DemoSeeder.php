<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fictional demo data for the Laragon preview only: en_GB Faker with a fixed seed
 * and example.test emails. Never seed real personal data. Refuses to run unless
 * APP_ENV=local.
 */
class DemoSeeder extends Seeder
{
    public const DEMO_EMAIL = 'demo@example.test';

    public const SEED = 20261109;

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder only runs with APP_ENV=local.');
        }

        $faker = fake('en_GB');
        $faker->seed(self::SEED);

        $this->user(self::DEMO_EMAIL, 'Demo User');

        foreach (['Demo Animal Rescue (fake)', 'Demo Wildlife Trust (fake)', 'Demo Reptile Sanctuary (fake)'] as $name) {
            $tenant = Tenant::query()->firstOrNew(['slug' => Str::slug($name)]);
            $tenant->forceFill(['name' => $name, 'slug' => Str::slug($name)])->save();
        }

        foreach (range(1, 12) as $i) {
            $this->user(sprintf('demo-user-%02d@example.test', $i), $faker->name());
        }
    }

    private function user(string $email, string $name): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();
    }
}
