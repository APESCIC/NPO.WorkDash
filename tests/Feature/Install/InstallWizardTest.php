<?php

use App\Install\InstallerService;
use App\Install\InstallState;
use App\Models\Installation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->uninstall();
    $this->envDir = useTemporaryEnvironmentFile();
    $this->installer = app(InstallerService::class);
    @unlink($this->installer->progressPath());
    $this->pwnedRange = '';
    Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response($this->pwnedRange)]);
});

afterEach(function () {
    @unlink($this->installer->progressPath());
    File::deleteDirectory($this->envDir);
});

test('an uninstalled app sends every page to the install wizard', function () {
    $this->get('/')->assertRedirect('/install');
    $this->get('/login')->assertRedirect('/install');
    $this->get('/install')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('install/index')
        ->where('step', 'welcome')
        ->where('steps', InstallerService::STEPS));
});

test('refreshing the browser resumes at the current step', function () {
    $this->post('/install/welcome')->assertRedirect('/install');

    $this->get('/install')->assertInertia(fn (Assert $page) => $page->where('step', 'requirements'));
    $this->get('/install')->assertInertia(fn (Assert $page) => $page->where('step', 'requirements'));
});

test('posting a step other than the current one does nothing', function () {
    $this->post('/install/admin', ['name' => 'Sneaky'])->assertRedirect('/install');

    expect(User::query()->count())->toBe(0)
        ->and($this->installer->progress()['step'])->toBe('welcome');
});

test('the database step rejects a connection that fails', function () {
    $this->installer->advance('database');

    $this->post('/install/database', [...testDatabaseSettings(), 'password' => 'wrong-password'])
        ->assertSessionHasErrors('host');

    expect($this->installer->progress()['step'])->toBe('database');
});

test('the database step warns when the database is not empty', function () {
    $this->installer->advance('database');

    $this->post('/install/database', testDatabaseSettings())->assertSessionHasErrors('confirm_not_empty');

    $this->post('/install/database', [...testDatabaseSettings(), 'confirm_not_empty' => true])->assertRedirect('/install');

    expect($this->installer->progress()['step'])->toBe('migrate')
        ->and(file_get_contents($this->envDir.'/.env'))->toContain('DB_DATABASE='.config('database.connections.mysql.database'));
});

test('migrations run one per request with progress', function () {
    $this->installer->advance('migrate');
    $this->get('/install')->assertInertia(fn (Assert $page) => $page
        ->where('step', 'migrate')
        ->where('total', fn ($total) => $total > 0)
        ->where('ran', fn ($ran) => $ran > 0));

    $this->post('/install/migrate')->assertRedirect('/install');

    expect($this->installer->progress()['step'])->toBe('app');
});

test('the mail step sends a test email over SMTP', function () {
    Mail::fake();
    $this->installer->advance('mail');

    $this->post('/install/mail', [
        'mailer' => 'smtp',
        'host' => 'smtp.example.test',
        'port' => 587,
        'scheme' => 'smtp',
        'from_address' => 'hello@example.test',
        'test_to' => 'admin@example.test',
    ])->assertRedirect('/install');

    expect($this->installer->progress()['step'])->toBe('admin')
        ->and(file_get_contents($this->envDir.'/.env'))->toContain('MAIL_HOST=smtp.example.test');
});

test('the Super Admin password must be at least 12 characters', function () {
    $this->installer->advance('admin');

    $this->post('/install/admin', [
        'name' => 'Example Admin',
        'email' => 'admin@example.test',
        'password' => 'short-pass1',
        'password_confirmation' => 'short-pass1',
    ])->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe(0);
});

test('a Super Admin password found in a breach is rejected when online', function () {
    $password = 'this-password-was-breached';
    $hash = strtoupper(sha1($password));
    $this->pwnedRange = substr($hash, 5).":42\r\n";
    $this->installer->advance('admin');

    $this->post('/install/admin', [
        'name' => 'Example Admin',
        'email' => 'admin@example.test',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe(0);
});

test('the cron step shows the cron line with the detected PHP binary', function () {
    $this->installer->advance('cron');

    $this->get('/install')->assertInertia(fn (Assert $page) => $page
        ->where('step', 'cron')
        ->where('cronLine', fn (string $line) => str_starts_with($line, '* * * * * ')
            && str_ends_with($line, 'artisan schedule:run >> /dev/null 2>&1')));
});

test('completing the wizard creates the Super Admin and workspace 1 and locks the installer', function () {
    completeWizard();

    $admin = User::query()->sole();

    expect($admin->is_super_admin)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Tenant::query()->sole()->id)->toBe(1)
        ->and(Installation::query()->sole()->installed_by)->toBe($admin->id)
        ->and(is_file(config('install.lock_path')))->toBeTrue();

    $this->get('/install')->assertNotFound();
    $this->post('/install/welcome')->assertNotFound();
    $this->get('/login')->assertOk();
});

test('the finish step can remove the installer files', function () {
    $files = [public_path('check.php'), public_path('RequirementsChecker.php')];
    array_map(fn ($file) => file_put_contents($file, '<?php'), $files);

    try {
        $this->installer->advance('finish');
        $this->get('/install')->assertInertia(fn (Assert $page) => $page
            ->where('installerFiles', ['check.php', 'RequirementsChecker.php']));

        completeWizard(['remove_installer_files' => true])
            ->assertInertia(fn (Assert $page) => $page->component('install/complete')->where('installerFiles', []));

        foreach ($files as $file) {
            expect(is_file($file))->toBeFalse();
        }
    } finally {
        array_map(fn ($file) => @unlink($file), $files);
    }
});

test('deleting the lock file alone does not re-enable the installer', function () {
    completeWizard();

    unlink(config('install.lock_path'));
    app(InstallState::class)->forget();

    $this->get('/install')->assertNotFound();

    expect(is_file(config('install.lock_path')))->toBeTrue();
});

test('the wizard cannot change data once installed', function () {
    completeWizard();
    $before = [User::query()->count(), Tenant::query()->count(), Installation::query()->count()];

    $this->post('/install/admin', [
        'name' => 'Intruder',
        'email' => 'intruder@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertNotFound();

    $this->artisan('app:install', ['--no-interaction' => true, '--admin-email' => 'intruder@example.test'])->assertFailed();

    expect([User::query()->count(), Tenant::query()->count(), Installation::query()->count()])->toBe($before);
});
