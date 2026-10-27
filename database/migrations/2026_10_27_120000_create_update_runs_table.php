<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-level update history and audit (not tenant-owned).
     */
    public function up(): void
    {
        Schema::create('update_runs', function (Blueprint $table) {
            $table->id();
            $table->string('from_version');
            $table->string('to_version');
            $table->string('channel');
            $table->string('source');
            $table->string('status')->index();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('release');
            $table->json('state')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('update_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('update_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->string('status');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('state')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['update_run_id', 'position']);
        });

        Schema::table('installations', function (Blueprint $table) {
            $table->string('web_root')->nullable()->after('installed_version');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn('web_root');
        });

        Schema::dropIfExists('update_steps');
        Schema::dropIfExists('update_runs');
    }
};
