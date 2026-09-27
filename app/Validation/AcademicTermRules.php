<?php

namespace App\Validation;

use App\Models\AcademicTerm;
use Illuminate\Validation\Rule;

final class AcademicTermRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?AcademicTerm $term = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('academic_terms', 'code')->ignore($term?->id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'enrollment_opens_on' => ['required', 'date', 'before_or_equal:enrollment_closes_on'],
            'enrollment_closes_on' => ['required', 'date', 'before_or_equal:ends_on'],
            'is_current' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'code.regex' => 'The term code may only contain letters, numbers and dashes.',
            'enrollment_closes_on.before_or_equal' => 'Enrollment must close on or before the last day of the term.',
        ];
    }
}
