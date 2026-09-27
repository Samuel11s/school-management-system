<?php

namespace App\Validation;

use App\Models\Course;
use Illuminate\Validation\Rule;

final class CourseRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?Course $course = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('courses', 'code')->ignore($course?->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'department' => ['nullable', 'string', 'max:100'],
            'credits' => ['required', 'integer', 'between:0,20'],
            'is_active' => ['boolean'],
            'prerequisite_ids' => ['array'],
            'prerequisite_ids.*' => ['integer', 'distinct', Rule::exists('courses', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'code.regex' => 'The course code may only contain letters, numbers and dashes.',
        ];
    }
}
