<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_school_overview(): void
    {
        AcademicTerm::factory()->current()->create();

        $this->actingAs($this->admin())->get('/dashboard')
            ->assertOk()
            ->assertSee('Active students')
            ->assertSee('Recent enrollment activity');
    }

    public function test_teacher_sees_their_classes(): void
    {
        $term = AcademicTerm::factory()->current()->create();
        $teacher = $this->teacher();
        $section = Section::factory()->for($term, 'term')->create(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)->get('/dashboard')
            ->assertOk()
            ->assertSee($section->name)
            ->assertDontSee('Active students');
    }

    public function test_student_sees_their_own_classes_only(): void
    {
        $term = AcademicTerm::factory()->current()->create();
        $student = Student::factory()->withAccount()->create();
        $mine = Section::factory()->for($term, 'term')->create();
        $other = Section::factory()->for($term, 'term')->create();
        Enrollment::factory()->for($student)->for($mine)->create();

        $this->actingAs($student->user)->get('/dashboard')
            ->assertOk()
            ->assertSee($mine->name)
            ->assertDontSee($other->name)
            ->assertDontSee('Active students');
    }

    public function test_attendance_breakdown_chart_is_scoped_to_the_viewer(): void
    {
        $term = AcademicTerm::factory()->current()->create();
        $student = Student::factory()->withAccount()->create();
        $section = Section::factory()->for($term, 'term')->create();
        AttendanceRecord::factory()->create(['section_id' => $section->id, 'student_id' => $student->id, 'status' => 'absent']);
        AttendanceRecord::factory()->count(3)->create(['section_id' => $section->id, 'status' => 'present']);

        $this->actingAs($student->user)->get('/dashboard')
            ->assertOk()
            ->assertSee('My attendance')
            ->assertSee('Attendance breakdown: Present 0%, Late 0%, Absent 100%, Excused 0%', false);

        $this->actingAs($this->admin())->get('/dashboard')
            ->assertSee('Attendance breakdown: Present 75%, Late 0%, Absent 25%, Excused 0%', false);
    }

    public function test_global_search_targets_what_the_user_may_search(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertSee('action="'.route('students.index').'"', false)
            ->assertSee('Search students');

        $this->actingAs(Student::factory()->withAccount()->create()->user)->get('/dashboard')
            ->assertSee('action="'.route('sections.index').'"', false)
            ->assertSee('Search classes');
    }

    public function test_error_pages_use_the_application_design(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Go back');

        $this->actingAs($this->studentUser())->get(route('users.index'))
            ->assertForbidden()
            ->assertSee('Access denied');
    }
}
