<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fictional multi-tenant data for the CI migration matrix. Never run in
 * production; every address uses example.test.
 */
class MultiTenantFixtureSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Example Animal Rescue', 'Example Wildlife Trust', 'Example Reptile Sanctuary'] as $name) {
            Tenant::factory()->create(['name' => $name]);
        }

        User::factory()->count(25)->sequence(fn ($sequence) => ['email' => "fixture-{$sequence->index}@example.test"])->create();
    }
}
