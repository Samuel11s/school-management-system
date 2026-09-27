<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Section;
use App\Validation\SectionRules;

class SectionRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create', Section::class)
            : $this->user()->can('update', $this->route('section'));
    }

    protected function prepareForValidation(): void
    {
        $this->upperCaseCode();

        // The composite uniqueness rule needs course and term, so partial
        // updates are merged with the stored values.
        if ($section = $this->route('section')) {
            /** @var Section $section */
            $this->merge(array_merge(
                [...$section->only(['course_id', 'academic_term_id', 'teacher_id', 'code', 'room', 'schedule', 'capacity']), 'status' => $section->status->value],
                $this->all(),
            ));
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Section|null $section */
        $section = $this->route('section');

        return SectionRules::rules($this->all(), $section);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return SectionRules::messages();
    }
}
