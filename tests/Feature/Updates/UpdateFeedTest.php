<?php

use App\Updates\FeedClient;
use App\Updates\InvalidRelease;
use App\Updates\UpdateFeed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function feedEntry(string $version): array
{
    return [
        'version' => $version,
        'channel' => str_contains($version, '-') ? 'beta' : 'stable',
        'released_at' => '2026-10-27T00:00:00+00:00',
        'changelog' => "### Added\n\n- Something for {$version}.",
        'size' => 1024,
        'sha256' => hash('sha256', $version),
        'download_url' => "https://github.com/APESCIC/NPO.WorkDash/releases/download/v{$version}/npo-workdash-{$version}.zip",
        'signature_url' => "https://github.com/APESCIC/NPO.WorkDash/releases/download/v{$version}/npo-workdash-{$version}.zip.sig",
        'requirements' => config('updates.requirements'),
        'min_update_from' => '0.1.0',
    ];
}

test('beta releases appear only in the beta feed, newest first', function () {
    $entries = [feedEntry('0.2.0-beta.1'), feedEntry('0.1.2'), feedEntry('0.2.0-beta.2')];

    $stable = UpdateFeed::build($entries, 'stable', '2026-10-27T00:00:00+00:00');
    $beta = UpdateFeed::build($entries, 'beta', '2026-10-27T00:00:00+00:00');

    expect(array_column($stable['releases'], 'version'))->toBe(['0.1.2'])
        ->and(array_column($beta['releases'], 'version'))->toBe(['0.2.0-beta.2', '0.2.0-beta.1', '0.1.2'])
        ->and(UpdateFeed::errors($stable))->toBe([])
        ->and(UpdateFeed::errors($beta))->toBe([]);
});

test('a stable release sorts above its betas', function () {
    $feed = UpdateFeed::build([feedEntry('0.2.0-beta.3'), feedEntry('0.2.0')], 'beta', 'now');

    expect($feed['releases'][0]['version'])->toBe('0.2.0');
});

test('the schema rejects feeds with missing or malformed fields', function () {
    $entry = feedEntry('0.2.0');
    unset($entry['sha256']);
    $entry['version'] = 'latest';

    $errors = UpdateFeed::errors(UpdateFeed::build([$entry], 'stable', '2026-10-27T00:00:00+00:00'));

    expect($errors)->not->toBe([])
        ->and(implode("\n", $errors))->toContain('sha256');
});

test('latestFor returns the newest release newer than the installed version', function () {
    $feed = UpdateFeed::build([feedEntry('0.1.2'), feedEntry('0.2.0')], 'stable', 'now');

    expect(UpdateFeed::latestFor($feed, '0.1.2')['version'])->toBe('0.2.0')
        ->and(UpdateFeed::latestFor($feed, '0.2.0'))->toBeNull();
});

test('release:feed-entry and release:feed build valid channel feeds', function () {
    $dir = storage_path('framework/testing/feed-'.uniqid());
    File::ensureDirectoryExists("{$dir}/entries");

    try {
        foreach (['0.1.2', '0.2.0-beta.1'] as $version) {
            $zip = "{$dir}/npo-workdash-{$version}.zip";
            file_put_contents($zip, random_bytes(64));
            file_put_contents("{$dir}/manifest.json", json_encode([
                'version' => $version,
                'released_at' => '2026-10-27T00:00:00+00:00',
                'requirements' => config('updates.requirements'),
                'min_update_from' => '0.1.0',
            ]));
            file_put_contents("{$dir}/notes.md", "### Added\n\n- {$version}");

            $this->artisan('release:feed-entry', [
                'zip' => $zip,
                'manifest' => "{$dir}/manifest.json",
                'output' => "{$dir}/entries/{$version}.json",
                '--notes' => "{$dir}/notes.md",
            ])->assertSuccessful();
        }

        $this->artisan('release:feed', ['entries' => "{$dir}/entries", 'output' => "{$dir}/feed/v1"])->assertSuccessful();

        $stable = json_decode((string) file_get_contents("{$dir}/feed/v1/stable.json"), true);
        $beta = json_decode((string) file_get_contents("{$dir}/feed/v1/beta.json"), true);

        expect(array_column($stable['releases'], 'version'))->toBe(['0.1.2'])
            ->and(array_column($beta['releases'], 'version'))->toBe(['0.2.0-beta.1', '0.1.2'])
            ->and($beta['releases'][0]['download_url'])->toEndWith('/v0.2.0-beta.1/npo-workdash-0.2.0-beta.1.zip');
    } finally {
        File::deleteDirectory($dir);
    }
});

test('the feed client sends the licence key and installation id', function () {
    Http::fake(['*' => Http::response(UpdateFeed::build([feedEntry('0.2.0')], 'stable', '2026-10-27T00:00:00+00:00'))]);

    $feed = (new FeedClient('https://feed.example.test/v1', 'licence-123', 'install-abc'))->fetch('stable');

    expect($feed['releases'][0]['version'])->toBe('0.2.0');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://feed.example.test/v1/stable.json'
        && $request->header('X-Licence-Key') === ['licence-123']
        && $request->header('X-Installation-Id') === ['install-abc']);
});

test('the feed client rejects an invalid feed', function () {
    Http::fake(['*' => Http::response(['schema' => 1, 'channel' => 'stable'])]);

    (new FeedClient('https://feed.example.test/v1'))->fetch('stable');
})->throws(InvalidRelease::class, 'not valid');
