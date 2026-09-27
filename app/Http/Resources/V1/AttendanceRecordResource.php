<?php

namespace App\Http\Resources\V1;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'student_id' => $this->student_id,
            'date' => $this->attended_on->toDateString(),
            'status' => $this->status->value,
            'remarks' => $this->remarks,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
