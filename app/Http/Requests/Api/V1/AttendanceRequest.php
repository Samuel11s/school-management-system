<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Permission;
use App\Enums\AttendanceStatus;
use Illuminate\Validation\Rule;

class AttendanceRequest extends ApiRequest
{
    public function authorize(): bool
    {
        if ($record = $this->route('attendanceRecord')) {
            return $this->user()->can('update', $record);
        }

        return $this->user()->can(Permission::ManageAttendance->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isMethod('POST')) {
            $rules['section_id'] = ['required', 'integer', Rule::exists('sections', 'id')];
            $rules['student_id'] = ['required', 'integer', Rule::exists('students', 'id')];
            /** Session date (Y-m-d); not in the future and within the term. */
            $rules['date'] = ['required', 'date_format:Y-m-d'];
        }

        return $this->partialOnUpdate($rules);
    }
}
