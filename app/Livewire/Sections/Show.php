<?php

namespace App\Livewire\Sections;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\GradebookService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Class detail page: roster and enrollment management for staff, and the
 * signed-in student's own grades and attendance for students.
 */
class Show extends Component
{
    use AuthorizesRequests;

    public Section $section;

    #[Url(as: 'roster', except: 'enrolled')]
    public string $rosterFilter = 'enrolled';

    public string $studentSearch = '';

    public function mount(Section $section): void
    {
        $this->authorize('view', $section);
        $this->section = $section;
    }

    public function enroll(int $studentId, EnrollmentService $enrollments): void
    {
        $this->authorize('manageEnrollments', $this->section);
        $student = Student::query()->findOrFail($studentId);

        try {
            $enrollments->enroll($student, $this->section);
            $this->studentSearch = '';
            $this->dispatch('notify', message: "{$student->full_name} enrolled in {$this->section->name}.");
        } catch (DomainRuleException $e) {
            $this->addError('enrollment', $e->getMessage());
        }
    }

    public function drop(int $enrollmentId, EnrollmentService $enrollments): void
    {
        $this->authorize('manageEnrollments', $this->section);
        $enrollment = $this->section->enrollments()->findOrFail($enrollmentId);

        try {
            $enrollments->drop($enrollment, 'Dropped by '.auth()->user()->name);
            $this->dispatch('notify', message: "{$enrollment->student->full_name} was dropped from the class.");
        } catch (DomainRuleException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'danger');
        }
    }

    public function complete(int $enrollmentId, EnrollmentService $enrollments): void
    {
        $this->authorize('manageEnrollments', $this->section);
        $enrollment = $this->section->enrollments()->findOrFail($enrollmentId);

        try {
            $enrollments->complete($enrollment, 'Marked complete');
            $this->dispatch('notify', message: "{$enrollment->student->full_name} completed the class.");
        } catch (DomainRuleException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'danger');
        }
    }

    public function render(GradebookService $gradebook): View
    {
        $user = auth()->user();
        $section = $this->section->load(['course.prerequisites', 'term', 'teacher']);
        $canViewRoster = $user->can('viewRoster', $section);
        $canManage = $user->can('manageEnrollments', $section);

        $roster = $canViewRoster
            ? $section->enrollments()
                ->with('student')
                ->when($this->rosterFilter !== 'all', fn ($q) => $q->where('status', EnrollmentStatus::tryFrom($this->rosterFilter)->value ?? 'enrolled'))
                ->get()
                ->sortBy(fn (Enrollment $e) => $e->student->last_name.' '.$e->student->first_name)
            : collect();

        $candidates = $canManage && mb_strlen(trim($this->studentSearch)) >= 2
            ? Student::query()
                ->search($this->studentSearch)
                ->where('status', StudentStatus::Active->value)
                ->whereDoesntHave('enrollments', fn ($q) => $q
                    ->where('section_id', $section->id)
                    ->whereIn('status', [EnrollmentStatus::Enrolled->value, EnrollmentStatus::Completed->value]))
                ->orderBy('last_name')
                ->limit(8)
                ->get()
            : collect();

        return view('livewire.sections.show', [
            'canViewRoster' => $canViewRoster,
            'canManage' => $canManage,
            'roster' => $roster,
            'candidates' => $candidates,
            'mine' => $user->student ? $this->ownResults($user->student, $gradebook) : null,
        ])->title($section->name);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ownResults(Student $student, GradebookService $gradebook): ?array
    {
        $enrollment = $this->section->enrollments()->where('student_id', $student->id)->with('grades')->first();

        if ($enrollment === null) {
            return null;
        }

        $score = $enrollment->status === EnrollmentStatus::Completed && $enrollment->final_score !== null
            ? (float) $enrollment->final_score
            : $gradebook->finalScore($enrollment);

        return [
            'enrollment' => $enrollment,
            'assessments' => $this->section->assessments()->orderBy('due_on')->get(),
            'grades' => $enrollment->grades->keyBy('assessment_id'),
            'score' => $score,
            'letter' => $enrollment->letter_grade ?? $gradebook->calculator()->letterFor($score),
            'attendance' => AttendanceRecord::query()
                ->where('section_id', $this->section->id)
                ->where('student_id', $student->id)
                ->orderByDesc('attended_on')
                ->get(),
        ];
    }
}
