<?php

namespace App\Livewire\Sections;

use App\Enums\SectionStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use App\Services\CatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Classes')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url]
    public ?string $term = null;

    #[Url(except: '')]
    public string $course = '';

    #[Url(except: '')]
    public string $teacher = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        // Default to the current term on first visit.
        $this->term ??= (string) (AcademicTerm::query()->current()->value('id') ?? '');
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function delete(int $sectionId, CatalogService $catalog): void
    {
        $section = Section::query()->findOrFail($sectionId);
        $this->authorize('delete', $section);

        try {
            $catalog->deleteSection($section);
            $this->dispatch('notify', message: "Class {$section->name} deleted.");
        } catch (DomainRuleException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'danger');
        }
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $sections = Section::query()
            ->select('sections.*')
            ->visibleTo($user)
            ->with(['course', 'term', 'teacher'])
            ->withEnrolledCount()
            ->when(ctype_digit((string) $this->term), fn ($q) => $q->where('academic_term_id', (int) $this->term))
            ->when(ctype_digit($this->course), fn ($q) => $q->where('course_id', (int) $this->course))
            ->when(ctype_digit($this->teacher), fn ($q) => $q->where('teacher_id', (int) $this->teacher))
            ->when(SectionStatus::tryFrom($this->status), fn ($q, $status) => $q->where('status', $status->value))
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('course', fn ($c) => $c->search($this->search)))
            ->join('courses', 'courses.id', '=', 'sections.course_id')
            ->orderBy('courses.code')
            ->orderBy('sections.code')
            ->paginate(15);

        return view('livewire.sections.index', [
            'sections' => $sections,
            'terms' => AcademicTerm::query()->orderByDesc('starts_on')->pluck('name', 'id'),
            'courses' => Course::query()->orderBy('code')->pluck('code', 'id'),
            'teachers' => $user->isAdmin() ? User::query()->teachers()->orderBy('name')->pluck('name', 'id') : collect(),
            'statuses' => SectionStatus::options(),
        ]);
    }
}
