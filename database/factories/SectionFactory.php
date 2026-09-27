<?php

namespace Database\Factories;

use App\Enums\SectionStatus;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'academic_term_id' => AcademicTerm::factory(),
            'teacher_id' => User::factory()->teacher(),
            'code' => strtoupper(fake()->unique()->bothify('?##')),
            'room' => 'Room '.fake()->numberBetween(100, 399),
            'schedule' => fake()->randomElement(['Mon/Wed 08:00-09:30', 'Tue/Thu 10:00-11:30', 'Mon/Wed/Fri 13:00-14:00']),
            'capacity' => 30,
            'status' => SectionStatus::Open,
        ];
    }

    public function capacity(int $capacity): static
    {
        return $this->state(fn () => ['capacity' => $capacity]);
    }

    public function status(SectionStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
