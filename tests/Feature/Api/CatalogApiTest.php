<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_any_user_can_browse_the_course_catalog(): void
    {
        Course::factory()->create(['code' => 'PHY101', 'department' => 'Science']);
        Course::factory()->create(['code' => 'ART101', 'department' => 'Arts']);
        Sanctum::actingAs($this->studentUser());

        $this->getJson('/api/v1/courses?filter[department]=Science&include=prerequisites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'PHY101')
            ->assertJsonPath('data.0.prerequisites', []);
    }

    public function test_only_admins_can_change_the_catalog(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($this->teacher());

        $this->postJson('/api/v1/courses', ['code' => 'X1', 'title' => 'X', 'credits' => 1])->assertForbidden();
        $this->putJson("/api/v1/courses/{$course->id}", ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/v1/courses/{$course->id}")->assertForbidden();
        $this->postJson('/api/v1/terms', [])->assertForbidden();
        $this->postJson('/api/v1/sections', [])->assertForbidden();
    }

    public function test_admin_manages_courses_and_prerequisite_rules(): void
    {
        $intro = Course::factory()->create(['code' => 'CHEM101']);
        Sanctum::actingAs($this->admin());

        $id = $this->postJson('/api/v1/courses', [
            'code' => 'chem201', 'title' => 'Organic Chemistry', 'credits' => 4, 'prerequisite_ids' => [$intro->id],
        ])->assertCreated()
            ->assertJsonPath('data.code', 'CHEM201')
            ->assertJsonPath('data.prerequisites.0.code', 'CHEM101')
            ->json('data.id');

        $this->patchJson("/api/v1/courses/{$intro->id}", ['prerequisite_ids' => [$id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prerequisite_ids');

        $this->patchJson("/api/v1/courses/{$id}", ['title' => 'Organic Chemistry I'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Organic Chemistry I')
            ->assertJsonPath('data.prerequisites.0.code', 'CHEM101');

        $this->postJson('/api/v1/courses', ['code' => 'CHEM101', 'title' => 'Dup', 'credits' => 1])
            ->assertJsonValidationErrors('code');
    }

    public function test_courses_with_classes_cannot_be_deleted(): void
    {
        $section = $this->section();
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/courses/{$section->course_id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course');
    }

    public function test_admin_manages_terms_with_partial_updates(): void
    {
        Sanctum::actingAs($this->admin());

        $id = $this->postJson('/api/v1/terms', [
            'name' => 'Summer 2027', 'code' => 'su2027',
            'starts_on' => '2027-06-01', 'ends_on' => '2027-08-15',
            'enrollment_opens_on' => '2027-05-01', 'enrollment_closes_on' => '2027-06-10',
        ])->assertCreated()->assertJsonPath('data.code', 'SU2027')->json('data.id');

        $this->patchJson("/api/v1/terms/{$id}", ['ends_on' => '2027-05-01'])->assertJsonValidationErrors('ends_on');
        $this->patchJson("/api/v1/terms/{$id}", ['name' => 'Summer Session 2027'])->assertOk()->assertJsonPath('data.name', 'Summer Session 2027');
    }

    public function test_class_listing_is_scoped_and_includes_relations(): void
    {
        $teacher = $this->teacher();
        $mine = $this->section(teacher: $teacher);
        $this->section();

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/sections?include=course,term,teacher')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.teacher.id', $teacher->id)
            ->assertJsonStructure(['data' => [['course' => ['code'], 'term' => ['name'], 'enrolled_count', 'seats_available']]]);
    }

    public function test_admin_creates_classes_and_duplicate_codes_fail(): void
    {
        $term = $this->openTerm();
        $course = Course::factory()->create();
        $teacher = $this->teacher();
        Sanctum::actingAs($this->admin());

        $payload = ['course_id' => $course->id, 'academic_term_id' => $term->id, 'teacher_id' => $teacher->id, 'code' => 'b', 'capacity' => 25, 'status' => 'open'];

        $this->postJson('/api/v1/sections', $payload)->assertCreated()->assertJsonPath('data.name', $course->code.'-B');
        $this->postJson('/api/v1/sections', $payload)->assertJsonValidationErrors('code');

        $id = Section::query()->value('id');
        $this->patchJson("/api/v1/sections/{$id}", ['capacity' => 30])->assertOk()->assertJsonPath('data.capacity', 30);
        $this->patchJson("/api/v1/sections/{$id}", ['teacher_id' => $this->studentUser()->id])->assertJsonValidationErrors('teacher_id');
    }
}
