<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use App\Models\UpdateRun;
use App\Models\UpdateStep;
use App\Models\User;
use App\Updates\Engine\UpdateEngine;
use App\Updates\Engine\UpdateInProgress;
use App\Updates\FeedClient;
use App\Updates\InvalidRelease;
use App\Updates\Preflight;
use App\Updates\UpdateFeed;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * The Super Admin updater. Updates only ever start when a Super Admin
 * confirms here (or runs app:update); the page then drives the run one
 * request (tick) at a time.
 */
class UpdateController extends Controller
{
    public function __construct(private readonly UpdateEngine $engine) {}

    public function index(Request $request): Response
    {
        $channel = (string) config('updates.channel');
        [$release, $error] = $this->latestRelease($channel);
        $active = $this->engine->active();

        return Inertia::render('admin/updates', [
            'version' => config('app.version'),
            'channel' => $channel,
            'release' => $release,
            'releaseError' => $error,
            'preflight' => $release ? Preflight::check($release, $this->engine->layout()) : [],
            'activeRun' => $active ? $this->present($active) : null,
            'history' => UpdateRun::query()->with('starter')->latest('id')->limit(15)->get()->map($this->present(...))->all(),
            'canRollback' => $active === null && $this->rollbackCandidate() !== null,
            'requiresCode' => $request->user()->hasEnabledTwoFactorAuthentication(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $this->confirmIdentity($request);
        [$release, $error] = $this->latestRelease((string) config('updates.channel'), fresh: true);

        if ($release === null) {
            throw ValidationException::withMessages(['password' => $error ?? 'There is no newer release to install.']);
        }

        if (! Preflight::passes(Preflight::check($release, $this->engine->layout()))) {
            throw ValidationException::withMessages(['password' => 'The pre-flight checks failed. Fix them before updating.']);
        }

        try {
            $this->engine->start($release, $user, 'web');
        } catch (UpdateInProgress $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        return redirect()->route('admin.updates.index');
    }

    public function tick(UpdateRun $run): RedirectResponse
    {
        $run = $this->engine->tick($run);
        $response = redirect()->route('admin.updates.index');
        $secret = $run->stateValue('maintenance_secret');

        return is_string($secret) && ! $run->isFinished()
            ? $response->withCookie(MaintenanceModeBypassCookie::create($secret))
            : $response;
    }

    public function rollback(Request $request): RedirectResponse
    {
        $user = $this->confirmIdentity($request);

        try {
            $this->engine->rollbackLatest($user, 'web');
        } catch (InvalidRelease|UpdateInProgress $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        return redirect()->route('admin.updates.index');
    }

    /**
     * Password and two-factor code, checked again before any change.
     */
    private function confirmIdentity(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $needsCode = $user->hasEnabledTwoFactorAuthentication();

        $request->validate([
            'password' => ['required', 'current_password:web'],
            'code' => [$needsCode ? 'required' : 'nullable', 'string'],
        ]);

        if ($needsCode && ! app(TwoFactorAuthenticationProvider::class)->verify(decrypt((string) $user->two_factor_secret), (string) $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'The two-factor authentication code is not valid.']);
        }

        return $user;
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function latestRelease(string $channel, bool $fresh = false): array
    {
        $key = "updates.latest.{$channel}";

        if ($fresh) {
            Cache::forget($key);
        }

        try {
            $feed = Cache::remember($key, now()->addMinutes(10), fn () => FeedClient::fromConfig(Installation::query()->value('installation_id'))->fetch($channel));
        } catch (InvalidRelease $e) {
            return [null, $e->getMessage()];
        }

        return [UpdateFeed::latestFor($feed, (string) config('app.version')), null];
    }

    private function rollbackCandidate(): ?UpdateRun
    {
        $run = UpdateRun::query()->where('status', UpdateRun::SUCCEEDED)->latest('id')->first();

        return $run && is_dir((string) $run->stateValue('previous_release')) ? $run : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(UpdateRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'fromVersion' => $run->from_version,
            'toVersion' => $run->to_version,
            'source' => $run->source,
            'startedBy' => $run->starter?->name,
            'startedAt' => $run->started_at?->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
            'durationSeconds' => $run->durationSeconds(),
            'error' => $run->error,
            'steps' => $run->steps->map(fn (UpdateStep $step) => [
                'position' => $step->position,
                'name' => $step->name,
                'status' => $step->status,
                'output' => $step->output,
            ])->all(),
        ];
    }
}
