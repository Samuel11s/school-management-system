<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $department = fake()->randomElement(['Mathematics', 'Science', 'Languages', 'Humanities', 'Arts', 'Technology']);

        return [
            'code' => strtoupper(substr($department, 0, 3)).fake()->unique()->numberBetween(100, 9999),
            'title' => ucwords(fake()->words(3, true)),
            'description' => fake()->paragraph(),
            'department' => $department,
            'credits' => fake()->numberBetween(1, 5),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
