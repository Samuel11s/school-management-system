<?php

namespace App\Livewire\Courses;

use App\Exceptions\DomainRuleException;
use App\Models\Course;
use App\Services\CatalogService;
use App\Validation\CourseRules;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?Course $course = null;

    public string $code = '';

    public string $title = '';

    public ?string $description = null;

    public ?string $department = null;

    public int|string $credits = 3;

    public bool $is_active = true;

    /** @var list<int|string> */
    public array $prerequisite_ids = [];

    public function mount(?Course $course = null): void
    {
        if ($course?->exists) {
            $this->authorize('update', $course);
            $this->course = $course;
            $this->fill($course->only(['code', 'title', 'description', 'department', 'credits', 'is_active']));
            $this->prerequisite_ids = $course->prerequisites()->pluck('courses.id')->all();

            return;
        }

        $this->authorize('create', Course::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return CourseRules::rules($this->course);
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return CourseRules::messages();
    }

    public function save(CatalogService $catalog): void
    {
        $this->course ? $this->authorize('update', $this->course) : $this->authorize('create', Course::class);

        $this->code = strtoupper(trim($this->code));
        /** @var array<string, mixed> $validated */
        $validated = $this->validate();
        $data = collect($validated)->except('prerequisite_ids')->map(fn ($v) => $v === '' ? null : $v)->all();

        try {
            $catalog->saveCourse($data, $validated['prerequisite_ids'] ?? [], $this->course);
        } catch (DomainRuleException $e) {
            $this->addError($e->field, $e->getMessage());

            return;
        }

        session()->flash('status', $this->course ? 'Course updated.' : 'Course created.');
        $this->redirectRoute('courses.index');
    }

    public function render(): View
    {
        return view('livewire.courses.form', [
            'availablePrerequisites' => Course::query()
                ->when($this->course, fn ($q) => $q->whereKeyNot($this->course->id))
                ->orderBy('code')
                ->get(['id', 'code', 'title']),
        ])->title($this->course ? 'Edit '.$this->course->code : 'Add course');
    }
}
