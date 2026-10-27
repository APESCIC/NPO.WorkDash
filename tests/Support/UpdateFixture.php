<?php

namespace Tests\Support;

use App\Support\HealthCheck;
use App\Support\Heartbeat;
use App\Updates\DiskSpace;
use App\Updates\Engine\UpdateEngine;
use App\Updates\Layout\PointerFileLayout;
use App\Updates\ReleaseManifest;
use App\Updates\ReleaseSigner;
use App\Updates\UpdateFeed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ParagonIE_Sodium_Compat as Sodium;
use ZipArchive;

/**
 * A throwaway release layout (npo-workdash/ with current.php, releases/,
 * shared/ and a web root), a signed release zip for a newer version, and a
 * faked feed and download server.
 */
final class UpdateFixture
{
    public string $root;

    public string $zip;

    public string $secretKey;

    /** @var array<string, mixed> */
    public array $release;

    public string $zipBytes;

    public function __construct(public string $version = '9.9.0', public string $currentVersion = '1.0.0')
    {
        $this->root = str_replace('\\', '/', storage_path('framework/testing/update-'.uniqid()));

        foreach (["releases/{$currentVersion}/public", 'public', 'shared/storage/app', 'shared/storage/framework', 'shared/storage/logs', 'build'] as $dir) {
            File::ensureDirectoryExists("{$this->root}/{$dir}");
        }

        file_put_contents("{$this->root}/releases/{$currentVersion}/artisan", '<?php');
        file_put_contents("{$this->root}/releases/{$currentVersion}/VERSION", $currentVersion);
        file_put_contents("{$this->root}/releases/{$currentVersion}/public/app-{$currentVersion}.js", 'old');
        file_put_contents("{$this->root}/current.php", "<?php\n\nreturn __DIR__.'/releases/{$currentVersion}';\n");
        file_put_contents("{$this->root}/public/index.php", '<?php // starter');
        file_put_contents("{$this->root}/public/workdash-path.php", '<?php return dirname(__DIR__);');

        $pair = Sodium::crypto_sign_keypair();
        $this->secretKey = base64_encode(Sodium::crypto_sign_secretkey($pair));

        config([
            'app.version' => $currentVersion,
            'updates.public_key' => base64_encode(Sodium::crypto_sign_publickey($pair)),
            'updates.work_path' => "{$this->root}/shared/storage/app/updates",
            'updates.backup_path' => "{$this->root}/shared/storage/app/backups",
            'updates.log_path' => "{$this->root}/shared/storage/logs/updates",
            'updates.lock_path' => "{$this->root}/shared/storage/framework/updater.lock",
            'updates.feed_url' => 'https://feed.example.test/v1',
        ]);

        $this->buildZip();
        Heartbeat::record();
        app(UpdateEngine::class)->useLayout($this->layout());
        $this->healthy(true);
    }

    /**
     * Make the post-update health check pass or fail.
     */
    public function healthy(bool $ok): void
    {
        app()->instance(HealthCheck::class, new class($ok) extends HealthCheck
        {
            public function __construct(private readonly bool $ok) {}

            public function run(): array
            {
                return ['ok' => $this->ok, 'version' => '9.9.0', 'checks' => ['database' => ['ok' => $this->ok, 'detail' => $this->ok ? 'Connected' : 'Simulated failure']]];
            }
        });
    }

    public function layout(): PointerFileLayout
    {
        return new PointerFileLayout($this->root, ["{$this->root}/public"]);
    }

