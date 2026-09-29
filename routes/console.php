<?php

use App\Enums\Role;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

Artisan::command('school:create-admin {email} {--name=Administrator} {--send-reset-link} {--password-env= : Read the password from this environment variable instead of prompting} {--if-missing : Succeed without changes when the account already exists}', function (string $email) {
    $email = mb_strtolower($email);

    if ($this->option('if-missing') && User::query()->where('email', $email)->exists()) {
        $this->info('Administrator already exists; nothing to do.');

        return 0;
    }

    $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:users,email']]);

    if ($validator->fails()) {
        $this->error($validator->errors()->first('email'));

        return 1;
    }

    if ($this->option('send-reset-link')) {
        $password = Str::password(32);
    } else {
        // Environment variables keep the password out of shell history and process lists.
        $password = $this->option('password-env')
            ? (string) getenv((string) $this->option('password-env'))
            : (string) $this->secret('Password (min. 10 characters, mixed case and a number)');

        $check = Validator::make(['password' => $password], ['password' => ['required', PasswordRule::defaults()]]);

        if ($check->fails()) {
            $this->error($check->errors()->first('password'));

            return 1;
        }
    }

    $user = User::create([
        'name' => (string) $this->option('name'),
        'email' => $email,
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

Artisan::command('school:seed-demo', function () {
    if (Course::query()->exists()) {
        $this->info('The database already contains data; demo data was not loaded.');

        return 0;
    }

    // All-or-nothing: a failure must not leave a half-seeded database behind.
    DB::transaction(fn () => $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]));
    $this->warn('Demo data loaded. Demo accounts use the password "password": do not use this on a real school database.');

    return 0;
})->purpose('Load demo data into an empty database (for showcases only)');
