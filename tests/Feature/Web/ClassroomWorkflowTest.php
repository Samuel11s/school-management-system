<?php

namespace Tests\Feature\Web;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Livewire\Attendance\Register;
use App\Livewire\Attendance\Report;
use App\Livewire\Grades\Gradebook;
use App\Livewire\Sections\Show;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

/**
 * End-to-end journeys through the class page: enrollment, grading and attendance.
 */
class ClassroomWorkflowTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_admin_enrolls_a_student_from_the_class_page(): void
    {
        $section = $this->section();
        $student = $this->activeStudent(['first_name' => 'Nina']);

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['section' => $section])
            ->set('studentSearch', 'Nina')
            ->assertSee($student->full_name)
            ->call('enroll', $student->id)
            ->assertHasNoErrors()
            ->assertSee($student->last_name.', '.$student->first_name);

        $this->assertTrue($section->activeEnrollments()->where('student_id', $student->id)->exists());
    }

    public function test_full_class_error_is_shown(): void
    {
        $section = $this->section(['capacity' => 1]);
        $this->enroll($this->activeStudent(), $section);
        $student = $this->activeStudent();

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['section' => $section])
            ->call('enroll', $student->id)
            ->assertHasErrors('enrollment')
            ->assertSee('is full');
    }

    public function test_admin_drops_and_completes_enrollments(): void
    {
        $section = $this->section();
        $toDrop = $this->enroll($this->activeStudent(), $section);
        $toComplete = $this->enroll($this->activeStudent(), $section);

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['section' => $section])
            ->call('drop', $toDrop->id)
            ->call('complete', $toComplete->id);

        $this->assertSame(EnrollmentStatus::Dropped, $toDrop->fresh()->status);
        $this->assertSame(EnrollmentStatus::Completed, $toComplete->fresh()->status);
    }

    public function test_teachers_see_the_roster_but_cannot_change_enrollments(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $enrollment = $this->enroll($this->activeStudent(), $section);

        Livewire::actingAs($teacher)
            ->test(Show::class, ['section' => $section])
            ->assertSee('Class roster')
            ->assertDontSee('Enroll a student')
            ->call('drop', $enrollment->id)
            ->assertForbidden();
    }

    public function test_enrollment_ids_from_other_classes_are_rejected(): void
    {
        $section = $this->section();
        $foreign = $this->enroll($this->activeStudent(), $this->section());

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['section' => $section])
            ->call('drop', $foreign->id)
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->isActive());
    }

    public function test_teacher_records_grades_in_the_gradebook(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $enrollment = $this->enroll($this->activeStudent(), $section);

        $component = Livewire::actingAs($teacher)
            ->test(Gradebook::class, ['section' => $section])
            ->call('newAssessment')
            ->set('assessment.title', 'Quiz 1')
            ->set('assessment.type', 'quiz')
            ->set('assessment.max_score', 20)
            ->set('assessment.weight', 25)
            ->call('saveAssessment')
            ->assertHasNoErrors();

        $assessment = Assessment::query()->where('title', 'Quiz 1')->firstOrFail();

        $component
            ->set("scores.{$enrollment->id}.{$assessment->id}", '25')
            ->call('saveGrades')
            ->assertHasErrors("scores.{$enrollment->id}.{$assessment->id}")
            ->set("scores.{$enrollment->id}.{$assessment->id}", '17')
            ->call('saveGrades')
            ->assertHasNoErrors()
            ->assertSee('85.0%');

        $this->assertDatabaseHas('grades', ['enrollment_id' => $enrollment->id, 'graded_by' => $teacher->id]);
        $this->assertEquals(17, Grade::query()->value('score'));
    }

    public function test_gradebook_rejects_assessment_weights_over_one_hundred(): void
    {
        $section = $this->section();
        Assessment::factory()->create(['section_id' => $section->id, 'weight' => 90]);

        Livewire::actingAs($this->admin())
            ->test(Gradebook::class, ['section' => $section])
            ->call('newAssessment')
            ->set('assessment.title', 'Extra')
            ->set('assessment.weight', 20)
            ->call('saveAssessment')
            ->assertHasErrors('assessment.weight');
    }

    public function test_teachers_cannot_grade_or_take_attendance_for_other_classes(): void
    {
        $teacher = $this->teacher();
        $other = $this->section();

        $this->actingAs($teacher)->get(route('sections.gradebook', $other))->assertForbidden();
        $this->actingAs($teacher)->get(route('sections.attendance', $other))->assertForbidden();
    }

    public function test_teacher_takes_attendance_and_resaving_updates_the_register(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $student = $this->activeStudent();
        $this->enroll($student, $section);

        Livewire::actingAs($teacher)
            ->test(Register::class, ['section' => $section])
            ->assertSet("entries.{$student->id}.status", 'present')
            ->set("entries.{$student->id}.status", 'absent')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('alreadyRecorded', true);

        Livewire::actingAs($teacher)
            ->test(Register::class, ['section' => $section])
            ->assertSet("entries.{$student->id}.status", 'absent')
            ->call('markAll', 'late')
            ->call('save');

        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame(AttendanceStatus::Late, AttendanceRecord::query()->first()->status);
    }

    public function test_attendance_register_rejects_future_dates_and_tampered_students(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        $this->enroll($student, $section);
        $outsider = $this->activeStudent();

        Livewire::actingAs($this->admin())
            ->test(Register::class, ['section' => $section])
            ->set('date', now()->addDay()->toDateString())
            ->call('save')
            ->assertHasErrors('date');

        Livewire::actingAs($this->admin())
            ->test(Register::class, ['section' => $section])
            ->set("entries.{$outsider->id}", ['status' => 'present', 'remarks' => null])
            ->call('save')
            ->assertHasErrors('entries');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_attendance_report_is_scoped_to_the_viewer(): void
    {
        $student = $this->activeStudent(['first_name' => 'Visible'], withAccount: true);
        $other = $this->activeStudent(['first_name' => 'Hidden']);
        $section = $this->section();
        AttendanceRecord::factory()->create(['section_id' => $section->id, 'student_id' => $student->id, 'status' => 'absent']);
        AttendanceRecord::factory()->create(['section_id' => $section->id, 'student_id' => $other->id, 'status' => 'present']);

        Livewire::actingAs($student->user)->test(Report::class)
            ->assertSee('Visible')->assertDontSee('Hidden');

        Livewire::actingAs($this->admin())->test(Report::class)
            ->assertSee('Visible')->assertSee('Hidden')
            ->assertViewHas('summaryTotal', 2)
            ->set('belowThresholdOnly', true)
            ->assertViewHas('summaryTotal', 1)
            ->assertViewHas('summary', fn ($rows) => $rows->first()['student_id'] === $student->id);
    }

    public function test_students_see_their_own_grades_on_the_class_page(): void
    {
        $student = $this->activeStudent(withAccount: true);
        $section = $this->section();
        $enrollment = $this->enroll($student, $section);
        $assessment = Assessment::factory()->create(['section_id' => $section->id, 'title' => 'Essay', 'max_score' => 10, 'weight' => 100]);
        Grade::factory()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id, 'score' => 9, 'feedback' => 'Well argued']);
        $classmate = $this->enroll($this->activeStudent(['last_name' => 'Classmate']), $section);

        $this->actingAs($student->user)->get(route('sections.show', $section))
            ->assertOk()
            ->assertSee('My grades')
            ->assertSee('Well argued')
            ->assertSee('90.0%')
            ->assertDontSee('Classmate');
    }
}
