<?php

namespace App\Livewire\Courses;

use App\Exceptions\DomainRuleException;
use App\Livewire\Concerns\WithSorting;
use App\Models\Course;
use App\Services\CatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Courses')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination, WithSorting;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: '')]
    public string $active = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'department', 'active'], true)) {
            $this->resetPage();
        }
    }

    public function delete(int $courseId, CatalogService $catalog): void
    {
        $course = Course::query()->findOrFail($courseId);
        $this->authorize('delete', $course);

        try {
            $catalog->deleteCourse($course);
            $this->dispatch('notify', message: "Course {$course->code} deleted.");
        } catch (DomainRuleException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'danger');
        }
    }

    protected function sortableColumns(): array
    {
        return ['code', 'title', 'department', 'credits'];
    }

    protected function defaultSortField(): string
    {
        return 'code';
    }

    public function render(): View
    {
        $courses = Course::query()
            ->with('prerequisites:id,code')
            ->withCount('sections')
            ->search($this->search)
            ->when($this->department !== '', fn ($q) => $q->where('department', $this->department))
            ->when($this->active !== '', fn ($q) => $q->where('is_active', $this->active === '1'))
            ->orderBy($this->currentSortField(), $this->currentSortDirection())
            ->paginate(15);

        return view('livewire.courses.index', [
            'courses' => $courses,
            'departments' => Course::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }
}
