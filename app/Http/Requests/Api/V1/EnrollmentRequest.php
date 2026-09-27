<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Enrollment;
use Illuminate\Validation\Rule;

class EnrollmentRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Enrollment::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            /** Optional note stored in the enrollment status history. */
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
