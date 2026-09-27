<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attendance registers and attendance summaries.
 */
final class AttendanceService
{
    /**
     * Save the register for a class session. Existing records for the same
     * student and date are updated rather than duplicated.
     *
     * @param  array<int, array{status: AttendanceStatus|string, remarks?: string|null}>  $entries  keyed by student id
     * @return Collection<int, AttendanceRecord>
     *
     * @throws DomainRuleException
     */
    public function recordRegister(Section $section, CarbonInterface $date, array $entries, ?User $recorder): Collection
    {
        $this->assertValidSessionDate($section, $date);
        $this->assertStudentsEnrolled($section, array_keys($entries));

        return DB::transaction(function () use ($section, $date, $entries, $recorder) {
            $records = collect();

            foreach ($entries as $studentId => $entry) {
                $records->push(AttendanceRecord::query()->updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'student_id' => $studentId,
                        'attended_on' => $date->toDateString(),
                    ],
                    [
                        'status' => $entry['status'] instanceof AttendanceStatus ? $entry['status'] : AttendanceStatus::from($entry['status']),
                        'remarks' => $entry['remarks'] ?? null,
                        'recorded_by' => $recorder?->id,
                    ],
                ));
            }

            return $records;
        });
    }

    /**
     * Create a single attendance record, rejecting duplicates for the same
     * student, class and date.
     *
     * @throws DomainRuleException
     */
    public function record(Section $section, Student $student, CarbonInterface $date, AttendanceStatus $status, ?string $remarks, ?User $recorder): AttendanceRecord
    {
        $this->assertValidSessionDate($section, $date);
        $this->assertStudentsEnrolled($section, [$student->id]);

        $exists = AttendanceRecord::query()
            ->where('section_id', $section->id)
            ->where('student_id', $student->id)
            ->whereDate('attended_on', $date->toDateString())
            ->exists();

        if ($exists) {
            throw self::duplicate();
        }

        try {
            return AttendanceRecord::query()->create([
                'section_id' => $section->id,
                'student_id' => $student->id,
                'attended_on' => $date->toDateString(),
                'status' => $status,
                'remarks' => $remarks,
                'recorded_by' => $recorder?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a concurrent request for the same session.
            throw self::duplicate();
        }
    }

    /**
     * Per-student attendance counts and rates.
     *
     * @param  Builder<AttendanceRecord>  $query  pre-filtered records (by class, student, dates...)
     * @return Collection<int, array{student_id: int, total: int, present: int, late: int, absent: int, excused: int, rate: float|null, below_threshold: bool}>
     */
    public function summarize(Builder $query): Collection
    {
        $threshold = (float) config('school.attendance.warning_threshold', 80);

        $rows = (clone $query)
            ->reorder()
            ->select('student_id', 'status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('student_id', 'status')
            ->toBase()
            ->get();

        return $rows->groupBy('student_id')->map(function (Collection $group, $studentId) use ($threshold) {
            $counts = array_fill_keys(AttendanceStatus::values(), 0);

            foreach ($group as $row) {
                $counts[$row->status] = (int) $row->aggregate;
            }

            $total = array_sum($counts);
            // Excused absences are not held against the student.
            $countable = $total - $counts[AttendanceStatus::Excused->value];
            $attended = $counts[AttendanceStatus::Present->value] + $counts[AttendanceStatus::Late->value];
            $rate = $countable > 0 ? round($attended / $countable * 100, 1) : null;

            return [
                'student_id' => (int) $studentId,
                'total' => $total,
                'present' => $counts[AttendanceStatus::Present->value],
                'late' => $counts[AttendanceStatus::Late->value],
                'absent' => $counts[AttendanceStatus::Absent->value],
                'excused' => $counts[AttendanceStatus::Excused->value],
                'rate' => $rate,
                'below_threshold' => $rate !== null && $rate < $threshold,
            ];
        })->values();
    }

    /**
     * @throws DomainRuleException
     */
    private function assertValidSessionDate(Section $section, CarbonInterface $date): void
    {
        if ($date->copy()->startOfDay()->greaterThan(Carbon::today())) {
            throw new DomainRuleException('Attendance cannot be recorded for a future date.', 'date');
        }

        $term = $section->term;

        if (! $term->includesDate($date)) {
            throw new DomainRuleException(sprintf(
                'The date must fall within the term (%s to %s).',
                $term->starts_on->toFormattedDateString(),
                $term->ends_on->toFormattedDateString(),
            ), 'date');
        }
    }

    /**
     * @param  list<int|string>  $studentIds
     *
     * @throws DomainRuleException
     */
    private function assertStudentsEnrolled(Section $section, array $studentIds): void
    {
        $enrolled = $section->enrollments()
            ->whereIn('student_id', $studentIds)
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->count();

        if ($enrolled !== count(array_unique($studentIds))) {
            throw new DomainRuleException('Attendance can only be recorded for students actively enrolled in this class.', 'student_id');
        }
    }

    private static function duplicate(): DomainRuleException
    {
        return new DomainRuleException(
            'Attendance has already been recorded for this student in this class on this date.',
            'date',
        );
    }
}
