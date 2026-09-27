<?php

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Attendance summaries and records with filters, scoped to what the user may see.
 */
#[Title('Attendance')]
class Report extends Component
{
    use WithPagination;

    #[Url]
    public ?string $term = null;

    #[Url(as: 'class', except: '')]
    public string $section = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $belowThresholdOnly = false;

    public function mount(): void
    {
        $this->term ??= (string) (AcademicTerm::query()->current()->value('id') ?? '');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return Builder<AttendanceRecord>
     */
    private function filteredQuery(User $user, bool $withStatus = true): Builder
    {
        return AttendanceRecord::query()
            ->visibleTo($user)
            ->when(ctype_digit((string) $this->term), fn ($q) => $q->whereHas('section', fn ($s) => $s->where('academic_term_id', (int) $this->term)))
            ->when(ctype_digit($this->section), fn ($q) => $q->where('section_id', (int) $this->section))
            ->when($withStatus && AttendanceStatus::tryFrom($this->status), fn ($q) => $q->where('status', $this->status))
            ->when(strtotime($this->from), fn ($q) => $q->whereDate('attended_on', '>=', $this->from))
            ->when(strtotime($this->to), fn ($q) => $q->whereDate('attended_on', '<=', $this->to))
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->search($this->search)));
    }

    public function render(AttendanceService $attendance): View
    {
        /** @var User $user */
        $user = auth()->user();

        // The summary ignores the status filter so that rates stay meaningful.
        $summary = $attendance->summarize($this->filteredQuery($user, withStatus: false));

        if ($this->belowThresholdOnly) {
            $summary = $summary->where('below_threshold', true);
        }

        $summary = $summary->sortBy('rate')->values();

        $students = Student::withTrashed()->whereKey($summary->pluck('student_id'))->get()->keyBy('id');

        $records = $this->filteredQuery($user)
            ->with(['student', 'section.course'])
            ->orderByDesc('attended_on')
            ->orderBy('section_id')
            ->paginate(20);

        return view('livewire.attendance.report', [
            'summary' => $summary->take(50),
            'summaryTotal' => $summary->count(),
            'students' => $students,
            'records' => $records,
            'terms' => AcademicTerm::query()->orderByDesc('starts_on')->pluck('name', 'id'),
            'sections' => Section::query()->visibleTo($user)
                ->with('course')
                ->when(ctype_digit((string) $this->term), fn ($q) => $q->where('academic_term_id', (int) $this->term))
                ->get()
                ->sortBy('name')
                ->mapWithKeys(fn (Section $s) => [$s->id => $s->name]),
            'statuses' => AttendanceStatus::options(),
            'threshold' => config('school.attendance.warning_threshold'),
        ]);
    }
}
