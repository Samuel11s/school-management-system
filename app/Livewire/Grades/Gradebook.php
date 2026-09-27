<?php

namespace App\Livewire\Grades;

use App\Enums\AssessmentType;
use App\Exceptions\DomainRuleException;
use App\Models\Section;
use App\Services\GradebookService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Class gradebook: manage assessments and record scores in a grid.
 */
class Gradebook extends Component
{
    use AuthorizesRequests;

    public Section $section;

    /** @var array<int|string, array<int|string, string|float|null>> scores[enrollmentId][assessmentId] */
    public array $scores = [];

    public ?int $editingAssessmentId = null;

    public bool $showAssessmentForm = false;

    /** @var array{title: string, type: string, max_score: string|int, weight: string|int, due_on: string|null} */
    public array $assessment = [
        'title' => '',
        'type' => 'assignment',
        'max_score' => 100,
        'weight' => 10,
        'due_on' => null,
    ];

    public function mount(Section $section): void
    {
        $this->authorize('manageGrades', $section);
        $this->section = $section;
        $this->loadScores();
    }

    private function loadScores(): void
    {
        $this->scores = [];

        foreach ($this->section->activeEnrollments()->with('grades')->get() as $enrollment) {
            foreach ($enrollment->grades as $grade) {
                $this->scores[$enrollment->id][$grade->assessment_id] = rtrim(rtrim((string) $grade->score, '0'), '.');
            }
        }
    }

    public function newAssessment(): void
    {
        $this->authorize('manageGrades', $this->section);
        $this->resetValidation();
        $this->editingAssessmentId = null;
        $this->assessment = ['title' => '', 'type' => 'assignment', 'max_score' => 100, 'weight' => 10, 'due_on' => null];
        $this->showAssessmentForm = true;
    }

    public function editAssessment(int $assessmentId): void
    {
        $model = $this->section->assessments()->findOrFail($assessmentId);
        $this->authorize('update', $model);
        $this->resetValidation();

        $this->editingAssessmentId = $model->id;
        $this->assessment = [
            'title' => $model->title,
            'type' => $model->type->value,
            'max_score' => (string) (float) $model->max_score,
            'weight' => (string) (float) $model->weight,
            'due_on' => $model->due_on?->toDateString(),
        ];
        $this->showAssessmentForm = true;
    }

    public function saveAssessment(GradebookService $gradebook): void
    {
        $this->authorize('manageGrades', $this->section);

        $validated = $this->validate([
            'assessment.title' => [
                'required', 'string', 'max:255',
                Rule::unique('assessments', 'title')->where('section_id', $this->section->id)->ignore($this->editingAssessmentId),
            ],
            'assessment.type' => ['required', Rule::enum(AssessmentType::class)],
            'assessment.max_score' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'assessment.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'assessment.due_on' => ['nullable', 'date'],
        ], [], [
            'assessment.title' => 'title',
            'assessment.type' => 'type',
            'assessment.max_score' => 'maximum score',
            'assessment.weight' => 'weight',
            'assessment.due_on' => 'due date',
        ])['assessment'];

        $existing = $this->editingAssessmentId ? $this->section->assessments()->findOrFail($this->editingAssessmentId) : null;

        try {
            $gradebook->saveAssessment($this->section, [...$validated, 'due_on' => $validated['due_on'] ?: null], $existing);
        } catch (DomainRuleException $e) {
            $this->addError('assessment.'.$e->field, $e->getMessage());

            return;
        }

        $this->showAssessmentForm = false;
        $this->dispatch('notify', message: $existing ? 'Assessment updated.' : 'Assessment added.');
    }

    public function deleteAssessment(int $assessmentId): void
    {
        $model = $this->section->assessments()->findOrFail($assessmentId);
        $this->authorize('delete', $model);

        $model->delete();
        $this->loadScores();
        $this->dispatch('notify', message: "{$model->title} and its grades were deleted.");
    }

    public function saveGrades(GradebookService $gradebook): void
    {
        $this->authorize('manageGrades', $this->section);

        $assessments = $this->section->assessments()->get()->keyBy('id');
        $enrollmentIds = $this->section->activeEnrollments()->pluck('id')->all();

        $rules = [];
        $attributes = [];

        foreach ($this->scores as $enrollmentId => $row) {
            foreach ($row as $assessmentId => $score) {
                $max = (float) ($assessments->get($assessmentId)->max_score ?? 0);
                $rules["scores.$enrollmentId.$assessmentId"] = ['nullable', 'numeric', 'min:0', 'max:'.$max];
                $attributes["scores.$enrollmentId.$assessmentId"] = 'score';
            }
        }

        $this->validate($rules, [], $attributes);

        try {
            foreach ($assessments as $assessment) {
                $entries = [];

                foreach ($enrollmentIds as $enrollmentId) {
                    if (array_key_exists($assessment->id, $this->scores[$enrollmentId] ?? [])) {
                        $entries[$enrollmentId] = ['score' => $this->scores[$enrollmentId][$assessment->id]];
                    }
                }

                if ($entries !== []) {
                    $gradebook->recordGrades($assessment, $entries, auth()->user());
                }
            }
        } catch (DomainRuleException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'danger');

            return;
        }

        $this->loadScores();
        $this->dispatch('notify', message: 'Grades saved.');
    }

    public function render(GradebookService $gradebook): View
    {
        $this->section->load(['course', 'term']);

        return view('livewire.grades.gradebook', [
            'summary' => $gradebook->sectionSummary($this->section),
            'types' => AssessmentType::options(),
        ])->title('Gradebook · '.$this->section->name);
    }
}
