<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\SectionStatus;
use App\Events\EnrollmentDropped;
use App\Events\StudentEnrolled;
use App\Exceptions\EnrollmentException;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment workflow: enroll, drop and complete, with all eligibility rules.
 */
final class EnrollmentService
{
    public function __construct(private readonly GradebookService $gradebook) {}

    /**
     * Enroll a student in a class, enforcing status, window, prerequisite,
     * duplicate and capacity rules.
     *
     * The section row is locked for the duration of the transaction so that
     * concurrent requests cannot overfill a class.
     *
     * @throws EnrollmentException
     */
    public function enroll(Student $student, Section $section, ?string $reason = null): Enrollment
    {
        $enrollment = DB::transaction(function () use ($student, $section, $reason) {
            /** @var Section $section */
            $section = Section::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            $section->load(['course.prerequisites', 'term']);
            $student->refresh();

            $this->assertEligible($student, $section);

            $existing = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('section_id', $section->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === EnrollmentStatus::Enrolled) {
                throw EnrollmentException::alreadyEnrolled();
            }

            if ($existing?->status === EnrollmentStatus::Completed) {
                throw EnrollmentException::alreadyCompleted();
            }

            $this->assertNotEnrolledInSameCourse($student, $section);

            if ($section->activeEnrollments()->count() >= $section->capacity) {
                throw EnrollmentException::sectionFull($section);
            }

            if ($existing !== null) {
                $existing->withStatusReason($reason ?? 'Re-enrolled')->fill([
                    'status' => EnrollmentStatus::Enrolled,
                    'enrolled_at' => now(),
                    'dropped_at' => null,
                ])->save();

                return $existing;
            }

            $enrollment = new Enrollment([
                'student_id' => $student->id,
                'section_id' => $section->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
            $enrollment->withStatusReason($reason)->save();

            return $enrollment;
        });

        StudentEnrolled::dispatch($enrollment);

        return $enrollment;
    }

    /**
     * @throws EnrollmentException
     */
    public function drop(Enrollment $enrollment, ?string $reason = null): Enrollment
    {
        if (! $enrollment->isActive()) {
            throw EnrollmentException::notActive();
        }

        $enrollment->withStatusReason($reason)->fill([
            'status' => EnrollmentStatus::Dropped,
            'dropped_at' => now(),
        ])->save();

        EnrollmentDropped::dispatch($enrollment);

        return $enrollment;
    }

    /**
     * Close an enrollment, freezing its final score and letter grade.
     *
     * @throws EnrollmentException
     */
    public function complete(Enrollment $enrollment, ?string $reason = null): Enrollment
    {
        if (! $enrollment->isActive()) {
            throw EnrollmentException::notActive();
        }

        $score = $this->gradebook->finalScore($enrollment);

        $enrollment->withStatusReason($reason)->fill([
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now(),
            'final_score' => $score,
            'letter_grade' => $this->gradebook->calculator()->letterFor($score),
        ])->save();

        return $enrollment;
    }

    /**
     * Reasons the student cannot currently enroll in the section (empty when eligible).
     *
     * @return list<string>
     */
    public function eligibilityErrors(Student $student, Section $section): array
    {
        try {
            $this->assertEligible($student, $section);
        } catch (EnrollmentException $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    /**
     * @throws EnrollmentException
     */
    private function assertEligible(Student $student, Section $section): void
    {
        if (! $student->status->canEnroll()) {
            throw EnrollmentException::studentNotEligible($student);
        }

        if ($section->status !== SectionStatus::Open) {
            throw EnrollmentException::sectionNotOpen($section);
        }

        if (! $section->term->isEnrollmentOpen()) {
            throw EnrollmentException::enrollmentWindowClosed($section);
        }

        if (in_array($section->course_id, $this->passedCourseIds($student), true)) {
            throw EnrollmentException::coursePassed($section->course->code);
        }

        $missing = $this->missingPrerequisites($student, $section);

        if ($missing !== []) {
            throw EnrollmentException::prerequisitesMissing($missing);
        }
    }

    /**
     * @return list<string> course codes of prerequisites not yet passed
     */
    public function missingPrerequisites(Student $student, Section $section): array
    {
        $prerequisites = $section->course->prerequisites;

        if ($prerequisites->isEmpty()) {
            return [];
        }

        $passedCourseIds = $this->passedCourseIds($student);

        return $prerequisites
            ->reject(fn ($course) => in_array($course->id, $passedCourseIds, true))
            ->pluck('code')
            ->values()
            ->all();
    }

    /**
     * Courses the student has completed with a passing final score.
     *
     * @return list<int>
     */
    private function passedCourseIds(Student $student): array
    {
        return Enrollment::query()
            ->join('sections', 'sections.id', '=', 'enrollments.section_id')
            ->where('enrollments.student_id', $student->id)
            ->where('enrollments.status', EnrollmentStatus::Completed->value)
            ->where('enrollments.final_score', '>=', $this->gradebook->calculator()->passingScore())
            ->pluck('sections.course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @throws EnrollmentException
     */
    private function assertNotEnrolledInSameCourse(Student $student, Section $section): void
    {
        $other = Enrollment::query()
            ->with('section.course')
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->whereHas('section', fn ($q) => $q
                ->where('course_id', $section->course_id)
                ->where('academic_term_id', $section->academic_term_id)
                ->whereKeyNot($section->id))
            ->first();

        if ($other !== null) {
            throw EnrollmentException::alreadyEnrolledInCourse($other->section->name);
        }
    }
}
