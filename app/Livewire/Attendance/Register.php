<?php

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Take (or correct) the attendance register of a class for one date.
 */
class Register extends Component
{
    use AuthorizesRequests;

    public Section $section;

    #[Url]
    public string $date = '';

    /** @var array<int|string, array{status: string, remarks: string|null}> keyed by student id */
    public array $entries = [];

    public bool $alreadyRecorded = false;

    public function mount(Section $section): void
    {
        $this->authorize('manageAttendance', $section);
        $this->section = $section;

        $term = $section->term;
        $default = Carbon::today()->min($term->ends_on)->max($term->starts_on);
        $this->date = $this->date !== '' && strtotime($this->date) ? $this->date : $default->toDateString();

        $this->loadRegister();
    }

    public function updatedDate(): void
    {
        $this->resetValidation();
        $this->loadRegister();
    }

    private function loadRegister(): void
    {
        $date = rescue(fn () => Carbon::parse($this->date), null, false);

        $existing = $date
            ? AttendanceRecord::query()
                ->where('section_id', $this->section->id)
                ->whereDate('attended_on', $date->toDateString())
                ->get()
                ->keyBy('student_id')
            : collect();

        $this->alreadyRecorded = $existing->isNotEmpty();
        $this->entries = [];

        foreach ($this->section->activeEnrollments()->pluck('student_id') as $studentId) {
            $record = $existing->get($studentId);
            $this->entries[$studentId] = [
                'status' => $record?->status->value ?? AttendanceStatus::Present->value,
                'remarks' => $record?->remarks,
            ];
        }
    }

    public function markAll(string $status): void
    {
        if (AttendanceStatus::tryFrom($status) === null) {
            return;
        }

        foreach (array_keys($this->entries) as $studentId) {
            $this->entries[$studentId]['status'] = $status;
        }
    }

    public function save(AttendanceService $attendance): void
    {
        $this->authorize('manageAttendance', $this->section);

        $this->validate([
            'date' => ['required', 'date'],
            'entries' => ['array'],
            'entries.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'entries.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [], ['entries.*.status' => 'status', 'entries.*.remarks' => 'remarks']);

        try {
            $records = $attendance->recordRegister($this->section, Carbon::parse($this->date), $this->entries, auth()->user());
        } catch (DomainRuleException $e) {
            $this->addError($e->field === 'student_id' ? 'entries' : $e->field, $e->getMessage());

            return;
        }

        $this->alreadyRecorded = true;
        $this->dispatch('notify', message: "Attendance saved for {$records->count()} students.");
    }

    public function render(): View
    {
        $this->section->load(['course', 'term']);

        $students = $this->section->activeEnrollments()
            ->with('student')
            ->get()
            ->pluck('student')
            ->sortBy(fn ($s) => $s->last_name.' '.$s->first_name);

        $recentSessions = AttendanceRecord::query()
            ->where('section_id', $this->section->id)
            ->selectRaw('attended_on, COUNT(*) as total, SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as attended', [
                AttendanceStatus::Present->value, AttendanceStatus::Late->value,
            ])
            ->groupBy('attended_on')
            ->orderByDesc('attended_on')
            ->limit(10)
            ->toBase()
            ->get();

        return view('livewire.attendance.register', [
            'students' => $students,
            'statuses' => AttendanceStatus::cases(),
            'recentSessions' => $recentSessions,
        ])->title('Attendance · '.$this->section->name);
    }
}
