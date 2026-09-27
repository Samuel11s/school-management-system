<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'section_id' => Section::factory(),
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now()->subWeeks(2),
        ];
    }

    public function dropped(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Dropped,
            'dropped_at' => now()->subWeek(),
        ]);
    }

    public function completed(float $finalScore = 85.0, string $letter = 'B'): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now()->subDay(),
            'final_score' => $finalScore,
            'letter_grade' => $letter,
        ]);
    }
}
