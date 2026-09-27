<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class GradeApiTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_teacher_manages_assessments_for_their_own_class_only(): void
    {
        $teacher = $this->teacher();
        $mine = $this->section(teacher: $teacher);
        $other = $this->section();
        Sanctum::actingAs($teacher);

        $payload = ['title' => 'Lab report', 'type' => 'assignment', 'max_score' => 40, 'weight' => 60];

        $id = $this->postJson('/api/v1/assessments', [...$payload, 'section_id' => $mine->id])
            ->assertCreated()->assertJsonPath('data.max_score', 40)->json('data.id');
        $this->postJson('/api/v1/assessments', [...$payload, 'section_id' => $other->id])->assertForbidden();

        // Weight cap and unique titles per class.
        $this->postJson('/api/v1/assessments', [...$payload, 'title' => 'Exam', 'section_id' => $mine->id])
            ->assertUnprocessable()->assertJsonValidationErrors('weight');
        $this->postJson('/api/v1/assessments', [...$payload, 'weight' => 10, 'section_id' => $mine->id])
            ->assertJsonValidationErrors('title');

        $this->patchJson("/api/v1/assessments/{$id}", ['weight' => 50])->assertOk()->assertJsonPath('data.weight', 50);
        $this->deleteJson("/api/v1/assessments/{$id}")->assertNoContent();
    }

    public function test_recording_grades_creates_then_updates(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $enrollment = $this->enroll($this->activeStudent(), $section);
        $assessment = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 20]);
        Sanctum::actingAs($teacher);

        $payload = ['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id, 'score' => 15];

        $this->postJson('/api/v1/grades', $payload)->assertCreated()->assertJsonPath('data.percentage', 75);
        $this->postJson('/api/v1/grades', [...$payload, 'score' => 18, 'feedback' => 'Improved'])
            ->assertOk()->assertJsonPath('data.score', 18)->assertJsonPath('data.feedback', 'Improved');

        $this->assertSame(1, Grade::query()->count());
    }

    public function test_invalid_grades_are_rejected(): void
    {
        $section = $this->section();
        $enrollment = $this->enroll($this->activeStudent(), $section);
        $assessment = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 20]);
        $otherEnrollment = $this->enroll($this->activeStudent(), $this->section());
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/grades', ['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id, 'score' => 25])
            ->assertUnprocessable()->assertJsonValidationErrors('score');
        $this->postJson('/api/v1/grades', ['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id, 'score' => 'ten'])
            ->assertJsonValidationErrors('score');
        $this->postJson('/api/v1/grades', ['assessment_id' => $assessment->id, 'enrollment_id' => $otherEnrollment->id, 'score' => 10])
            ->assertJsonValidationErrors('enrollment_id');

        $this->assertSame(0, Grade::query()->count());
    }

    public function test_teachers_cannot_grade_other_classes(): void
    {
        $grade = Grade::factory()->create();
        Sanctum::actingAs($this->teacher());

        $this->postJson('/api/v1/grades', ['assessment_id' => $grade->assessment_id, 'enrollment_id' => $grade->enrollment_id, 'score' => 1])
            ->assertForbidden();
        $this->patchJson("/api/v1/grades/{$grade->id}", ['score' => 1])->assertForbidden();
        $this->getJson("/api/v1/grades/{$grade->id}")->assertForbidden();
    }

    public function test_students_only_receive_their_own_grades(): void
    {
        $student = $this->activeStudent(withAccount: true);
        $section = $this->section();
        $assessment = Assessment::factory()->create(['section_id' => $section->id]);
        $mine = Grade::factory()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $this->enroll($student, $section)->id]);
        $classmate = Grade::factory()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $this->enroll($this->activeStudent(), $section)->id]);
        Sanctum::actingAs($student->user);

        $this->getJson("/api/v1/grades?filter[section_id]={$section->id}&include=assessment")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.student_id', $student->id);
        $this->getJson("/api/v1/grades/{$classmate->id}")->assertForbidden();
        $this->postJson('/api/v1/grades', ['assessment_id' => $assessment->id, 'enrollment_id' => $mine->enrollment_id, 'score' => 100])->assertForbidden();
        $this->getJson("/api/v1/sections/{$section->id}/grade-summary")->assertForbidden();
    }

    public function test_grade_summary_reports_class_statistics(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $assessment = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 100, 'weight' => 100]);
        Grade::factory()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $this->enroll($this->activeStudent(), $section)->id, 'score' => 90]);
        Grade::factory()->create(['assessment_id' => $assessment->id, 'enrollment_id' => $this->enroll($this->activeStudent(), $section)->id, 'score' => 70]);
        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/sections/{$section->id}/grade-summary")
            ->assertOk()
            ->assertJsonPath('data.class_average', 80)
            ->assertJsonPath('data.distribution.A', 1)
            ->assertJsonPath('data.distribution.C', 1)
            ->assertJsonPath('data.assessments.0.average_percentage', 80)
            ->assertJsonCount(2, 'data.students');
    }
}
