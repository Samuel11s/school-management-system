<?php

namespace App\Exceptions;

use App\Models\Section;
use App\Models\Student;

class EnrollmentException extends DomainRuleException
{
    public static function studentNotEligible(Student $student): self
    {
        return new self(
            "Only active students can be enrolled; {$student->full_name} is {$student->status->label()}.",
            'student_id',
        );
    }

    public static function sectionNotOpen(Section $section): self
    {
        return new self("Class {$section->name} is not open for enrollment.", 'section_id');
    }

    public static function enrollmentWindowClosed(Section $section): self
    {
        $term = $section->term;

        return new self(sprintf(
            'Enrollment for %s is only possible between %s and %s.',
            $term->name,
            $term->enrollment_opens_on->toFormattedDateString(),
            $term->enrollment_closes_on->toFormattedDateString(),
        ), 'section_id');
    }

    public static function alreadyEnrolled(): self
    {
        return new self('The student is already enrolled in this class.', 'student_id');
    }

    public static function alreadyCompleted(): self
    {
        return new self('The student has already completed this class.', 'student_id');
    }

    public static function alreadyEnrolledInCourse(string $sectionName): self
    {
        return new self("The student is already enrolled in this course this term (class {$sectionName}).", 'student_id');
    }

    public static function sectionFull(Section $section): self
    {
        return new self("Class {$section->name} is full ({$section->capacity} seats).", 'section_id');
    }

    /**
     * @param  list<string>  $missingCourseCodes
     */
    public static function prerequisitesMissing(array $missingCourseCodes): self
    {
        return new self(
            'Missing prerequisite course(s): '.implode(', ', $missingCourseCodes).'.',
            'student_id',
        );
    }

    public static function notActive(): self
    {
        return new self('Only active enrollments can be changed.', 'status');
    }
}
