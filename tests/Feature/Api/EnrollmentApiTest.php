<?php

namespace Tests\Feature\Api;

use App\Models\AcademicTerm;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class EnrollmentApiTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_admin_enrolls_a_student(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/enrollments', ['student_id' => $student->id, 'section_id' => $section->id, 'reason' => 'Registrar'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'enrolled')
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.section.id', $section->id);
    }

    public function test_business_rule_violations_return_422_with_reason(): void
    {
        $section = $this->section(['capacity' => 1]);
        $first = $this->activeStudent();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/enrollments', ['student_id' => $first->id, 'section_id' => $section->id])->assertCreated();

        // Duplicate enrollment.
        $this->postJson('/api/v1/enrollments', ['student_id' => $first->id, 'section_id' => $section->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.student_id.0', 'The student is already enrolled in this class.');

        // Full class.
        $this->postJson('/api/v1/enrollments', ['student_id' => $this->activeStudent()->id, 'section_id' => $section->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('section_id');

        // Closed enrollment window.
        $closed = $this->section(term: AcademicTerm::factory()->enrollmentClosed()->create());
        $this->postJson('/api/v1/enrollments', ['student_id' => $this->activeStudent()->id, 'section_id' => $closed->id])
            ->assertJsonValidationErrors('section_id');

        // Unknown ids.
        $this->postJson('/api/v1/enrollments', ['student_id' => 999, 'section_id' => 999])
            ->assertJsonValidationErrors(['student_id', 'section_id']);
    }

    public function test_teachers_and_students_cannot_enroll(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $student = $this->activeStudent(withAccount: true);

        foreach ([$teacher, $student->user] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/v1/enrollments', ['student_id' => $student->id, 'section_id' => $section->id])->assertForbidden();
        }

        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_enrollment_listing_is_scoped(): void
    {
        $student = $this->activeStudent(withAccount: true);
        $mine = $this->enroll($student, $this->section());
        $other = $this->enroll($this->activeStudent(), $this->section());

        Sanctum::actingAs($student->user);
        $this->getJson('/api/v1/enrollments?include=section')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/v1/enrollments/{$other->id}")->assertForbidden();
        $this->getJson("/api/v1/enrollments/{$mine->id}")->assertOk()->assertJsonStructure(['data' => ['history']]);

        Sanctum::actingAs($this->admin());
        $this->getJson("/api/v1/enrollments?filter[section_id]={$other->section_id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
    }

    public function test_drop_and_complete(): void
    {
        $section = $this->section();
        $toDrop = $this->enroll($this->activeStudent(), $section);
        $toComplete = $this->enroll($this->activeStudent(), $section);
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/enrollments/{$toDrop->id}/drop", ['reason' => 'Moved away'])->assertOk()->assertJsonPath('data.status', 'dropped');
        $this->postJson("/api/v1/enrollments/{$toDrop->id}/drop")->assertUnprocessable();
        $this->postJson("/api/v1/enrollments/{$toComplete->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

        Sanctum::actingAs($this->teacher());
        $this->postJson("/api/v1/enrollments/{$toComplete->id}/drop")->assertForbidden();
    }
}
