<?php

namespace App\Http\Resources\V1;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'section_id' => $this->section_id,
            'status' => $this->status->value,
            'enrolled_at' => $this->enrolled_at->toIso8601String(),
            'dropped_at' => $this->dropped_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'final_score' => $this->final_score !== null ? (float) $this->final_score : null,
            'letter_grade' => $this->letter_grade,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'student_number' => $this->student->student_number,
                'full_name' => $this->student->full_name,
            ]),
            'section' => new SectionResource($this->whenLoaded('section')),
            'history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'reason' => $h->reason,
                'changed_at' => $h->created_at->toIso8601String(),
            ])->values()),
        ];
    }
}
