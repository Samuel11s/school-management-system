<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'student_id' => Student::factory(),
            'attended_on' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
            'status' => fake()->randomElement([
                AttendanceStatus::Present, AttendanceStatus::Present, AttendanceStatus::Present,
                AttendanceStatus::Late, AttendanceStatus::Absent, AttendanceStatus::Excused,
            ]),
        ];
    }
}
