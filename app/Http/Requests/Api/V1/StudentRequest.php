<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Student;
use App\Validation\StudentRules;
use Illuminate\Validation\Rule;

class StudentRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create', Student::class)
            : $this->user()->can('update', $this->route('student'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Student|null $student */
        $student = $this->route('student');
        $rules = StudentRules::rules($student);

        if ($this->isMethod('POST')) {
            /** Create a student login account and email a password setup link. */
            $rules['create_account'] = ['sometimes', 'boolean'];

            if ($this->boolean('create_account')) {
                $rules['email'][] = Rule::unique('users', 'email');
            }
        } else {
            /** Reason recorded in the status history when the status changes. */
            $rules['status_reason'] = ['nullable', 'string', 'max:255'];
        }

        return $this->partialOnUpdate($rules);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StudentRules::messages();
    }
}
