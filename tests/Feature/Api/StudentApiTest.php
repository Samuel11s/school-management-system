<?php

namespace Tests\Feature\Api;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_admin_lists_students_with_pagination_metadata(): void
    {
        Student::factory()->count(3)->create();
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/students?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'student_number', 'full_name', 'email', 'status', 'phone', 'guardian_name']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_page_size_is_capped(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/students?per_page=5000')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_students_can_be_filtered_and_sorted(): void
    {
        $this->activeStudent(['last_name' => 'Zulu', 'grade_level' => 9]);
        $this->activeStudent(['last_name' => 'Alpha', 'grade_level' => 9]);
        $this->activeStudent(['last_name' => 'Mike', 'grade_level' => 10, 'status' => StudentStatus::Suspended]);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/students?filter[grade_level]=9&sort=-last_name')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.last_name', 'Zulu')
            ->assertJsonPath('data.1.last_name', 'Alpha');

        $this->getJson('/api/v1/students?filter[status]=suspended')->assertJsonCount(1, 'data')->assertJsonPath('data.0.last_name', 'Mike');
        $this->getJson('/api/v1/students?filter[search]=alp')->assertJsonCount(1, 'data');
    }

    public function test_unknown_filters_and_sorts_are_rejected(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/students?filter[password]=x')->assertStatus(400);
        $this->getJson('/api/v1/students?sort=password')->assertStatus(400);
    }

    public function test_teachers_only_list_their_students_without_sensitive_fields(): void
    {
        $teacher = $this->teacher();
        $mine = $this->activeStudent();
        $this->enroll($mine, $this->section(teacher: $teacher));
        $this->activeStudent();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.guardian_phone')
            ->assertJsonMissingPath('data.0.date_of_birth');
    }

    public function test_students_cannot_list_but_can_read_their_own_record(): void
    {
        $student = $this->activeStudent(withAccount: true);
        $other = $this->activeStudent();
        Sanctum::actingAs($student->user);

        $this->getJson('/api/v1/students')->assertForbidden()->assertExactJson(['message' => 'This action is unauthorized.']);
        $this->getJson("/api/v1/students/{$student->id}")->assertOk()->assertJsonPath('data.phone', $student->phone);
        $this->getJson("/api/v1/students/{$other->id}")->assertForbidden();
    }

    public function test_admin_creates_a_student_with_an_account(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/v1/students', [
            'first_name' => 'Rosalind',
            'last_name' => 'Franklin',
            'email' => 'rosalind@example.com',
            'admission_date' => '2026-09-01',
            'status' => 'active',
            'grade_level' => 11,
            'create_account' => true,
            'user_id' => 999, // not fillable through the API: ignored
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Rosalind Franklin')
            ->assertJsonPath('data.has_account', true);

        $student = Student::query()->findOrFail($response->json('data.id'));
        $this->assertNotSame(999, $student->user_id);
        Notification::assertSentTo($student->user, ResetPassword::class);
    }

    public function test_create_validation_errors(): void
    {
        $existing = $this->activeStudent();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/students', ['email' => $existing->email, 'status' => 'unknown', 'grade_level' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'admission_date', 'status', 'grade_level']);
    }

    public function test_partial_update_and_status_history(): void
    {
        $student = $this->activeStudent(['first_name' => 'Old']);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/v1/students/{$student->id}", ['status' => 'suspended', 'status_reason' => 'Conduct review'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.first_name', 'Old');

        $this->assertDatabaseHas('status_histories', ['subject_id' => $student->id, 'to_status' => 'suspended', 'reason' => 'Conduct review']);
    }

    public function test_delete_soft_deletes_and_teachers_cannot_modify(): void
    {
        $teacher = $this->teacher();
        $student = $this->activeStudent();
        $this->enroll($student, $this->section(teacher: $teacher));

        Sanctum::actingAs($teacher);
        $this->patchJson("/api/v1/students/{$student->id}", ['first_name' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/v1/students/{$student->id}")->assertForbidden();
        $this->postJson('/api/v1/students', [])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/v1/students/{$student->id}")->assertNoContent();
        $this->assertSoftDeleted($student);
        $this->getJson("/api/v1/students/{$student->id}")->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
    }
}
