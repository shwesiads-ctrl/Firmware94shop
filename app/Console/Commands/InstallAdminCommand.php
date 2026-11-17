<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * The ONLY supported way to create the first Super Admin. There is
 * deliberately no public registration route for admin accounts (master
 * prompt §5 Module C). Safe to re-run: if a Super Admin already exists it
 * refuses unless --force is passed.
 */
class InstallAdminCommand extends Command
{
    protected $signature = 'f94:install-admin {--email=} {--name=} {--force}';
    protected $description = 'Create the initial Firmware94shop Super Admin account';

    public function handle(): int
    {
        $existing = User::role('super-admin')->exists();

        if ($existing && ! $this->option('force')) {
            $this->error('A Super Admin already exists. Use --force to create another one.');
            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Admin name');
        $email = $this->option('email') ?: $this->ask('Admin email');
        $password = $this->secret('Admin password (min 12 chars)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $admin->assignRole('super-admin');
        Wallet::create(['user_id' => $admin->id, 'balance' => 0]);

        $this->info("Super Admin created: {$admin->email}");
        return self::SUCCESS;
    }
}
