<?php

namespace Tests\Concerns;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;

/**
 * Small builders for common school scenarios in tests.
 */
trait BuildsSchool
{
    protected function openTerm(array $attributes = []): AcademicTerm
    {
        return AcademicTerm::factory()->current()->create($attributes);
    }

    protected function section(array $attributes = [], ?AcademicTerm $term = null, ?User $teacher = null): Section
    {
        return Section::factory()->create([
            'academic_term_id' => ($term ?? $this->openTerm())->id,
            'teacher_id' => ($teacher ?? User::factory()->teacher()->create())->id,
            ...$attributes,
        ]);
    }

    protected function activeStudent(array $attributes = [], bool $withAccount = false): Student
    {
        $factory = Student::factory();

        return ($withAccount ? $factory->withAccount() : $factory)->create($attributes);
    }

    protected function enroll(Student $student, Section $section): Enrollment
    {
        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'section_id' => $section->id,
        ]);
    }

    /**
     * Record a completed (passed or failed) enrollment in a past term.
     */
    protected function completedCourse(Student $student, Course $course, float $finalScore = 85.0): Enrollment
    {
        $section = Section::factory()->create([
            'course_id' => $course->id,
            'academic_term_id' => AcademicTerm::factory()->past()->create()->id,
        ]);

        return Enrollment::factory()->completed($finalScore, $finalScore >= 60 ? 'B' : 'F')->create([
            'student_id' => $student->id,
            'section_id' => $section->id,
        ]);
    }
}
