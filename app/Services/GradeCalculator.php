<?php

namespace App\Services;

/**
 * Pure grading arithmetic: weighted averages, letter grades and pass/fail.
 *
 * The final score of an enrollment is the weighted average of the graded
 * assessments, normalised by the total weight of those graded assessments
 * so that partially graded courses still yield a meaningful running score.
 */
final class GradeCalculator
{
    /**
     * @param  array<string, int|float>  $scale  letter => minimum percentage
     */
    public function __construct(
        private readonly array $scale = ['A' => 90, 'B' => 80, 'C' => 70, 'D' => 60, 'F' => 0],
        private readonly float $passingScore = 60.0,
    ) {}

    /**
     * @param  iterable<array{score: float|int|string|null, max_score: float|int|string, weight: float|int|string}>  $items
     */
    public function weightedPercentage(iterable $items): ?float
    {
        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($items as $item) {
            $max = (float) $item['max_score'];
            $weight = (float) $item['weight'];

            if ($item['score'] === null || $max <= 0 || $weight <= 0) {
                continue;
            }

            $weighted += ((float) $item['score'] / $max) * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return null;
        }

        return round($weighted / $totalWeight * 100, 2);
    }

    public function letterFor(?float $percentage): ?string
    {
        if ($percentage === null) {
            return null;
        }

        $scale = $this->scale;
        arsort($scale);

        foreach ($scale as $letter => $minimum) {
            if ($percentage >= $minimum) {
                return (string) $letter;
            }
        }

        return (string) array_key_last($scale);
    }

    public function passes(?float $percentage): bool
    {
        return $percentage !== null && $percentage >= $this->passingScore;
    }

    public function passingScore(): float
    {
        return $this->passingScore;
    }

    /**
     * @param  iterable<float|int|string|null>  $values
     */
    public function average(iterable $values): ?float
    {
        $sum = 0.0;
        $count = 0;

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $sum += (float) $value;
            $count++;
        }

        return $count > 0 ? round($sum / $count, 2) : null;
    }
}
