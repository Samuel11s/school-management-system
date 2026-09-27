<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Staff and student account administration.
 */
final class UserService
{
    /**
     * Create an account with an unusable random password and email the user
     * a link to choose their own. Administrators never handle passwords.
     *
     * @param  array{name: string, email: string}  $data
     */
    public function create(array $data, Role $role): User
    {
        $user = DB::transaction(function () use ($data, $role) {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => Str::password(32),
                'is_active' => true,
            ]);
            $user->syncRoles([$role->value]);

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $user->email]);

        return $user;
    }

    /**
     * @param  array{name: string, email: string}  $data
     */
    public function update(User $user, array $data, Role $role): User
    {
        return DB::transaction(function () use ($user, $data, $role) {
            $user->fill(['name' => $data['name'], 'email' => mb_strtolower($data['email'])])->save();
            $user->syncRoles([$role->value]);
            $user->student?->forceFill(['email' => $user->email])->save();

            return $user;
        });
    }

    /**
     * Deactivating an account also revokes all of its API tokens.
     */
    public function setActive(User $user, bool $active): User
    {
        $user->forceFill(['is_active' => $active])->save();

        if (! $active) {
            $user->tokens()->delete();
        }

        return $user;
    }
}
