<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Course;
use App\Validation\CourseRules;

class CourseRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create', Course::class)
            : $this->user()->can('update', $this->route('course'));
    }

    protected function prepareForValidation(): void
    {
        $this->upperCaseCode();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Course|null $course */
        $course = $this->route('course');

        return $this->partialOnUpdate(CourseRules::rules($course));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CourseRules::messages();
    }
}
