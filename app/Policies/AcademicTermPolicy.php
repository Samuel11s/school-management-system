<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AcademicTerm;
use App\Models\User;

class AcademicTermPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AcademicTerm $term): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageTerms->value);
    }

    public function update(User $user, AcademicTerm $term): bool
    {
        return $user->can(Permission::ManageTerms->value);
    }

    public function delete(User $user, AcademicTerm $term): bool
    {
        return $user->can(Permission::ManageTerms->value);
    }
}
