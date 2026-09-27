<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AssessmentType;
use App\Enums\Permission;
use App\Models\Assessment;
use Illuminate\Validation\Rule;

class AssessmentRequest extends ApiRequest
{
    public function authorize(): bool
    {
        if ($assessment = $this->route('assessment')) {
            return $this->user()->can('update', $assessment);
        }

        // The class-level check runs in the controller once section_id is validated.
        return $this->user()->can(Permission::ManageGrades->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Assessment|null $assessment */
        $assessment = $this->route('assessment');
        $sectionId = $assessment->section_id ?? $this->input('section_id');

        $rules = [
            'title' => ['required', 'string', 'max:255', Rule::unique('assessments', 'title')->where('section_id', $sectionId)->ignore($assessment?->id)],
            'type' => ['required', Rule::enum(AssessmentType::class)],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:1000'],
            /** Percentage of the final grade; all assessments of a class may total at most 100. */
            'weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'due_on' => ['nullable', 'date'],
        ];

        if ($assessment === null) {
            $rules['section_id'] = ['required', 'integer', Rule::exists('sections', 'id')];
        }

        return $this->partialOnUpdate($rules);
    }
}
