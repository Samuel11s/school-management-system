<?php

namespace App\Http\Resources\V1;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'department' => $this->department,
            'credits' => $this->credits,
            'is_active' => $this->is_active,
            'prerequisites' => $this->whenLoaded('prerequisites', fn () => $this->prerequisites->map(fn (Course $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'title' => $c->title,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
