<?php

namespace App\Http\Resources\V1;

use App\Models\AcademicTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AcademicTerm
 */
class AcademicTermResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'enrollment_opens_on' => $this->enrollment_opens_on->toDateString(),
            'enrollment_closes_on' => $this->enrollment_closes_on->toDateString(),
            'is_current' => $this->is_current,
            'is_enrollment_open' => $this->isEnrollmentOpen(),
        ];
    }
}
