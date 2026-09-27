<?php

namespace App\Livewire\Sections;

use App\Enums\SectionStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use App\Services\CatalogService;
use App\Validation\SectionRules;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?Section $section = null;

    public int|string $course_id = '';

    public int|string $academic_term_id = '';

    public int|string|null $teacher_id = null;

    public string $code = 'A';

    public ?string $room = null;

    public ?string $schedule = null;

    public int|string $capacity = 30;

    public string $status = 'open';

    public function mount(?Section $section = null): void
    {
        if ($section?->exists) {
            $this->authorize('update', $section);
            $this->section = $section;
            $this->fill($section->only(['course_id', 'academic_term_id', 'teacher_id', 'code', 'room', 'schedule', 'capacity']));
            $this->status = $section->status->value;

            return;
        }

        $this->authorize('create', Section::class);
        $this->academic_term_id = AcademicTerm::query()->current()->value('id') ?? '';
        $this->course_id = request()->integer('course') ?: '';
    }

    protected function rules(): array
    {
        return SectionRules::rules([
            'course_id' => $this->course_id,
            'academic_term_id' => $this->academic_term_id,
        ], $this->section);
    }

    protected function messages(): array
    {
        return SectionRules::messages();
    }

    public function save(CatalogService $catalog): void
    {
        $this->section ? $this->authorize('update', $this->section) : $this->authorize('create', Section::class);

        $this->code = strtoupper(trim($this->code));
        $data = collect($this->validate())->map(fn ($v) => $v === '' ? null : $v)->all();

        try {
            $section = $catalog->saveSection($data, $this->section);
        } catch (DomainRuleException $e) {
            $this->addError($e->field, $e->getMessage());

            return;
        }

        session()->flash('status', $this->section ? 'Class updated.' : 'Class created.');
        $this->redirectRoute('sections.show', $section);
    }

    public function render(): View
    {
        return view('livewire.sections.form', [
            'courses' => Course::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($this->section, fn ($q) => $q->orWhere('id', $this->section->course_id)))
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (Course $c) => [$c->id => $c->code.' – '.$c->title]),
            'terms' => AcademicTerm::query()->orderByDesc('starts_on')->pluck('name', 'id'),
            'teachers' => User::query()->teachers()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'statuses' => SectionStatus::options(),
        ])->title($this->section ? 'Edit '.$this->section->name : 'Add class');
    }
}
