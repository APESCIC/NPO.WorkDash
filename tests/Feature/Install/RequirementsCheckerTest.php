<?php

use App\Install\InstallerService;
use App\Install\RequirementsChecker;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

test('MySQL below 8.0 and MariaDB below 10.6 fail with control-panel hints', function (string $version) {
    $item = (new RequirementsChecker)->checkDatabaseVersion($version);

    expect($item['status'])->toBe('fail')
        ->and($item['hints'])->toHaveKeys(['cpanel', 'plesk', 'directadmin']);
})->with(['5.7.44', '5.7.44-log', '10.5.22-MariaDB', '10.4.32-MariaDB-1:10.4.32+maria~ubu2004']);

test('MySQL 8.0 and MariaDB 10.6 or newer pass', function (string $version) {
    expect((new RequirementsChecker)->checkDatabaseVersion($version)['status'])->toBe('pass');
})->with(['8.0.36', '8.4.0', '10.6.16-MariaDB', '10.11.6-MariaDB-0+deb12u1']);

test('an outdated PHP version fails with hints for every control panel', function () {
    $items = (new RequirementsChecker(['php' => '99.0.0', 'outbound' => false]))->run();
    $php = collect($items)->firstWhere('key', 'php');

    expect($php['status'])->toBe('fail')
        ->and($php['hints'])->toHaveKeys(['cpanel', 'plesk', 'directadmin'])
        ->and(RequirementsChecker::summary($items))->toBe('fail');
});

test('a missing extension fails and an unwritable folder fails', function () {
    $items = collect((new RequirementsChecker([
        'extensions' => ['definitely_not_an_extension'],
        'writable' => ['nowhere' => '/definitely/not/a/real/folder/x'],
        'outbound' => false,
    ]))->run())->keyBy('key');

    expect($items['ext_definitely_not_an_extension']['status'])->toBe('fail')
        ->and($items['writable_nowhere']['status'])->toBe('fail');
});

test('memory limits are parsed', function (string $value, int $bytes) {
    expect(RequirementsChecker::toBytes($value))->toBe($bytes);
})->with([['256M', 268435456], ['1G', 1073741824], ['-1', -1], ['512K', 524288]]);

test('every check reports pass, warn or fail with a label and detail', function () {
    foreach ((new RequirementsChecker(['outbound' => false]))->run() as $item) {
        expect($item['status'])->toBeIn(['pass', 'warn', 'fail'])
            ->and($item['label'])->not->toBeEmpty()
            ->and($item['detail'])->not->toBeEmpty();
    }
});

test('the checker has no framework dependencies so check.php can run alone', function () {
    $source = (string) file_get_contents(app_path('Install/RequirementsChecker.php'));

    expect($source)->not->toMatch('/^use (Illuminate|Laravel|App)\\\\/m')
        ->and($source)->not->toContain('config(')
        ->and($source)->not->toContain('str_contains(')
        ->and($source)->not->toContain('match (');
});

test('app:check gives the same results as the install wizard', function () {
    $this->uninstall();
    useTemporaryEnvironmentFile();
    $installer = app(InstallerService::class);

    $wizard = [];

    foreach (['requirements', 'permissions'] as $step) {
        $installer->advance($step);

        $this->get('/install')->assertOk()->assertInertia(function (Assert $page) use (&$wizard) {
            $wizard = [...$wizard, ...$page->toArray()['props']['checks']];
        });
    }

    @unlink($installer->progressPath());

    Artisan::call('app:check', ['--json' => true, '--no-outbound' => true]);
    $cli = json_decode(Artisan::output(), true)['items'];

    $byKey = fn (array $items) => collect($items)
        ->reject(fn (array $item) => str_starts_with($item['key'], 'db_'))
        ->mapWithKeys(fn (array $item) => [$item['key'] => [$item['status'], $item['detail']]])
        ->sortKeys()
        ->all();

    expect($byKey($cli))->toBe($byKey($wizard));
});
