<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * The course catalog is visible to every signed-in user.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Course $course): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCourses->value);
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can(Permission::ManageCourses->value);
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can(Permission::ManageCourses->value);
    }
}
