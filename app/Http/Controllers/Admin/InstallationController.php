<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Install\InstallerService;
use App\Install\PhpBinary;
use App\Install\ReleaseLayout;
use App\Models\Installation;
use App\Support\Heartbeat;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InstallationController extends Controller
{
    public function show(InstallerService $installer): Response
    {
        $installation = Installation::query()->first();

        return Inertia::render('admin/installation', [
            'installation' => [
                'id' => $installation?->installation_id,
                'installedVersion' => $installation?->installed_version,
                'installedAt' => $installation?->installed_at->toIso8601String(),
                'version' => config('app.version'),
            ],
            'cron' => [
                'line' => PhpBinary::cronLine(PhpBinary::detect(), ReleaseLayout::cronArtisan(base_path())),
                'runner' => config('queue.runner'),
                'lastSeen' => Heartbeat::lastSeen()?->toIso8601String(),
                'stale' => Heartbeat::isStale(),
            ],
            'installerFiles' => array_map('basename', $installer->installerFiles()),
        ]);
    }

    public function removeInstallerFiles(InstallerService $installer): RedirectResponse
    {
        $installer->removeInstallerFiles();

        return redirect()->route('admin.installation.show');
    }
}
