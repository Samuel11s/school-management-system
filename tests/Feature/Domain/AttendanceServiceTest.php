<?php

namespace Tests\Feature\Domain;

use App\Enums\AttendanceStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Services\AttendanceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceService::class);
    }

    public function test_register_is_saved_for_enrolled_students(): void
    {
        $section = $this->section();
        $a = $this->activeStudent();
        $b = $this->activeStudent();
        $this->enroll($a, $section);
        $this->enroll($b, $section);

        $records = $this->service->recordRegister($section, Carbon::today(), [
            $a->id => ['status' => 'present'],
            $b->id => ['status' => AttendanceStatus::Absent, 'remarks' => 'Sick'],
        ], $section->teacher);

        $this->assertCount(2, $records);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $b->id, 'status' => 'absent', 'remarks' => 'Sick']);
    }

    public function test_saving_the_same_session_twice_updates_instead_of_duplicating(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        $this->enroll($student, $section);
        $date = Carbon::today();

        $this->service->recordRegister($section, $date, [$student->id => ['status' => 'absent']], null);
        $this->service->recordRegister($section, $date->copy()->setTime(15, 30), [$student->id => ['status' => 'late']], null);

        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame(AttendanceStatus::Late, AttendanceRecord::query()->first()->status);
    }

    public function test_single_record_rejects_duplicates(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        $this->enroll($student, $section);

        $this->service->record($section, $student, Carbon::today(), AttendanceStatus::Present, null, null);

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('already been recorded');

        $this->service->record($section, $student, Carbon::today(), AttendanceStatus::Absent, null, null);
    }

    public function test_database_prevents_duplicate_attendance(): void
    {
        $record = AttendanceRecord::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        AttendanceRecord::factory()->create([
            'section_id' => $record->section_id,
            'student_id' => $record->student_id,
            'attended_on' => $record->attended_on,
        ]);
    }

    public function test_future_dates_are_rejected(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        $this->enroll($student, $section);

        $this->expectExceptionMessage('future date');

        $this->service->recordRegister($section, Carbon::tomorrow(), [$student->id => ['status' => 'present']], null);
    }

    public function test_dates_outside_the_term_are_rejected(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        $this->enroll($student, $section);

        $this->expectExceptionMessage('within the term');

        $this->service->recordRegister($section, $section->term->starts_on->copy()->subDay(), [$student->id => ['status' => 'present']], null);
    }

    public function test_students_not_enrolled_are_rejected(): void
    {
        $section = $this->section();
        $outsider = $this->activeStudent();
        $dropped = Enrollment::factory()->dropped()->create(['section_id' => $section->id]);

        foreach ([$outsider->id, $dropped->student_id] as $studentId) {
            try {
                $this->service->recordRegister($section, Carbon::today(), [$studentId => ['status' => 'present']], null);
                $this->fail('Expected rejection.');
            } catch (DomainRuleException $e) {
                $this->assertSame('student_id', $e->field);
            }
        }

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_summary_counts_and_rates(): void
    {
        $section = $this->section();
        $student = $this->activeStudent();
        $days = collect(range(1, 6))->map(fn ($d) => Carbon::today()->subDays($d)->toDateString());
        $statuses = ['present', 'present', 'late', 'absent', 'excused', 'absent'];

        foreach ($days as $i => $day) {
            AttendanceRecord::factory()->create([
                'section_id' => $section->id, 'student_id' => $student->id, 'attended_on' => $day, 'status' => $statuses[$i],
            ]);
        }

        $row = $this->service->summarize(AttendanceRecord::query()->where('section_id', $section->id))->first();

        $this->assertSame(6, $row['total']);
        $this->assertSame(2, $row['present']);
        $this->assertSame(1, $row['late']);
        $this->assertSame(2, $row['absent']);
        $this->assertSame(1, $row['excused']);
        // Excused excluded: 3 attended out of 5 countable sessions.
        $this->assertSame(60.0, $row['rate']);
        $this->assertTrue($row['below_threshold']);
    }
}
