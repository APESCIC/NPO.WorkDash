<?php

namespace App\Install;

use App\Models\Installation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use PDO;
use PDOException;
use RuntimeException;

/**
 * The one install code path, driven step by step by the web wizard and in a
 * single run by `php artisan app:install`.
 */
class InstallerService
{
    public const STEPS = ['welcome', 'requirements', 'permissions', 'database', 'migrate', 'app', 'mail', 'admin', 'workspace', 'cron', 'finish'];

    public function __construct(
        private readonly InstallState $state,
        private readonly EnvironmentFile $env,
    ) {}

    /**
     * Prepare .env on a fresh host: create it from .env.example with a new
     * APP_KEY and production defaults. Returns true when it changed anything.
     */
    public function prepareEnvironment(): bool
    {
        if ($this->env->exists() && filled($this->env->get('APP_KEY'))) {
            return false;
        }

        $this->env->ensureExists();
        $this->env->set([
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'LOG_LEVEL' => 'warning',
            'MAIL_MAILER' => 'log',
        ]);

        return true;
    }

    // --- Progress (resumable wizard) -----------------------------------------

    /**
     * @return array{step: string, data: array<string, mixed>}
     */
    public function progress(): array
    {
        $saved = is_file($this->progressPath()) ? json_decode((string) file_get_contents($this->progressPath()), true) : null;

        return [
            'step' => in_array($saved['step'] ?? null, self::STEPS, true) ? $saved['step'] : 'welcome',
            'data' => is_array($saved['data'] ?? null) ? $saved['data'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  non-secret values to remember
     */
    public function advance(string $to, array $data = []): void
    {
        $progress = $this->progress();
        $progress['step'] = $to;
        $progress['data'] = array_merge($progress['data'], $data);

        if (! is_dir(dirname($this->progressPath()))) {
            mkdir(dirname($this->progressPath()), 0755, true);
        }

        file_put_contents($this->progressPath(), json_encode($progress, JSON_PRETTY_PRINT));
    }

    public function progressPath(): string
    {
        return storage_path('app/install-progress.json');
    }

    // --- Requirements --------------------------------------------------------

    /**
     * @return array{requirements: list<array<string, mixed>>, permissions: list<array<string, mixed>>}
     */
    public function checks(bool $outbound = true): array
    {
        $items = AppRequirements::checker($outbound)->run();
        $isPermission = fn (array $item) => str_starts_with($item['key'], 'writable_');

        return [
            'requirements' => array_values(array_filter($items, fn (array $item) => ! $isPermission($item))),
            'permissions' => array_values(array_filter($items, $isPermission)),
        ];
    }

    // --- Database ------------------------------------------------------------

    /**
     * Connection settings from the environment, falling back to the Cloudron
     * MySQL addon variables.
     *
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    public function databaseDefaults(): array
    {
        $mysql = config('database.connections.mysql');

        return [
            'host' => (string) (getenv('CLOUDRON_MYSQL_HOST') ?: $mysql['host']),
            'port' => (string) (getenv('CLOUDRON_MYSQL_PORT') ?: $mysql['port']),
            'database' => (string) (getenv('CLOUDRON_MYSQL_DATABASE') ?: $mysql['database']),
            'username' => (string) (getenv('CLOUDRON_MYSQL_USERNAME') ?: $mysql['username']),
            'password' => (string) (getenv('CLOUDRON_MYSQL_PASSWORD') ?: $mysql['password']),
        ];
    }

    /**
     * Connect with the given settings and report version, charset and
     * whether the database already holds tables.
     *
     * @param  array{host: string, port: string|int, database: string, username: string, password?: string|null}  $db
     * @return array{ok: bool, error: string|null, items: list<array<string, mixed>>, tables: int}
     */
    public function testDatabase(array $db): array
    {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
                $db['username'],
                $db['password'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => 'Could not connect: '.$e->getMessage(), 'items' => [], 'tables' => 0];
        }

        $items = AppRequirements::checker(false)->checkDatabase($pdo);
        $tables = (int) $pdo->query('select count(*) from information_schema.tables where table_schema = '.$pdo->quote($db['database']))->fetchColumn();
        $failed = RequirementsChecker::summary($items) === RequirementsChecker::FAIL;

        return [
            'ok' => ! $failed,
            'error' => $failed ? collect($items)->firstWhere('status', RequirementsChecker::FAIL)['detail'] : null,
            'items' => $items,
            'tables' => $tables,
        ];
    }

    /**
     * Write the connection to .env and use it for the rest of this request.
     *
     * @param  array{host: string, port: string|int, database: string, username: string, password?: string|null}  $db
     */
    public function saveDatabase(array $db): void
    {
        $this->env->set([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
        ]);

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => (string) $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => (string) ($db['password'] ?? ''),
        ]);

        DB::purge('mysql');
    }

