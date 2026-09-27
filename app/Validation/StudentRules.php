<?php

namespace App\Validation;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Validation rules for student records, shared by the web UI and the API.
 */
final class StudentRules
{
    public const PHONE_PATTERN = '/^[0-9+()\-\s.]{5,30}$/';

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?Student $student = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('students', 'email')->ignore($student?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:'.self::PHONE_PATTERN],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'address' => ['nullable', 'string', 'max:500'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:30', 'regex:'.self::PHONE_PATTERN],
            'grade_level' => ['nullable', 'integer', 'between:1,12'],
            'admission_date' => ['required', 'date', 'after:1900-01-01'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Profile photos: raster images only (no SVG), limited in size.
     *
     * @return array<int, mixed>
     */
    public static function photo(): array
    {
        return [
            'nullable',
            File::image()
                ->types(config('school.uploads.photo_mimes'))
                ->max((int) config('school.uploads.photo_max_kilobytes')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'phone.regex' => 'The phone number may only contain digits, spaces and + ( ) - . characters.',
            'guardian_phone.regex' => 'The guardian phone number may only contain digits, spaces and + ( ) - . characters.',
        ];
    }
}
