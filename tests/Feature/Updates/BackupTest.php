<?php

use App\Updates\Backup\Backups;
use App\Updates\Backup\DatabaseDump;
use App\Updates\Backup\FileArchive;
use App\Updates\Deadline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->connection = scratchConnection();
    $this->dir = storage_path('framework/testing/backup-'.uniqid());
    File::ensureDirectoryExists($this->dir);

    DB::connection($this->connection)->unprepared(<<<'SQL'
        CREATE TABLE things (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            postcode VARCHAR(10) NULL,
            amount DECIMAL(8,2) NOT NULL,
            notes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        SQL);

    foreach (range(1, 7) as $i) {
        DB::connection($this->connection)->table('things')->insert([
            'name' => "Thing {$i} with 'quotes'; and \"semicolons\" ✓",
            'postcode' => "0{$i}123",
            'amount' => $i * 1.5,
            'notes' => $i % 2 ? "Line one\nLine two\r\n-- not a comment" : null,
            'active' => $i % 2,
        ]);
    }
});

afterEach(function () {
    File::deleteDirectory($this->dir);
    DB::connection($this->connection)->unprepared('DROP DATABASE IF EXISTS `'.config("database.connections.{$this->connection}.database").'`');
});

test('the backup is written in chunks that each stop at the deadline', function () {
    $dump = new DatabaseDump($this->connection);
    $file = "{$this->dir}/database.sql";
    $state = [];
    $passes = 0;

    do {
        $state = $dump->run($file, $state, new Deadline(0), 2);
        $passes++;
    } while (! $state['done'] && $passes < 50);

    expect($state['done'])->toBeTrue()
        ->and($passes)->toBeGreaterThan(3)
        ->and(file_get_contents($file))->toContain('CREATE TABLE `things`');
});

test('restoring a backup into an empty database reproduces the data', function () {
    $db = DB::connection($this->connection);
    $before = $db->table('things')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    $dump = new DatabaseDump($this->connection);
    $state = [];

    do {
        $state = $dump->run("{$this->dir}/database.sql", $state, new Deadline(0), 3);
    } while (! $state['done']);

    $db->unprepared('DROP TABLE things');
    expect($db->select("SHOW TABLES LIKE 'things'"))->toBe([]);

    $dump->restore("{$this->dir}/database.sql");

    expect($db->table('things')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toBe($before);
});

test('the files archive restores .env and storage/app', function () {
    File::ensureDirectoryExists("{$this->dir}/storage/app/private");
    File::ensureDirectoryExists("{$this->dir}/storage/app/backups/old");
    file_put_contents("{$this->dir}/.env", 'APP_NAME=Example');
    file_put_contents("{$this->dir}/storage/app/private/receipt.txt", 'receipt');
    file_put_contents("{$this->dir}/storage/app/backups/old/database.sql", 'excluded');

    FileArchive::create("{$this->dir}/files.zip", "{$this->dir}/.env", "{$this->dir}/storage/app", ["{$this->dir}/storage/app/backups"]);
    File::deleteDirectory("{$this->dir}/storage");
    unlink("{$this->dir}/.env");

    FileArchive::restore("{$this->dir}/files.zip", "{$this->dir}/.env", "{$this->dir}/storage/app");

    expect(file_get_contents("{$this->dir}/.env"))->toBe('APP_NAME=Example')
        ->and(file_get_contents("{$this->dir}/storage/app/private/receipt.txt"))->toBe('receipt')
        ->and(is_file("{$this->dir}/storage/app/backups/old/database.sql"))->toBeFalse();
});

test('only the newest three backups are kept', function () {
    config(['updates.backup_path' => "{$this->dir}/backups"]);

    foreach (['20261001-000000-run-1', '20261002-000000-run-2', '20261003-000000-run-3', '20261004-000000-run-4', '20261005-000000-run-5'] as $name) {
        File::ensureDirectoryExists("{$this->dir}/backups/{$name}");
    }

    Backups::prune();

    expect(array_map('basename', Backups::all()))->toBe(['20261005-000000-run-5', '20261004-000000-run-4', '20261003-000000-run-3']);
});

test('backups are stored in storage, outside every web root', function () {
    $path = str_replace('\\', '/', (string) config('updates.backup_path'));

    expect($path)->toStartWith(str_replace('\\', '/', storage_path()))
        ->and($path)->not->toStartWith(str_replace('\\', '/', public_path()));
});
