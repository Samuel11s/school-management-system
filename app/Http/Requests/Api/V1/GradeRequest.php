<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Permission;
use Illuminate\Validation\Rule;

class GradeRequest extends ApiRequest
{
    public function authorize(): bool
    {
        if ($grade = $this->route('grade')) {
            return $this->user()->can('update', $grade);
        }

        return $this->user()->can(Permission::ManageGrades->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            /** Must be between 0 and the assessment's max_score. */
            'score' => ['required', 'numeric', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->isMethod('POST')) {
            $rules['assessment_id'] = ['required', 'integer', Rule::exists('assessments', 'id')];
            $rules['enrollment_id'] = ['required', 'integer', Rule::exists('enrollments', 'id')];
        }

        return $this->partialOnUpdate($rules);
    }
}
