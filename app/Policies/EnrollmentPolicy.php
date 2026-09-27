<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Listing is always scoped with Enrollment::visibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($enrollment->section->isTaughtBy($user)) {
            return true;
        }

        return $enrollment->student?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageEnrollments->value);
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->can(Permission::ManageEnrollments->value);
    }
}
