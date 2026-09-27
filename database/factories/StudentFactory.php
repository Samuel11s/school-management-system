<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+1 555 ### ####'),
            'date_of_birth' => fake()->dateTimeBetween('-19 years', '-12 years')->format('Y-m-d'),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->numerify('+1 555 ### ####'),
            'grade_level' => fake()->numberBetween(7, 12),
            'admission_date' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'status' => StudentStatus::Active,
        ];
    }

    public function status(StudentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /**
     * Create a linked user account with the student role.
     */
    public function withAccount(): static
    {
        // A lazy attribute receives the final attributes, including create() overrides.
        return $this->state([
            'user_id' => fn (array $attributes) => User::factory()->withRole(Role::Student)->create([
                'name' => $attributes['first_name'].' '.$attributes['last_name'],
                'email' => $attributes['email'],
            ])->id,
        ]);
    }
}
