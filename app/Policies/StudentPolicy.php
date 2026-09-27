<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewStudents->value);
    }

    /**
     * Admins see everyone; teachers see students in their classes; students see themselves.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->can(Permission::ManageStudents->value)) {
            return true;
        }

        if ($student->user_id !== null && $student->user_id === $user->id) {
            return true;
        }

        return $user->can(Permission::ViewStudents->value)
            && $student->enrollments()
                ->whereIn('status', [EnrollmentStatus::Enrolled->value, EnrollmentStatus::Completed->value])
                ->whereHas('section', fn ($q) => $q->where('teacher_id', $user->id))
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageStudents->value);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can(Permission::ManageStudents->value);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can(Permission::ManageStudents->value);
    }

    /**
     * Sensitive contact and guardian details are limited to administrators
     * and the student themselves.
     */
    public function viewSensitive(User $user, Student $student): bool
    {
        return $user->can(Permission::ManageStudents->value)
            || ($student->user_id !== null && $student->user_id === $user->id);
    }
}
