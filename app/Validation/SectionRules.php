<?php

namespace App\Validation;

use App\Enums\Role;
use App\Enums\SectionStatus;
use App\Models\Section;
use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;

final class SectionRules
{
    /**
     * @param  array<string, mixed>  $input  raw input (used for the composite uniqueness rule)
     * @return array<string, array<int, mixed>>
     */
    public static function rules(array $input, ?Section $section = null): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'academic_term_id' => ['required', 'integer', Rule::exists('academic_terms', 'id')],
            'teacher_id' => ['nullable', 'integer', self::teacherRule()],
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('sections', 'code')
                    ->where('course_id', $input['course_id'] ?? null)
                    ->where('academic_term_id', $input['academic_term_id'] ?? null)
                    ->ignore($section?->id),
            ],
            'room' => ['nullable', 'string', 'max:50'],
            'schedule' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'between:1,500'],
            'status' => ['required', Rule::enum(SectionStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'code.unique' => 'This course already has a class with this code in the selected term.',
            'code.regex' => 'The class code may only contain letters, numbers and dashes.',
        ];
    }

    /**
     * The assigned teacher must be an active user with the teacher role.
     */
    private static function teacherRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $isTeacher = User::query()
                ->whereKey($value)
                ->where('is_active', true)
                ->role(Role::Teacher->value)
                ->exists();

            if (! $isTeacher) {
                $fail('The selected teacher must be an active user with the teacher role.');
            }
        };
    }
}
