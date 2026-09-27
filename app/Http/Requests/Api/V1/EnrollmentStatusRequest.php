<?php

namespace App\Http\Requests\Api\V1;

class EnrollmentStatusRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('enrollment'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Optional note stored in the enrollment status history. */
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
