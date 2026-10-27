<?php

use App\Install\InstallState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Databases that already have users predate the installer (production
     * before v0.2.0). Record them as installed with workspace 1 so they are
     * never sent to the install wizard.
     */
    public function up(): void
    {
        if (DB::table('installations')->exists() || ! DB::table('users')->exists()) {
            return;
        }

        $now = now();

        if (! DB::table('tenants')->exists()) {
            $name = (string) config('app.name');

            DB::table('tenants')->insert([
                'id' => 1,
                'name' => $name,
                'slug' => Str::slug($name) ?: 'workspace',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('installations')->insert([
            'installation_id' => (string) Str::uuid(),
            'installed_version' => (string) config('app.version'),
            'installed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        app(InstallState::class)->writeLock();
    }

    public function down(): void
    {
        //
    }
};
