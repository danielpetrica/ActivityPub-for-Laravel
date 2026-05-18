<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class ResetAdmin extends Command
{
    protected $signature = 'app:reset-admin {--password= : Custom password for the admin user}';

    protected $description = 'Reset an admin user password and send a new email verification link.';

    public function handle(): int
    {
        $password = $this->resolvePassword();

        $users = User::all();

        if ($users->isNotEmpty()) {
            $user = $this->selectUser(users: $users);
        } else {
            $user = $this->createUser(password: $password);
        }

        $user->update([
            'password' => $password,
            'email_verified_at' => null,
        ]);

        $user->sendEmailVerificationNotification();

        $this->info(string: "Admin user '{$user->email}' has been reset. Verification email sent!");

        return self::SUCCESS;
    }

    protected function resolvePassword(): string
    {
        if ($this->option(key: 'password')) {
            return $this->option(key: 'password');
        }

        return promptPassword(
            label: 'Enter new password for the admin user',
            placeholder: 'password',
            default: 'password',
            required: 'The password is required.',
        );
    }

    protected function selectUser(Collection $users): User
    {
        if (! $this->input->isInteractive()) {
            return $users->first();
        }

        $options = $users->mapWithKeys(callback: fn (User $user) => [
            $user->id => "{$user->name} <{$user->email}>",
        ])->all();

        $selectedUserId = select(
            label: 'Which user do you want to reset?',
            options: $options,
            default: $users->first()->id,
            scroll: 15,
        );

        return User::findOrFail(id: $selectedUserId);
    }

    protected function createUser(string $password): User
    {
        if (! $this->input->isInteractive()) {
            return User::create([
                'name' => 'Admin',
                'email' => 'admin@danielpetrica.com',
                'password' => $password,
            ]);
        }

        $email = text(
            label: 'No users found. Enter email for the new admin user',
            placeholder: 'admin@danielpetrica.com',
            default: 'admin@danielpetrica.com',
            required: 'The email is required.',
            validate: ['email' => 'required|email'],
        );

        return User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => $password,
        ]);
    }
}