    // --- Migrations ----------------------------------------------------------

    /**
     * @return array{total: int, ran: int, pending: list<string>}
     */
    public function migrationStatus(): array
    {
        $migrator = $this->migrator();
        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $pending = array_values(array_diff(array_keys($files), $ran));

        return ['total' => count($files), 'ran' => count($files) - count($pending), 'pending' => $pending];
    }

    /**
     * Run the next pending migration only. Returns its name, or null when
     * none are left.
     */
    public function runNextMigration(): ?string
    {
        $migrator = $this->migrator();
        $repository = $migrator->getRepository();

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $next = array_values(array_diff(array_keys($files), $repository->getRan()))[0] ?? null;

        if ($next === null) {
            return null;
        }

        $migrator->runPending([$files[$next]], ['step' => true]);

        return $next;
    }

    private function migrator(): Migrator
    {
        return app('migrator');
    }

    // --- Application, mail, accounts -----------------------------------------

    public function saveApplication(string $url, string $timezone): void
    {
        $this->env->set(['APP_URL' => rtrim($url, '/'), 'APP_TIMEZONE' => $timezone]);
        config(['app.url' => rtrim($url, '/'), 'app.timezone' => $timezone]);
    }

    /**
     * @param  array{mailer: string, host?: string|null, port?: string|int|null, username?: string|null, password?: string|null, scheme?: string|null, from_address?: string|null, from_name?: string|null}  $mail
     */
    public function saveMail(array $mail): void
    {
        if ($mail['mailer'] !== 'smtp') {
            $this->env->set(['MAIL_MAILER' => 'log']);
            config(['mail.default' => 'log']);

            return;
        }

        $this->env->set([
            'MAIL_MAILER' => 'smtp',
            'MAIL_SCHEME' => $mail['scheme'] ?? null,
            'MAIL_HOST' => (string) $mail['host'],
            'MAIL_PORT' => (string) $mail['port'],
            'MAIL_USERNAME' => $mail['username'] ?? null,
            'MAIL_PASSWORD' => $mail['password'] ?? null,
            'MAIL_FROM_ADDRESS' => (string) $mail['from_address'],
            'MAIL_FROM_NAME' => (string) ($mail['from_name'] ?? config('app.name')),
        ]);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $mail['scheme'] ?? null,
            'mail.mailers.smtp.host' => $mail['host'],
            'mail.mailers.smtp.port' => (int) $mail['port'],
            'mail.mailers.smtp.username' => $mail['username'] ?? null,
            'mail.mailers.smtp.password' => $mail['password'] ?? null,
            'mail.from.address' => $mail['from_address'],
            'mail.from.name' => $mail['from_name'] ?? config('app.name'),
        ]);
    }

    public function sendTestMail(string $to): void
    {
        Mail::raw('This is a test email from the '.config('app.name').' installer. Mail is working.', function ($message) use ($to) {
            $message->to($to)->subject(config('app.name').' installer test email');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function superAdminRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->uncompromised()],
        ];
    }

    public function createSuperAdmin(string $name, string $email, string $password): User
    {
        $user = new User(['name' => $name, 'email' => strtolower($email), 'password' => $password]);
        $user->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();

        return $user;
    }

    public function createWorkspace(string $name): Tenant
    {
        if (Tenant::query()->exists()) {
            throw new RuntimeException('A workspace already exists.');
        }

        return Tenant::query()->forceCreate(['id' => 1, 'name' => $name, 'slug' => Str::slug($name) ?: 'workspace']);
    }

    public function finish(?User $installedBy): Installation
    {
        if ($this->state->hasDatabaseRecord()) {
            throw new RuntimeException('This app is already installed.');
        }

        $installation = Installation::query()->create([
            'installation_id' => (string) Str::uuid(),
            'installed_version' => (string) config('app.version'),
            'installed_by' => $installedBy?->id,
            'installed_at' => now(),
        ]);

        $this->state->writeLock();
        @unlink($this->progressPath());

        return $installation;
    }

    /**
     * Installer files served from the web root that can be deleted after install.
     *
     * @return list<string>
     */
    public function installerFiles(): array
    {
        return array_values(array_filter(
            [public_path('check.php'), public_path('RequirementsChecker.php')],
            fn (string $path) => is_file($path),
        ));
    }

    public function removeInstallerFiles(): int
    {
        $removed = 0;

        foreach ($this->installerFiles() as $path) {
            $removed += @unlink($path) ? 1 : 0;
        }

        return $removed;
    }
}
