<?php

use App\Updates\ReleaseManifest;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = storage_path('framework/testing/manifest-'.uniqid());
    File::ensureDirectoryExists("{$this->dir}/database/migrations");
    File::ensureDirectoryExists("{$this->dir}/public/build");
    file_put_contents("{$this->dir}/VERSION", "0.2.0-beta.1\n");
    file_put_contents("{$this->dir}/database/migrations/2026_01_01_000000_create_things.php", '<?php');
    file_put_contents("{$this->dir}/public/build/manifest.json", '{}');
});

afterEach(fn () => File::deleteDirectory($this->dir));

test('the manifest lists requirements, migrations and per-file hashes', function () {
    $manifest = ReleaseManifest::build($this->dir, '0.2.0-beta.1', '2026-10-27T00:00:00+00:00');

    expect($manifest)
        ->version->toBe('0.2.0-beta.1')
        ->channel->toBe('beta')
        ->released_at->toBe('2026-10-27T00:00:00+00:00')
        ->min_update_from->toBe(config('updates.min_update_from'))
        ->migrations->toBe(['2026_01_01_000000_create_things'])
        ->and($manifest['requirements'])
        ->php->toBe('8.3.0')
        ->mysql->toBe('8.0.0')
        ->mariadb->toBe('10.6.0')
        ->extensions->toContain('pdo_mysql', 'zip')
        ->and($manifest['files'])
        ->toHaveKeys(['VERSION', 'public/build/manifest.json', 'database/migrations/2026_01_01_000000_create_things.php'])
        ->and($manifest['files']['VERSION'])->toBe(hash('sha256', "0.2.0-beta.1\n"));
});

test('stable versions are on the stable channel', function () {
    expect(ReleaseManifest::build($this->dir, '0.2.0', 'now')['channel'])->toBe('stable');
});

test('mismatches reports modified and missing files', function () {
    $manifest = ReleaseManifest::build($this->dir, '0.2.0', 'now');

    expect(ReleaseManifest::mismatches($this->dir, $manifest))->toBe([]);

    file_put_contents("{$this->dir}/VERSION", 'tampered');
    unlink("{$this->dir}/public/build/manifest.json");

    expect(ReleaseManifest::mismatches($this->dir, $manifest))
        ->toEqualCanonicalizing(['VERSION', 'public/build/manifest.json']);
});

test('release:manifest writes manifest.json into the staged release', function () {
    $this->artisan('release:manifest', ['path' => $this->dir])->assertSuccessful();

    $manifest = json_decode((string) file_get_contents("{$this->dir}/manifest.json"), true);

    expect($manifest['version'])->toBe('0.2.0-beta.1')
        ->and($manifest['files'])->not->toHaveKey('manifest.json');
});