    /**
     * @param  array<string, string>  $extraReleaseFiles  path => contents, inside releases/<version>/
     */
    public function buildZip(array $extraReleaseFiles = [], bool $tamper = false): void
    {
        $stage = "{$this->root}/build/stage-".uniqid();
        $app = "{$stage}/npo-workdash/releases/{$this->version}";

        $files = [
            "{$app}/artisan" => '<?php',
            "{$app}/VERSION" => $this->version,
            "{$app}/public/app-{$this->version}.js" => 'new',
            "{$stage}/npo-workdash/public/index.php" => '<?php // new starter',
            "{$stage}/npo-workdash/public/workdash-path.php" => '<?php // must not overwrite',
            "{$stage}/npo-workdash/public/build/app-{$this->version}.js" => 'new asset',
        ];

        foreach ($extraReleaseFiles as $path => $contents) {
            $files["{$app}/{$path}"] = $contents;
        }

        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($path));
            file_put_contents($path, $contents);
        }

        $manifest = ReleaseManifest::build($app, $this->version, '2026-10-27T00:00:00+00:00');
        file_put_contents("{$app}/manifest.json", json_encode($manifest));

        $this->zip = "{$this->root}/build/npo-workdash-{$this->version}.zip";
        @unlink($this->zip);
        $zip = new ZipArchive;
        $zip->open($this->zip, ZipArchive::CREATE);

        foreach (File::allFiles($stage, true) as $file) {
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $file->getRelativePathname()));
        }

        $zip->close();
        File::deleteDirectory($stage);

        $signature = ReleaseSigner::sign($this->zip, $this->secretKey);

        if ($tamper) {
            file_put_contents($this->zip, 'x', FILE_APPEND);
        }

        $this->zipBytes = (string) file_get_contents($this->zip);
        $this->release = [
            'version' => $this->version,
            'channel' => 'stable',
            'released_at' => '2026-10-27T00:00:00+00:00',
            'changelog' => "### Added\n\n- Things.",
            'size' => strlen($this->zipBytes),
            'sha256' => hash('sha256', $tamper ? substr($this->zipBytes, 0, -1) : $this->zipBytes),
            'download_url' => "https://downloads.example.test/v{$this->version}/npo-workdash-{$this->version}.zip",
            'signature_url' => "https://downloads.example.test/v{$this->version}/npo-workdash-{$this->version}.zip.sig",
            'requirements' => config('updates.requirements'),
            'min_update_from' => '0.0.0',
        ];

        $this->signature = $signature;

        if (! $this->faked) {
            $this->faked = true;
            $this->fakeServers();
        }
    }

    private string $signature = '';

    private bool $faked = false;

    /**
     * Feed, signature and zip downloads (with Range support), always serving
     * the latest buildZip() output.
     */
    private function fakeServers(): void
    {
        Http::fake([
            'feed.example.test/*' => fn () => Http::response(UpdateFeed::build([$this->release], 'stable', '2026-10-27T00:00:00+00:00')),
            'api.pwnedpasswords.com/*' => Http::response(''),
            '*.zip.sig' => fn () => Http::response($this->signature),
            '*.zip' => function (Request $request) {
                if (preg_match('/bytes=(\d+)-(\d+)/', (string) ($request->header('Range')[0] ?? ''), $m)) {
                    return Http::response(substr($this->zipBytes, (int) $m[1], (int) $m[2] - (int) $m[1] + 1), 206);
                }

                return Http::response($this->zipBytes);
            },
        ]);
    }

    /**
     * Put the layout back to the old release, keeping the same zip and fakes.
     */
    public function resetLayout(): void
    {
        Artisan::call('up');
        File::deleteDirectory("{$this->root}/releases/{$this->version}");
        File::deleteDirectory("{$this->root}/public/build");
        File::deleteDirectory("{$this->root}/shared/storage/app");
        file_put_contents("{$this->root}/current.php", "<?php\n\nreturn __DIR__.'/releases/{$this->currentVersion}';\n");
        file_put_contents("{$this->root}/public/index.php", '<?php // starter');
    }

    public function currentRelease(): string
    {
        return $this->layout()->current();
    }

    public function cleanUp(): void
    {
        Artisan::call('up');
        DiskSpace::fake(null);
        File::deleteDirectory($this->root);
    }
}
