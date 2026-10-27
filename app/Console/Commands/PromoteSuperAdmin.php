<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteSuperAdmin extends Command
{
    protected $signature = 'app:super-admin {email : Email address of an existing user}';

    protected $description = 'Make an existing user a Super Admin';

    public function handle(): int
    {
        $user = User::query()->where('email', strtolower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->error('No user has that email address.');

            return self::FAILURE;
        }

        $user->forceFill(['is_super_admin' => true])->save();

        $this->info("{$user->email} is now a Super Admin. They will be asked to set up two-factor authentication at their next sign-in.");

        return self::SUCCESS;
    }
}
