<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-level licences for self-hosted update downloads (not tenant-owned).
     */
    public function up(): void
    {
        Schema::create('licences', function (Blueprint $table) {
            $table->id();
            $table->string('key_hash', 64)->unique();
            $table->string('label');
            $table->string('installation_id')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licences');
    }
};
