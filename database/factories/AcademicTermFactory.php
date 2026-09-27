<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth()->subWeeks(2);

        return [
            'name' => 'Term '.fake()->unique()->numberBetween(1, 99999),
            'code' => strtoupper(fake()->unique()->bothify('T####??')),
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addMonths(4)->toDateString(),
            'enrollment_opens_on' => $start->copy()->subMonth()->toDateString(),
            'enrollment_closes_on' => now()->addMonth()->toDateString(),
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true]);
    }

    public function enrollmentClosed(): static
    {
        return $this->state(fn () => [
            'enrollment_opens_on' => now()->subMonths(2)->toDateString(),
            'enrollment_closes_on' => now()->subDay()->toDateString(),
        ]);
    }

    public function enrollmentNotYetOpen(): static
    {
        return $this->state(fn () => [
            'enrollment_opens_on' => now()->addDays(3)->toDateString(),
            'enrollment_closes_on' => now()->addMonth()->toDateString(),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subMonths(10)->toDateString(),
            'ends_on' => now()->subMonths(6)->toDateString(),
            'enrollment_opens_on' => now()->subMonths(11)->toDateString(),
            'enrollment_closes_on' => now()->subMonths(10)->toDateString(),
        ]);
    }
}
