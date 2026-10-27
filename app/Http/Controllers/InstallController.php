<?php

namespace App\Http\Controllers;

use App\Install\InstallerService;
use App\Install\PhpBinary;
use App\Install\ReleaseLayout;
use App\Install\RequirementsChecker;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The web install wizard. Each POST does one step's work (each well inside
 * a 30-second PHP limit) and records progress, so a refresh resumes at the
 * current step.
 */
class InstallController extends Controller
{
    public function __construct(private readonly InstallerService $installer) {}

    public function show(Request $request): Response
    {
        $progress = $this->installer->progress();
        $step = $progress['step'];

        return Inertia::render('install/index', [
            'step' => $step,
            'steps' => InstallerService::STEPS,
            'guidesUrl' => config('install.guides_url'),
            ...$this->props($step, $progress['data'], $request),
        ]);
    }

    public function store(Request $request, string $step): RedirectResponse|Response
    {
        $current = $this->installer->progress()['step'];

        if ($step !== $current) {
            return redirect()->route('install.show');
        }

        return match ($step) {
            'welcome' => $this->next('requirements'),
            'requirements' => $this->passChecks('requirements', 'permissions'),
            'permissions' => $this->passChecks('permissions', 'database'),
            'database' => $this->database($request),
            'migrate' => $this->migrate(),
            'app' => $this->application($request),
            'mail' => $this->mail($request),
            'admin' => $this->admin($request),
            'workspace' => $this->workspace($request),
            'cron' => $this->next('finish'),
            'finish' => $this->finish($request),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function props(string $step, array $data, Request $request): array
    {
        return match ($step) {
            'requirements', 'permissions' => (function () use ($step) {
                $items = $this->installer->checks($step === 'requirements')[$step];

                return ['checks' => $items, 'summary' => RequirementsChecker::summary($items)];
            })(),
            'database' => ['database' => collect($this->installer->databaseDefaults())->except('password')->all()],
            'migrate' => collect($this->installer->migrationStatus())->except('pending')->all(),
            'app' => [
                'url' => $data['url'] ?? $request->getSchemeAndHttpHost(),
                'timezone' => $data['timezone'] ?? 'Europe/London',
                'timezones' => DateTimeZone::listIdentifiers(),
            ],
            'cron' => [
                'cronLine' => PhpBinary::cronLine(PhpBinary::detect(), ReleaseLayout::cronArtisan(base_path())),
                'queueRunner' => config('queue.runner'),
            ],
            'finish' => ['installerFiles' => array_map('basename', $this->installer->installerFiles())],
            default => [],
        };
    }

    private function next(string $step): RedirectResponse
    {
        $this->installer->advance($step);

        return redirect()->route('install.show');
    }

    private function passChecks(string $group, string $next): RedirectResponse
    {
        $items = $this->installer->checks($group === 'requirements')[$group];

        if (RequirementsChecker::summary($items) === RequirementsChecker::FAIL) {
            throw ValidationException::withMessages(['checks' => 'Fix the failed items before continuing. Each one shows what to change in your control panel.']);
        }

        return $this->next($next);
    }

    private function database(Request $request): RedirectResponse
    {
        $db = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'confirm_not_empty' => ['boolean'],
        ]);

        $result = $this->installer->testDatabase($db);

        if (! $result['ok']) {
            throw ValidationException::withMessages(['host' => $result['error']]);
        }

        if ($result['tables'] > 0 && ! $request->boolean('confirm_not_empty')) {
            throw ValidationException::withMessages([
                'confirm_not_empty' => "This database already has {$result['tables']} tables. Use an empty database, or tick the box to install into it anyway.",
            ]);
        }

        $this->installer->saveDatabase($db);

        return $this->next('migrate');
    }

    private function migrate(): RedirectResponse
    {
        if ($this->installer->runNextMigration() === null) {
            return $this->next('app');
        }

        return redirect()->route('install.show');
    }

    private function application(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:255'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
        ]);

        $this->installer->saveApplication($data['url'], $data['timezone']);
        $this->installer->advance('mail', $data);

        return redirect()->route('install.show');
    }

    private function mail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mailer' => ['required', Rule::in(['smtp', 'log'])],
            'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'from_address' => ['required_if:mailer,smtp', 'nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'test_to' => ['required_if:mailer,smtp', 'nullable', 'email', 'max:255'],
        ]);

        $this->installer->saveMail($data);

        if ($data['mailer'] === 'smtp') {
            try {
                $this->installer->sendTestMail((string) $data['test_to']);
            } catch (Throwable $e) {
                throw ValidationException::withMessages(['host' => 'The test email could not be sent: '.$e->getMessage()]);
            }
        }

        return $this->next('admin');
    }

    private function admin(Request $request): RedirectResponse
    {
        $data = $request->validate(InstallerService::superAdminRules());
        $user = $this->installer->createSuperAdmin($data['name'], $data['email'], $data['password']);
        $this->installer->advance('workspace', ['admin_id' => $user->id]);

        return redirect()->route('install.show');
    }

    private function workspace(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $this->installer->createWorkspace($data['name']);

        return $this->next('cron');
    }

    private function finish(Request $request): Response
    {
        $adminId = $this->installer->progress()['data']['admin_id'] ?? null;
        $this->installer->finish(User::query()->find($adminId));

        if ($request->boolean('remove_installer_files')) {
            $this->installer->removeInstallerFiles();
        }

        return Inertia::render('install/complete', [
            'loginUrl' => route('login'),
            'installerFiles' => array_map('basename', $this->installer->installerFiles()),
        ]);
    }
}
