<?php

namespace Tests;

use App\Install\InstallState;
use App\Models\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config([
            'install.lock_path' => storage_path('framework/testing/installed-'.getmypid().'.lock'),
            'install.check_outbound' => false,
        ]);
        @unlink(config('install.lock_path'));

        // Feature tests run against an installed app; installer tests call uninstall().
        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            Installation::factory()->create();
        }
    }

    protected function tearDown(): void
    {
        @unlink((string) config('install.lock_path'));

        parent::tearDown();
    }

    protected function uninstall(): void
    {
        Installation::query()->delete();
        @unlink((string) config('install.lock_path'));
        $this->app->make(InstallState::class)->forget();
    }

    protected function skipUnlessFortifyFeature(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
