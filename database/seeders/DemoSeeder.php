<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fictional demo data for local development only. Never seed real personal data.
 */
class DemoSeeder extends Seeder
{
    public const DEMO_EMAIL = 'demo@example.test';

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo User',
            'email' => self::DEMO_EMAIL,
        ]);
    }
}
