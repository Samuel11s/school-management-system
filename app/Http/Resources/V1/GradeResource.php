<?php

namespace App\Http\Resources\V1;

use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Grade
 */
class GradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_id' => $this->assessment_id,
            'enrollment_id' => $this->enrollment_id,
            'student_id' => $this->whenLoaded('enrollment', fn () => $this->enrollment->student_id),
            'score' => (float) $this->score,
            'percentage' => $this->whenLoaded('assessment', fn () => $this->percentage),
            'feedback' => $this->feedback,
            'graded_at' => $this->graded_at?->toIso8601String(),
            'assessment' => new AssessmentResource($this->whenLoaded('assessment')),
        ];
    }
}
