<?php

namespace App\Livewire\Students;

use App\Enums\StudentStatus;
use App\Livewire\Concerns\WithSorting;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Students')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination, WithSorting;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'grade', except: '')]
    public string $gradeLevel = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Student::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'gradeLevel'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'gradeLevel');
        $this->resetPage();
    }

    public function delete(int $studentId, StudentService $students): void
    {
        $student = Student::query()->visibleTo(auth()->user())->findOrFail($studentId);
        $this->authorize('delete', $student);

        $students->delete($student);

        $this->dispatch('notify', message: "{$student->full_name} was deleted.", type: 'success');
    }

    protected function sortableColumns(): array
    {
        return ['last_name', 'student_number', 'grade_level', 'status', 'admission_date'];
    }

    protected function defaultSortField(): string
    {
        return 'last_name';
    }

    public function render(): View
    {
        $students = Student::query()
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when(StudentStatus::tryFrom($this->status), fn ($q, $status) => $q->where('status', $status->value))
            ->when(ctype_digit($this->gradeLevel), fn ($q) => $q->where('grade_level', (int) $this->gradeLevel))
            ->orderBy($this->currentSortField(), $this->currentSortDirection())
            ->orderBy('first_name')
            ->paginate(15);

        return view('livewire.students.index', [
            'students' => $students,
            'statuses' => StudentStatus::options(),
        ]);
    }
}
