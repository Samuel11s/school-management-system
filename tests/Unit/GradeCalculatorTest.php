<?php

namespace Tests\Unit;

use App\Services\GradeCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GradeCalculatorTest extends TestCase
{
    private GradeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new GradeCalculator(['A' => 90, 'B' => 80, 'C' => 70, 'D' => 60, 'F' => 0], 60.0);
    }

    public function test_weighted_percentage_combines_scores_by_weight(): void
    {
        $result = $this->calculator->weightedPercentage([
            ['score' => 45, 'max_score' => 50, 'weight' => 20],   // 90%
            ['score' => 70, 'max_score' => 100, 'weight' => 30],  // 70%
            ['score' => 80, 'max_score' => 100, 'weight' => 50],  // 80%
        ]);

        // (0.9*20 + 0.7*30 + 0.8*50) / 100 = 79
        $this->assertSame(79.0, $result);
    }

    public function test_ungraded_assessments_are_excluded_from_the_running_score(): void
    {
        $result = $this->calculator->weightedPercentage([
            ['score' => 18, 'max_score' => 20, 'weight' => 10],   // 90%
            ['score' => null, 'max_score' => 100, 'weight' => 90],
        ]);

        $this->assertSame(90.0, $result);
    }

    public function test_weighted_percentage_is_null_without_graded_items(): void
    {
        $this->assertNull($this->calculator->weightedPercentage([]));
        $this->assertNull($this->calculator->weightedPercentage([
            ['score' => null, 'max_score' => 100, 'weight' => 50],
        ]));
    }

    public function test_items_with_zero_weight_or_max_are_ignored(): void
    {
        $result = $this->calculator->weightedPercentage([
            ['score' => 5, 'max_score' => 0, 'weight' => 50],
            ['score' => 10, 'max_score' => 10, 'weight' => 0],
            ['score' => 30, 'max_score' => 40, 'weight' => 25],
        ]);

        $this->assertSame(75.0, $result);
    }

    public function test_decimal_strings_from_the_database_are_supported(): void
    {
        $result = $this->calculator->weightedPercentage([
            ['score' => '33.50', 'max_score' => '50.00', 'weight' => '25.00'],
        ]);

        $this->assertSame(67.0, $result);
    }

    /**
     * @return array<string, array{float|null, string|null}>
     */
    public static function letterProvider(): array
    {
        return [
            'perfect' => [100.0, 'A'],
            'lower A boundary' => [90.0, 'A'],
            'just below A' => [89.99, 'B'],
            'C' => [70.0, 'C'],
            'D' => [65.5, 'D'],
            'fail' => [59.99, 'F'],
            'zero' => [0.0, 'F'],
            'no score' => [null, null],
        ];
    }

    #[DataProvider('letterProvider')]
    public function test_letter_grades_follow_the_scale(?float $percentage, ?string $expected): void
    {
        $this->assertSame($expected, $this->calculator->letterFor($percentage));
    }

    public function test_scale_order_does_not_matter(): void
    {
        $calculator = new GradeCalculator(['F' => 0, 'P' => 50, 'D' => 75]);

        $this->assertSame('D', $calculator->letterFor(80));
        $this->assertSame('P', $calculator->letterFor(50));
        $this->assertSame('F', $calculator->letterFor(49));
    }

    public function test_passing_threshold(): void
    {
        $this->assertTrue($this->calculator->passes(60.0));
        $this->assertFalse($this->calculator->passes(59.9));
        $this->assertFalse($this->calculator->passes(null));
    }

    public function test_average_skips_nulls(): void
    {
        $this->assertSame(80.0, $this->calculator->average([70, null, 90]));
        $this->assertNull($this->calculator->average([null, null]));
    }
}
