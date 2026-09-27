<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\StatusHistory;
use App\Models\Student;
use App\Models\User;
use App\Services\GradebookService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(GradebookService $gradebook): View
    {
        /** @var User $user */
        $user = auth()->user();
        $term = AcademicTerm::query()->current()->first();

        return view('livewire.dashboard', [
            'user' => $user,
            'term' => $term,
            'admin' => $user->isAdmin() ? $this->adminData($term) : null,
            'teaching' => $user->isTeacher() ? $this->teacherSections($user, $term) : collect(),
            'studentClasses' => $user->student ? $this->studentClasses($user->student, $term, $gradebook) : collect(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function adminData(?AcademicTerm $term): array
    {
        return [
            'activeStudents' => Student::query()->where('status', StudentStatus::Active->value)->count(),
            'courses' => Course::query()->where('is_active', true)->count(),
            'openSections' => $term ? $term->sections()->count() : 0,
            'activeEnrollments' => $term
                ? Enrollment::query()->active()->whereHas('section', fn ($q) => $q->where('academic_term_id', $term->id))->count()
                : 0,
            'nearlyFull' => $term
                ? Section::query()
                    ->with(['course', 'teacher'])
                    ->withEnrolledCount()
                    ->where('academic_term_id', $term->id)
                    ->get()
                    ->filter(fn (Section $s) => $s->capacity > 0 && $s->seatsTaken() / $s->capacity >= 0.8)
                    ->sortByDesc(fn (Section $s) => $s->seatsTaken() / $s->capacity)
                    ->take(5)
                : collect(),
            'recentActivity' => StatusHistory::query()
                ->with(['subject', 'changedBy'])
                ->where('subject_type', (new Enrollment)->getMorphClass())
                ->latest('id')
                ->limit(6)
                ->get(),
        ];
    }

    /**
     * @return Collection<int, Section>
     */
    private function teacherSections(User $user, ?AcademicTerm $term): Collection
    {
        if ($term === null) {
            return collect();
        }

        $today = now()->toDateString();

        return Section::query()
            ->with('course')
            ->withEnrolledCount()
            ->withExists(['attendanceRecords as attendance_taken_today' => fn ($q) => $q->whereDate('attended_on', $today)])
            ->where('teacher_id', $user->id)
            ->where('academic_term_id', $term->id)
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, array{enrollment: Enrollment, score: float|null, letter: string|null, attendance: float|null}>
     */
    private function studentClasses(Student $student, ?AcademicTerm $term, GradebookService $gradebook): Collection
    {
        return $student->enrollments()
            ->with(['section.course', 'section.teacher'])
            ->where('status', EnrollmentStatus::Enrolled->value)
            ->when($term, fn ($q) => $q->whereHas('section', fn ($s) => $s->where('academic_term_id', $term->id)))
            ->get()
            ->map(function (Enrollment $enrollment) use ($gradebook, $student) {
                $score = $gradebook->finalScore($enrollment);
                $records = AttendanceRecord::query()
                    ->where('section_id', $enrollment->section_id)
                    ->where('student_id', $student->id)
                    ->pluck('status');
                $countable = $records->reject(fn (AttendanceStatus $s) => $s === AttendanceStatus::Excused);

                return [
                    'enrollment' => $enrollment,
                    'score' => $score,
                    'letter' => $gradebook->calculator()->letterFor($score),
                    'attendance' => $countable->isNotEmpty()
                        ? round($countable->filter(fn (AttendanceStatus $s) => $s->countsAsAttended())->count() / $countable->count() * 100, 1)
                        : null,
                ];
            });
    }
}
