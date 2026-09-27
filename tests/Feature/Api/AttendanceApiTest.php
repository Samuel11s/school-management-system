<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    public function test_single_record_and_duplicate_prevention(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $student = $this->activeStudent();
        $this->enroll($student, $section);
        Sanctum::actingAs($teacher);

        $payload = ['section_id' => $section->id, 'student_id' => $student->id, 'date' => now()->toDateString(), 'status' => 'late'];

        $id = $this->postJson('/api/v1/attendance', $payload)->assertCreated()->assertJsonPath('data.status', 'late')->json('data.id');
        $this->postJson('/api/v1/attendance', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'Attendance has already been recorded for this student in this class on this date.');

        $this->patchJson("/api/v1/attendance/{$id}", ['status' => 'present', 'remarks' => 'Bus delay'])
            ->assertOk()->assertJsonPath('data.status', 'present');

        $this->assertSame(1, AttendanceRecord::query()->count());
    }

    public function test_register_saves_a_whole_session_idempotently(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $a = $this->activeStudent();
        $b = $this->activeStudent();
        $this->enroll($a, $section);
        $this->enroll($b, $section);
        Sanctum::actingAs($teacher);

        $body = ['date' => now()->toDateString(), 'entries' => [
            ['student_id' => $a->id, 'status' => 'present'],
            ['student_id' => $b->id, 'status' => 'absent', 'remarks' => 'Unwell'],
        ]];

        $this->postJson("/api/v1/sections/{$section->id}/attendance", $body)->assertOk()->assertJsonCount(2, 'data');
        $body['entries'][1]['status'] = 'excused';
        $this->postJson("/api/v1/sections/{$section->id}/attendance", $body)->assertOk();

        $this->assertSame(2, AttendanceRecord::query()->count());
        $this->assertDatabaseHas('attendance_records', ['student_id' => $b->id, 'status' => 'excused']);
    }

    public function test_register_validation_and_rules(): void
    {
        $section = $this->section();
        $enrolled = $this->activeStudent();
        $this->enroll($enrolled, $section);
        Sanctum::actingAs($this->admin());
        $url = "/api/v1/sections/{$section->id}/attendance";

        $this->postJson($url, ['date' => 'yesterday', 'entries' => []])->assertJsonValidationErrors(['date', 'entries']);
        $this->postJson($url, ['date' => now()->toDateString(), 'entries' => [['student_id' => $enrolled->id, 'status' => 'asleep']]])
            ->assertJsonValidationErrors('entries.0.status');
        $this->postJson($url, ['date' => now()->addDay()->toDateString(), 'entries' => [['student_id' => $enrolled->id, 'status' => 'present']]])
            ->assertJsonValidationErrors('date');
        $this->postJson($url, ['date' => now()->toDateString(), 'entries' => [['student_id' => $this->activeStudent()->id, 'status' => 'present']]])
            ->assertJsonValidationErrors('student_id');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_permissions_and_scoping(): void
    {
        $student = $this->activeStudent(withAccount: true);
        $section = $this->section();
        $this->enroll($student, $section);
        $mine = AttendanceRecord::factory()->create(['section_id' => $section->id, 'student_id' => $student->id]);
        $other = AttendanceRecord::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student->user);
        $this->getJson('/api/v1/attendance')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/v1/attendance/{$other->id}")->assertForbidden();
        $this->patchJson("/api/v1/attendance/{$mine->id}", ['status' => 'present'])->assertForbidden();
        $this->postJson("/api/v1/sections/{$section->id}/attendance", [])->assertForbidden();

        Sanctum::actingAs($this->teacher());
        $this->deleteJson("/api/v1/attendance/{$mine->id}")->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/v1/attendance/{$mine->id}")->assertNoContent();
    }

    public function test_filters_and_summary(): void
    {
        $teacher = $this->teacher();
        $section = $this->section(teacher: $teacher);
        $student = $this->activeStudent();
        foreach (['present', 'absent', 'late', 'present'] as $i => $status) {
            AttendanceRecord::factory()->create([
                'section_id' => $section->id, 'student_id' => $student->id,
                'attended_on' => now()->subDays($i + 1)->toDateString(), 'status' => $status,
            ]);
        }
        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/attendance?filter[section_id]={$section->id}&filter[status]=present")->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/attendance?filter[from]='.now()->subDays(2)->toDateString())->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/attendance?sort=date')->assertJsonPath('data.0.date', now()->subDays(4)->toDateString());

        $this->getJson("/api/v1/sections/{$section->id}/attendance-summary")
            ->assertOk()
            ->assertJsonPath('data.students.0.total', 4)
            ->assertJsonPath('data.students.0.rate', 75)
            ->assertJsonPath('data.students.0.below_threshold', true);
    }
}
