<?php

namespace App\Console\Commands;

use App\Install\InstallerService;
use App\Install\InstallState;
use App\Install\RequirementsChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class InstallApp extends Command
{
    protected $signature = 'app:install
        {--db-host= : Database host (defaults to DB_HOST or CLOUDRON_MYSQL_HOST)}
        {--db-port= : Database port}
        {--db-database= : Database name}
        {--db-username= : Database user}
        {--db-password= : Database password}
        {--url= : Site address, for example https://workdash.example.org}
        {--timezone=Europe/London : Timezone}
        {--mail-host= : SMTP host (mail is logged when omitted)}
        {--mail-port=587 : SMTP port}
        {--mail-username= : SMTP user}
        {--mail-password= : SMTP password}
        {--mail-scheme= : smtp or smtps}
        {--mail-from= : From address}
        {--admin-name= : Super Admin name}
        {--admin-email= : Super Admin email}
        {--admin-password= : Super Admin password (prompted when omitted; at least 12 characters)}
        {--workspace= : Name of the first workspace}
        {--skip-requirements : Install even if a requirement fails}';

    protected $description = 'Install NPO.WorkDash on a blank database (same steps as the web install wizard)';

    public function handle(InstallerService $installer, InstallState $state): int
    {
        if ($state->isInstalled()) {
            $this->error('NPO.WorkDash is already installed. The installer cannot change an existing installation.');

            return self::FAILURE;
        }

        $installer->prepareEnvironment();

        $checks = $installer->checks();
        $items = [...$checks['requirements'], ...$checks['permissions']];

        foreach ($items as $item) {
            if ($item['status'] !== RequirementsChecker::PASS) {
                $this->warn(strtoupper($item['status']).": {$item['label']}: {$item['detail']}");
            }
        }

        if (RequirementsChecker::summary($items) === RequirementsChecker::FAIL && ! $this->option('skip-requirements')) {
            $this->error('Fix the failed requirements above, or rerun with --skip-requirements.');

            return self::FAILURE;
        }

        try {
            $this->installDatabase($installer);

            $this->components->task('Running migrations', function () use ($installer) {
                while ($installer->runNextMigration() !== null) {
                    //
                }
            });

            $installer->saveApplication(
                (string) ($this->option('url') ?: getenv('CLOUDRON_APP_ORIGIN') ?: $this->ask('Site address (https://...)', (string) config('app.url'))),
                (string) $this->option('timezone'),
            );

            $installer->saveMail($this->option('mail-host') ? [
                'mailer' => 'smtp',
                'host' => $this->option('mail-host'),
                'port' => $this->option('mail-port'),
                'username' => $this->option('mail-username'),
                'password' => $this->option('mail-password'),
                'scheme' => $this->option('mail-scheme'),
                'from_address' => $this->option('mail-from'),
            ] : ['mailer' => 'log']);

            $admin = $this->superAdmin();

            if ($admin === null) {
                return self::FAILURE;
            }

            $user = $installer->createSuperAdmin($admin['name'], $admin['email'], $admin['password']);
            $installer->createWorkspace((string) ($this->option('workspace') ?: text('Name of the first workspace (your charity)', required: true)));
            $installer->finish($user);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Installed NPO.WorkDash '.config('app.version').". Sign in as {$user->email}; you will be asked to set up two-factor authentication.");

        return self::SUCCESS;
    }

    private function installDatabase(InstallerService $installer): void
    {
        $given = array_filter([
            'host' => $this->option('db-host'),
            'port' => $this->option('db-port'),
            'database' => $this->option('db-database'),
            'username' => $this->option('db-username'),
            'password' => $this->option('db-password'),
        ], fn ($value) => $value !== null);

        $db = array_merge($installer->databaseDefaults(), $given);
        $result = $installer->testDatabase($db);

        if (! $result['ok']) {
            throw new RuntimeException((string) $result['error']);
        }

        if ($given !== []) {
            $installer->saveDatabase($db);
        }
    }

    /**
     * @return array{name: string, email: string, password: string}|null
     */
    private function superAdmin(): ?array
    {
        $input = [
            'name' => $this->option('admin-name') ?: text('Super Admin name', required: true),
            'email' => $this->option('admin-email') ?: text('Super Admin email', required: true),
        ];

        $input['password'] = $this->option('admin-password') ?: password('Super Admin password (12+ characters)', required: true);
        $input['password_confirmation'] = $this->option('admin-password') ?: password('Confirm the password', required: true);

        $validator = Validator::make($input, InstallerService::superAdminRules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return null;
        }

        return ['name' => (string) $input['name'], 'email' => (string) $input['email'], 'password' => (string) $input['password']];
    }
}
