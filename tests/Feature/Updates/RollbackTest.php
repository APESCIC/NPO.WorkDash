<?php

use App\Models\UpdateRun;
use App\Updates\Engine\UpdateEngine;
use Illuminate\Support\Facades\DB;
use Tests\Support\UpdateFixture;

beforeEach(function () {
    $this->connection = scratchConnection();
    DB::connection($this->connection)->unprepared('CREATE TABLE donations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, amount INT NOT NULL)');
    DB::connection($this->connection)->table('donations')->insert([['amount' => 10], ['amount' => 25]]);
    config(['updates.backup_connection' => $this->connection]);

    $this->fixture = new UpdateFixture;
    $this->fixture->buildZip([
        'database/migrations/2099_01_01_000000_double_donations.php' => <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Support\Facades\DB;

            return new class extends Migration
            {
                public function up(): void
                {
                    DB::connection('scratch')->table('donations')->update(['amount' => DB::raw('amount * 2')]);
                }
            };
            PHP,
    ]);
    $this->engine = app(UpdateEngine::class);
});

afterEach(function () {
    $this->fixture->cleanUp();
    DB::connection($this->connection)->unprepared('DROP DATABASE IF EXISTS `'.config("database.connections.{$this->connection}.database").'`');
});

function donations(): array
{
    return DB::connection('scratch')->table('donations')->orderBy('id')->pluck('amount')->all();
}

test('a failed update after migrations restores the previous release and the database', function () {
    $this->fixture->healthy(false);

    $run = $this->engine->runToCompletion($this->engine->start($this->fixture->release, null, 'web'));

    expect($run->status)->toBe(UpdateRun::ROLLED_BACK)
        ->and($this->fixture->currentRelease())->toBe("{$this->fixture->root}/releases/1.0.0")
        ->and(donations())->toBe([10, 25]);
});

test('app:rollback restores the previous release and the database after a successful update', function () {
    $this->engine->runToCompletion($this->engine->start($this->fixture->release, null, 'cli'));
    expect(donations())->toBe([20, 50]);

    $this->artisan('app:rollback')->assertSuccessful();

    expect($this->fixture->currentRelease())->toBe("{$this->fixture->root}/releases/1.0.0")
        ->and(donations())->toBe([10, 25])
        ->and(UpdateRun::query()->sole()->status)->toBe(UpdateRun::ROLLED_BACK)
        ->and(app()->isDownForMaintenance())->toBeFalse();
});

test('the web rollback gives the same result as app:rollback', function () {
    $admin = superAdminWithTwoFactor();
    $this->engine->runToCompletion($this->engine->start($this->fixture->release, $admin['user'], 'web'));

    $this->actingAs($admin['user'])
        ->post(route('admin.updates.rollback'), ['password' => 'password', 'code' => currentOtp($admin['secret'])])
        ->assertRedirect(route('admin.updates.index'));

    expect($this->fixture->currentRelease())->toBe("{$this->fixture->root}/releases/1.0.0")
        ->and(donations())->toBe([10, 25])
        ->and(UpdateRun::query()->sole()->status)->toBe(UpdateRun::ROLLED_BACK);
});

test('app:rollback fails when there is nothing to roll back', function () {
    $this->artisan('app:rollback')->assertFailed();
});
