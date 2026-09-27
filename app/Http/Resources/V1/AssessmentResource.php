<?php

namespace App\Http\Resources\V1;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assessment
 */
class AssessmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'type' => $this->type->value,
            'max_score' => (float) $this->max_score,
            'weight' => (float) $this->weight,
            'due_on' => $this->due_on?->toDateString(),
        ];
    }
}
