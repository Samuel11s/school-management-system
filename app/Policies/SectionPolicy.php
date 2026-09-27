<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    /**
     * Everyone may list classes; the list itself is scoped with Section::visibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Class details: admins, the assigned teacher and enrolled students.
     */
    public function view(User $user, Section $section): bool
    {
        if ($user->isAdmin() || $section->isTaughtBy($user)) {
            return true;
        }

        return $section->enrollments()
            ->whereHas('student', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSections->value);
    }

    public function update(User $user, Section $section): bool
    {
        return $user->can(Permission::ManageSections->value);
    }

    public function delete(User $user, Section $section): bool
    {
        return $user->can(Permission::ManageSections->value);
    }

    /**
     * See the class list (roster) with every student.
     */
    public function viewRoster(User $user, Section $section): bool
    {
        return $user->isAdmin()
            || ($user->can(Permission::ViewEnrollments->value) && $section->isTaughtBy($user));
    }

    public function manageEnrollments(User $user, Section $section): bool
    {
        return $user->can(Permission::ManageEnrollments->value);
    }

    public function manageGrades(User $user, Section $section): bool
    {
        return $user->can(Permission::ManageGrades->value)
            && ($user->isAdmin() || $section->isTaughtBy($user));
    }

    public function manageAttendance(User $user, Section $section): bool
    {
        return $user->can(Permission::ManageAttendance->value)
            && ($user->isAdmin() || $section->isTaughtBy($user));
    }
}
