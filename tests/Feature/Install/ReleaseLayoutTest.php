<?php

use App\Install\ReleaseLayout;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = storage_path('framework/testing/layout-'.uniqid());
    File::ensureDirectoryExists("{$this->root}/releases/0.2.0");
    File::ensureDirectoryExists("{$this->root}/shared/storage");
});

afterEach(fn () => File::deleteDirectory($this->root));

test('the layout root is found from a release folder', function () {
    expect(ReleaseLayout::root("{$this->root}/releases/0.2.0"))->toBe($this->root)
        ->and(ReleaseLayout::root(base_path()))->toBeNull();
});

test('a release keeps its .env and storage in shared/', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('basePath')->andReturn("{$this->root}/releases/0.2.0");
    $app->shouldReceive('useEnvironmentPath')->once()->with("{$this->root}/shared");
    $app->shouldReceive('useStoragePath')->once()->with("{$this->root}/shared/storage");

    ReleaseLayout::apply($app);
});

test('cron uses the stable artisan proxy when the layout has one', function () {
    expect(ReleaseLayout::cronArtisan("{$this->root}/releases/0.2.0"))->toBe("{$this->root}/releases/0.2.0/artisan");

    touch("{$this->root}/artisan");

    expect(ReleaseLayout::cronArtisan("{$this->root}/releases/0.2.0"))->toBe("{$this->root}/artisan");
});

test('the starter web root holds no application code and denies dotfiles', function () {
    $starter = base_path('deploy/release/starter/public');
    $htaccess = (string) file_get_contents("{$starter}/.htaccess");

    expect(collect(File::allFiles($starter, true))->map->getFilename()->sort()->values()->all())
        ->toBe(['.htaccess', 'check.php', 'index.php', 'workdash-path.php'])
        ->and($htaccess)->toContain('<FilesMatch "^\.">')
        ->toContain('workdash-path|RequirementsChecker')
        ->toContain('Require all denied');
});

test('the starter entry point reads the release from current.php', function () {
    $index = (string) file_get_contents(base_path('deploy/release/starter/public/index.php'));

    expect($index)->toContain("require \$root.'/current.php'")
        ->toContain('usePublicPath(__DIR__)')
        ->toContain("'/shared/storage/framework/maintenance.php'");
});
