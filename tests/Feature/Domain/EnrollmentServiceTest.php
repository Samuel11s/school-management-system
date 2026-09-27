<?php

namespace Tests\Feature\Domain;

use App\Enums\EnrollmentStatus;
use App\Enums\SectionStatus;
use App\Enums\StudentStatus;
use App\Events\EnrollmentDropped;
use App\Events\StudentEnrolled;
use App\Exceptions\EnrollmentException;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Notifications\EnrollmentStatusChanged;
use App\Services\EnrollmentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class EnrollmentServiceTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private EnrollmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnrollmentService::class);
    }

    public function test_active_student_can_enroll_in_open_class(): void
    {
        Event::fake([StudentEnrolled::class]);
        $section = $this->section();
        $student = $this->activeStudent();

        $enrollment = $this->service->enroll($student, $section);

        $this->assertSame(EnrollmentStatus::Enrolled, $enrollment->status);
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'enrolled']);
        $this->assertDatabaseHas('status_histories', ['subject_id' => $enrollment->id, 'to_status' => 'enrolled']);
        Event::assertDispatched(StudentEnrolled::class, fn ($e) => $e->enrollment->is($enrollment));
    }

    public function test_student_with_account_is_notified(): void
    {
        Notification::fake();
        $student = $this->activeStudent(withAccount: true);

        $this->service->enroll($student, $this->section());

        Notification::assertSentTo($student->user, EnrollmentStatusChanged::class);
    }

    public function test_duplicate_enrollment_is_rejected(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        $this->service->enroll($student, $section);

        $this->expectException(EnrollmentException::class);
        $this->expectExceptionMessage('already enrolled');

        $this->service->enroll($student, $section);
    }

    public function test_database_prevents_duplicate_enrollment_rows(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        $this->enroll($student, $section);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->enroll($student, $section);
    }

    public function test_full_class_rejects_enrollment(): void
    {
        $section = $this->section(['capacity' => 2]);
        $this->service->enroll($this->activeStudent(), $section);
        $this->service->enroll($this->activeStudent(), $section);

        try {
            $this->service->enroll($this->activeStudent(), $section);
            $this->fail('Expected the class to be full.');
        } catch (EnrollmentException $e) {
            $this->assertStringContainsString('is full', $e->getMessage());
            $this->assertSame('section_id', $e->field);
        }

        $this->assertSame(2, $section->activeEnrollments()->count());
    }

    public function test_dropped_students_free_their_seat(): void
    {
        $section = $this->section(['capacity' => 1]);
        $first = $this->service->enroll($this->activeStudent(), $section);
        $this->service->drop($first);

        $second = $this->service->enroll($this->activeStudent(), $section);

        $this->assertTrue($second->isActive());
    }

    /**
     * @return array<string, array{StudentStatus}>
     */
    public static function ineligibleStatuses(): array
    {
        return [
            'inactive' => [StudentStatus::Inactive],
            'suspended' => [StudentStatus::Suspended],
            'graduated' => [StudentStatus::Graduated],
            'withdrawn' => [StudentStatus::Withdrawn],
        ];
    }

    #[DataProvider('ineligibleStatuses')]
    public function test_only_active_students_are_eligible(StudentStatus $status): void
    {
        $this->expectException(EnrollmentException::class);

        $this->service->enroll($this->activeStudent(['status' => $status]), $this->section());
    }

    public function test_closed_or_cancelled_classes_reject_enrollment(): void
    {
        foreach ([SectionStatus::Closed, SectionStatus::Cancelled] as $status) {
            try {
                $this->service->enroll($this->activeStudent(), $this->section(['status' => $status]));
                $this->fail("Expected {$status->value} class to reject enrollment.");
            } catch (EnrollmentException $e) {
                $this->assertStringContainsString('not open', $e->getMessage());
            }
        }
    }

    public function test_enrollment_window_is_enforced(): void
    {
        $closed = AcademicTerm::factory()->enrollmentClosed()->create();
        $notYetOpen = AcademicTerm::factory()->enrollmentNotYetOpen()->create();

        foreach ([$closed, $notYetOpen] as $term) {
            try {
                $this->service->enroll($this->activeStudent(), $this->section(term: $term));
                $this->fail('Expected the enrollment window to be enforced.');
            } catch (EnrollmentException $e) {
                $this->assertStringContainsString('only possible between', $e->getMessage());
            }
        }
    }

    public function test_prerequisites_must_be_passed(): void
    {
        $intro = Course::factory()->create(['code' => 'CS101']);
        $advanced = Course::factory()->create(['code' => 'CS201']);
        $advanced->prerequisites()->attach($intro);
        $section = $this->section(['course_id' => $advanced->id]);

        $never = $this->activeStudent();
        $failed = $this->activeStudent();
        $passed = $this->activeStudent();
        $this->completedCourse($failed, $intro, 42.0);
        $this->completedCourse($passed, $intro, 75.0);

        foreach ([$never, $failed] as $student) {
            try {
                $this->service->enroll($student, $section);
                $this->fail('Expected missing prerequisite.');
            } catch (EnrollmentException $e) {
                $this->assertStringContainsString('CS101', $e->getMessage());
            }
        }

        $this->assertTrue($this->service->enroll($passed, $section)->isActive());
    }

    public function test_student_cannot_retake_a_passed_course(): void
    {
        $course = Course::factory()->create(['code' => 'ART101']);
        $student = $this->activeStudent();
        $this->completedCourse($student, $course, 88.0);

        $this->expectExceptionMessage('already passed ART101');

        $this->service->enroll($student, $this->section(['course_id' => $course->id]));
    }

    public function test_student_cannot_take_two_classes_of_the_same_course_in_a_term(): void
    {
        $term = $this->openTerm();
        $course = Course::factory()->create();
        $a = $this->section(['course_id' => $course->id, 'code' => 'A'], $term);
        $b = $this->section(['course_id' => $course->id, 'code' => 'B'], $term);
        $student = $this->activeStudent();
        $this->service->enroll($student, $a);

        $this->expectExceptionMessage('already enrolled in this course this term');

        $this->service->enroll($student, $b);
    }

    public function test_re_enrolling_after_drop_reactivates_the_same_row(): void
    {
        Event::fake([EnrollmentDropped::class, StudentEnrolled::class]);
        $section = $this->section();
        $student = $this->activeStudent();

        $enrollment = $this->service->enroll($student, $section);
        $this->service->drop($enrollment, 'Schedule conflict');
        $again = $this->service->enroll($student, $section);

        $this->assertTrue($again->is($enrollment));
        $this->assertSame(1, Enrollment::query()->count());
        $this->assertSame(
            ['enrolled', 'dropped', 'enrolled'],
            $again->statusHistories()->reorder('id')->pluck('to_status')->all(),
        );
        $this->assertDatabaseHas('status_histories', ['to_status' => 'dropped', 'reason' => 'Schedule conflict']);
        Event::assertDispatched(EnrollmentDropped::class);
    }

    public function test_only_active_enrollments_can_be_dropped_or_completed(): void
    {
        $enrollment = Enrollment::factory()->dropped()->create();

        try {
            $this->service->drop($enrollment);
            $this->fail('Expected an exception.');
        } catch (EnrollmentException) {
        }

        $this->expectException(EnrollmentException::class);
        $this->service->complete($enrollment);
    }

    public function test_completing_freezes_the_weighted_final_score(): void
    {
        $section = $this->section();
        $enrollment = $this->enroll($this->activeStudent(), $section);
        $exam = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 100, 'weight' => 60]);
        $quiz = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 20, 'weight' => 40]);
        Grade::factory()->create(['assessment_id' => $exam->id, 'enrollment_id' => $enrollment->id, 'score' => 90]);
        Grade::factory()->create(['assessment_id' => $quiz->id, 'enrollment_id' => $enrollment->id, 'score' => 15]);

        $this->service->complete($enrollment);

        // 0.9*60 + 0.75*40 = 84
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->fresh()->status);
        $this->assertSame('84.00', $enrollment->fresh()->final_score);
        $this->assertSame('B', $enrollment->fresh()->letter_grade);
    }

    public function test_eligibility_errors_are_reported_without_throwing(): void
    {
        $student = $this->activeStudent(['status' => StudentStatus::Suspended]);

        $errors = $this->service->eligibilityErrors($student, $this->section());

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Only active students', $errors[0]);
    }
}
