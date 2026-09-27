<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model) || $user->can(Permission::ManageUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    /**
     * Administrators cannot deactivate or delete their own account, which
     * could otherwise lock everyone out.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::ManageUsers->value) && ! $user->is($model);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }
}
