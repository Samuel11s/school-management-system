<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AcademicTerm;
use App\Validation\AcademicTermRules;

class AcademicTermRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create', AcademicTerm::class)
            : $this->user()->can('update', $this->route('term'));
    }

    protected function prepareForValidation(): void
    {
        $this->upperCaseCode();

        // Date rules reference each other, so partial updates are merged with
        // the stored values before validation.
        if ($term = $this->route('term')) {
            /** @var AcademicTerm $term */
            $this->merge(array_merge([
                'name' => $term->name,
                'code' => $term->code,
                'starts_on' => $term->starts_on->toDateString(),
                'ends_on' => $term->ends_on->toDateString(),
                'enrollment_opens_on' => $term->enrollment_opens_on->toDateString(),
                'enrollment_closes_on' => $term->enrollment_closes_on->toDateString(),
                'is_current' => $term->is_current,
            ], $this->all()));
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var AcademicTerm|null $term */
        $term = $this->route('term');

        return AcademicTermRules::rules($term);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AcademicTermRules::messages();
    }
}
