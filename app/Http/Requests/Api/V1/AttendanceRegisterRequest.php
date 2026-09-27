<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AttendanceStatus;
use Illuminate\Validation\Rule;

class AttendanceRegisterRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageAttendance', $this->route('section'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Session date (Y-m-d); not in the future and within the term. */
            'date' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.student_id' => ['required', 'integer', 'distinct'],
            'entries.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'entries.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
