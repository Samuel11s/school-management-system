<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function __construct(private readonly SectionPolicy $sections) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Staff responsible for the class, or the student who received the grade.
     */
    public function view(User $user, Grade $grade): bool
    {
        $enrollment = $grade->enrollment;

        return $this->sections->manageGrades($user, $enrollment->section)
            || $enrollment->student?->user_id === $user->id;
    }

    public function update(User $user, Grade $grade): bool
    {
        return $this->sections->manageGrades($user, $grade->enrollment->section);
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $this->update($user, $grade);
    }
}
