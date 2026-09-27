<?php

namespace App\Http\Resources\V1;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A class (section) of a course in an academic term.
 *
 * @mixin Section
 */
class SectionResource extends JsonResource
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
            'course_id' => $this->course_id,
            'academic_term_id' => $this->academic_term_id,
            'teacher_id' => $this->teacher_id,
            'room' => $this->room,
            'schedule' => $this->schedule,
            'capacity' => $this->capacity,
            'enrolled_count' => $this->seatsTaken(),
            'seats_available' => $this->seatsAvailable(),
            'status' => $this->status->value,
            'course' => new CourseResource($this->whenLoaded('course')),
            'term' => new AcademicTermResource($this->whenLoaded('term')),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher ? [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ] : null),
        ];
    }
}
