<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

/**
 * Assessments follow the permissions of the class they belong to.
 */
class AssessmentPolicy
{
    public function __construct(private readonly SectionPolicy $sections) {}

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->sections->view($user, $assessment->section);
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $this->sections->manageGrades($user, $assessment->section);
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $this->sections->manageGrades($user, $assessment->section);
    }
}
