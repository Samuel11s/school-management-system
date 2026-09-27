<?php

namespace Database\Factories;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(AssessmentType::cases());

        return [
            'section_id' => Section::factory(),
            'title' => $type->label().' '.fake()->unique()->numberBetween(1, 9999),
            'type' => $type,
            'max_score' => 100,
            'weight' => 20,
            'due_on' => now()->addWeeks(fake()->numberBetween(-4, 6))->toDateString(),
        ];
    }
}
