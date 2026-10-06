<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The way the first account is made.
 *
 * Public registration is closed, so a fresh install has no way to produce an
 * account and nobody can sign in. It is a console command rather than a
 * seeder: a seeded account means a password in version control, the same on
 * every install, with nothing to stop it surviving into production.
 */
class CreateAccount extends Command
{
    // The password is not an option. Anything typed on a command line lands in
    // the shell history and the process list, where others can read it.
    protected $signature = 'dtr:user
                            {--name= : The person\'s name}
                            {--email= : The address they sign in with}
                            {--role=admin : admin or hr}';

    protected $description = 'Create an admin or HR account';

    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error("[{$this->option('role')}] is not a role. Use admin or hr.");

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm the password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);

        $this->info("Account [{$email}] created with the {$role->getLabel()} role.");

        return self::SUCCESS;
    }
}
