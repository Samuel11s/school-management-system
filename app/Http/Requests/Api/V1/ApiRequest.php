<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base API request. Authorization is performed by policies in the
 * controllers, so every request passes authorize() here.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * PUT and PATCH accept partial payloads: only submitted fields are validated.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    protected function partialOnUpdate(array $rules): array
    {
        if (! $this->isMethod('PUT') && ! $this->isMethod('PATCH')) {
            return $rules;
        }

        return array_map(fn (array $fieldRules) => ['sometimes', ...$fieldRules], $rules);
    }

    /**
     * Upper-case identifier codes so uniqueness checks match stored values.
     */
    protected function upperCaseCode(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }
}
