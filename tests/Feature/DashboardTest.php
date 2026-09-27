<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
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
}
