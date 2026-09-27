<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks (run by `php artisan schedule:work` or a cron entry)
|--------------------------------------------------------------------------
*/

// Permanently purge soft-deleted students past the retention period.
Schedule::command('model:prune')->dailyAt('02:00')->onOneServer()->withoutOverlapping();

// Housekeeping for tokens, password resets and failed jobs.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
Schedule::command('auth:clear-resets')->everyFifteenMinutes()->onOneServer();
Schedule::command('queue:prune-failed --hours=168')->daily()->onOneServer();

/*
|--------------------------------------------------------------------------
| Operational commands
|--------------------------------------------------------------------------
*/

Artisan::command('school:create-admin {email} {--name=Administrator} {--send-reset-link}', function (string $email) {
    $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:users,email']]);

    if ($validator->fails()) {
        $this->error($validator->errors()->first('email'));

        return 1;
    }

    if ($this->option('send-reset-link')) {
        $password = Str::password(32);
    } else {
        $password = (string) $this->secret('Password (min. 10 characters, mixed case and a number)');

        $check = Validator::make(['password' => $password], ['password' => ['required', PasswordRule::defaults()]]);

        if ($check->fails()) {
            $this->error($check->errors()->first('password'));

            return 1;
        }
    }

    $user = User::create([
        'name' => (string) $this->option('name'),
        'email' => mb_strtolower($email),
        'password' => $password,
        'is_active' => true,
    ]);
    $user->assignRole(Role::Admin->value);

    if ($this->option('send-reset-link')) {
        Password::broker()->sendResetLink(['email' => $user->email]);
        $this->info('Administrator created; a password setup link was emailed.');
    } else {
        $this->info('Administrator created.');
    }

    return 0;
})->purpose('Create an administrator account (use after deploying to a fresh database)');
