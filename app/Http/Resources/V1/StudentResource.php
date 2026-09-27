<?php

namespace App\Http\Resources\V1;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student record. Contact, guardian and birth date fields are only included
 * for administrators and the student themselves.
 *
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sensitive = $request->user()?->can('viewSensitive', $this->resource) ?? false;

        return [
            'id' => $this->id,
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'grade_level' => $this->grade_level,
            'admission_date' => $this->admission_date?->toDateString(),
            'status' => $this->status->value,
            'has_photo' => $this->photo_path !== null,
            'has_account' => $this->user_id !== null,
            'phone' => $this->when($sensitive, $this->phone),
            'date_of_birth' => $this->when($sensitive, fn () => $this->date_of_birth?->toDateString()),
            'address' => $this->when($sensitive, $this->address),
            'guardian_name' => $this->when($sensitive, $this->guardian_name),
            'guardian_phone' => $this->when($sensitive, $this->guardian_phone),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
