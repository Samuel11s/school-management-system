<?php

namespace Tests\Feature\Domain;

use App\Exceptions\DomainRuleException;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\GradebookService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchool;
use Tests\TestCase;

class GradebookServiceTest extends TestCase
{
    use BuildsSchool, RefreshDatabase;

    private GradebookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GradebookService::class);
    }

    private function assessmentData(array $overrides = []): array
    {
        return ['title' => 'Midterm', 'type' => 'exam', 'max_score' => 100, 'weight' => 30, 'due_on' => null, ...$overrides];
    }

    public function test_assessment_weights_cannot_exceed_one_hundred_percent(): void
    {
        $section = $this->section();
        $this->service->saveAssessment($section, $this->assessmentData(['title' => 'A', 'weight' => 70]));

        try {
            $this->service->saveAssessment($section, $this->assessmentData(['title' => 'B', 'weight' => 40]));
            $this->fail('Expected weight validation.');
        } catch (DomainRuleException $e) {
            $this->assertSame('weight', $e->field);
            $this->assertStringContainsString('30% is still available', $e->getMessage());
        }

        $this->service->saveAssessment($section, $this->assessmentData(['title' => 'C', 'weight' => 30]));
        $this->assertEquals(100, $section->assessments()->sum('weight'));
    }

    public function test_editing_an_assessment_ignores_its_own_weight(): void
    {
        $section = $this->section();
        $assessment = $this->service->saveAssessment($section, $this->assessmentData(['weight' => 100]));

        $updated = $this->service->saveAssessment($section, $this->assessmentData(['weight' => 90]), $assessment);

        $this->assertEquals(90, $updated->weight);
    }

    public function test_max_score_cannot_drop_below_recorded_scores(): void
    {
        $section = $this->section();
        $enrollment = $this->enroll($this->activeStudent(), $section);
        $assessment = $this->service->saveAssessment($section, $this->assessmentData());
        $this->service->recordGrade($assessment, $enrollment, 95, null, null);

        $this->expectException(DomainRuleException::class);

        $this->service->saveAssessment($section, $this->assessmentData(['max_score' => 50]), $assessment);
    }

    public function test_grade_is_recorded_and_updated_in_place(): void
    {
        $section = $this->section();
        $enrollment = $this->enroll($this->activeStudent(), $section);
        $assessment = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 50]);
        $teacher = $section->teacher;

        $this->service->recordGrade($assessment, $enrollment, 40, 'Good', $teacher);
        $grade = $this->service->recordGrade($assessment, $enrollment, 45.5, 'Better', $teacher);

        $this->assertSame(1, Grade::query()->count());
        $this->assertSame('45.50', $grade->score);
        $this->assertSame(91.0, $grade->percentage);
        $this->assertSame($teacher->id, $grade->graded_by);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function invalidScores(): array
    {
        return ['negative' => [-1.0], 'above maximum' => [50.01]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidScores')]
    public function test_invalid_scores_are_rejected(float $score): void
    {
        $section = $this->section();
        $enrollment = $this->enroll($this->activeStudent(), $section);
        $assessment = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 50]);

        try {
            $this->service->recordGrade($assessment, $enrollment, $score, null, null);
            $this->fail('Expected invalid score to be rejected.');
        } catch (DomainRuleException $e) {
            $this->assertSame('score', $e->field);
        }

        $this->assertSame(0, Grade::query()->count());
    }

    public function test_grades_require_an_active_enrollment_in_the_same_class(): void
    {
        $section = $this->section();
        $assessment = Assessment::factory()->create(['section_id' => $section->id]);
        $otherClass = $this->enroll($this->activeStudent(), $this->section());
        $dropped = Enrollment::factory()->dropped()->create(['section_id' => $section->id]);

        foreach ([$otherClass, $dropped] as $enrollment) {
            try {
                $this->service->recordGrade($assessment, $enrollment, 50, null, null);
                $this->fail('Expected the grade to be rejected.');
            } catch (DomainRuleException $e) {
                $this->assertSame('enrollment_id', $e->field);
            }
        }
    }

    public function test_database_prevents_duplicate_grades(): void
    {
        $grade = Grade::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Grade::factory()->create(['assessment_id' => $grade->assessment_id, 'enrollment_id' => $grade->enrollment_id]);
    }

    public function test_bulk_recording_is_atomic(): void
    {
        $section = $this->section();
        $good = $this->enroll($this->activeStudent(), $section);
        $bad = $this->enroll($this->activeStudent(), $section);
        $assessment = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 10]);

        try {
            $this->service->recordGrades($assessment, [
                $good->id => ['score' => 8],
                $bad->id => ['score' => 11],
            ], null);
            $this->fail('Expected the batch to fail.');
        } catch (DomainRuleException) {
        }

        $this->assertSame(0, Grade::query()->count());
    }

    public function test_clearing_a_score_removes_the_grade(): void
    {
        $grade = Grade::factory()->create();

        $this->service->recordGrades($grade->assessment, [$grade->enrollment_id => ['score' => '']], null);

        $this->assertModelMissing($grade);
    }

    public function test_section_summary_reports_averages_and_distribution(): void
    {
        $section = $this->section();
        $exam = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 100, 'weight' => 50]);
        $quiz = Assessment::factory()->create(['section_id' => $section->id, 'max_score' => 10, 'weight' => 50]);
        $alice = $this->enroll($this->activeStudent(['last_name' => 'Alpha']), $section);
        $bob = $this->enroll($this->activeStudent(['last_name' => 'Bravo']), $section);
        $this->service->recordGrade($exam, $alice, 100, null, null);
        $this->service->recordGrade($quiz, $alice, 8, null, null);  // alice: 90
        $this->service->recordGrade($exam, $bob, 60, null, null);   // bob: 60 (quiz ungraded)

        $summary = $this->service->sectionSummary($section);

        $this->assertSame(75.0, $summary['class_average']);
        $this->assertSame(['A' => 1, 'D' => 1], $summary['distribution']);
        $this->assertSame(80.0, $summary['assessments']->first()['average']);
        $this->assertSame([90.0, 60.0], $summary['students']->pluck('score')->all());
        $this->assertSame(100.0, $summary['total_weight']);
    }
}
