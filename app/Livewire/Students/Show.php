<?php

namespace App\Livewire\Students;

use App\Enums\EnrollmentStatus;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\GradebookService;
use App\Services\StudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Student profile: details, enrollments with grades, attendance and history.
 */
class Show extends Component
{
    use AuthorizesRequests;

    public Student $student;

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        $this->student = $student;
    }

    public function delete(StudentService $students): void
    {
        $this->authorize('delete', $this->student);
        $students->delete($this->student);

        session()->flash('status', 'Student deleted.');
        $this->redirectRoute('students.index');
    }

    public function render(GradebookService $gradebook, AttendanceService $attendance): View
    {
        $user = auth()->user();
        $student = $this->student->load(['user', 'statusHistories.changedBy']);

        $enrollments = $student->enrollments()
            ->visibleTo($user)
            ->with(['section.course', 'section.term', 'section.teacher'])
            ->get()
            ->sortByDesc(fn (Enrollment $e) => $e->section->term->starts_on)
            ->map(fn (Enrollment $e) => [
                'enrollment' => $e,
                'score' => $e->status === EnrollmentStatus::Completed
                    ? ($e->final_score !== null ? (float) $e->final_score : null)
                    : $gradebook->finalScore($e),
            ]);

        $sectionIds = $enrollments->pluck('enrollment.section_id');

        $attendanceSummary = $attendance->summarize(
            AttendanceRecord::query()
                ->where('student_id', $student->id)
                ->whereIn('section_id', $sectionIds)
        )->first();

        $completed = $enrollments->filter(fn ($row) => $row['enrollment']->status === EnrollmentStatus::Completed);

        return view('livewire.students.show', [
            'enrollments' => $enrollments,
            'attendanceSummary' => $attendanceSummary,
            'creditsEarned' => $completed
                ->filter(fn ($row) => $gradebook->calculator()->passes($row['score']))
                ->sum(fn ($row) => $row['enrollment']->section->course->credits),
            'cumulativeAverage' => $gradebook->calculator()->average($completed->pluck('score')),
            'calculator' => $gradebook->calculator(),
            'canSeeSensitive' => $user->can('viewSensitive', $student),
        ])->title($student->full_name);
    }
}
