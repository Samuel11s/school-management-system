<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assessments, grade recording and grade summaries for a class.
 */
final class GradebookService
{
    public const MAX_TOTAL_WEIGHT = 100.0;

    public function __construct(private readonly GradeCalculator $calculator) {}

    public function calculator(): GradeCalculator
    {
        return $this->calculator;
    }

    /**
     * Create or update an assessment, keeping the total weight of the class
     * at or below 100% and never dropping max_score below recorded scores.
     *
     * @param  array{title: string, type: mixed, max_score: float|int|string, weight: float|int|string, due_on?: string|null}  $data
     *
     * @throws DomainRuleException
     */
    public function saveAssessment(Section $section, array $data, ?Assessment $assessment = null): Assessment
    {
        $otherWeights = (float) $section->assessments()
            ->when($assessment, fn ($q) => $q->whereKeyNot($assessment->id))
            ->sum('weight');

        if ($otherWeights + (float) $data['weight'] > self::MAX_TOTAL_WEIGHT) {
            throw new DomainRuleException(sprintf(
                'The total weight of all assessments in this class cannot exceed 100%%; %s%% is still available.',
                rtrim(rtrim(number_format(self::MAX_TOTAL_WEIGHT - $otherWeights, 2), '0'), '.'),
            ), 'weight');
        }

        if ($assessment !== null) {
            $highestScore = (float) $assessment->grades()->max('score');

            if ((float) $data['max_score'] < $highestScore) {
                throw new DomainRuleException(
                    "The maximum score cannot be lower than an already recorded score ({$highestScore}).",
                    'max_score',
                );
            }
        }

        $assessment ??= new Assessment(['section_id' => $section->id]);
        $assessment->fill($data)->save();

        return $assessment;
    }

    /**
     * Record (create or update) a student's score for an assessment.
     *
     * @throws DomainRuleException
     */
    public function recordGrade(Assessment $assessment, Enrollment $enrollment, float $score, ?string $feedback, ?User $grader): Grade
    {
        if ($enrollment->section_id !== $assessment->section_id) {
            throw new DomainRuleException('The student is not enrolled in the class for this assessment.', 'enrollment_id');
        }

        if ($enrollment->status !== EnrollmentStatus::Enrolled) {
            throw new DomainRuleException('Grades can only be recorded for active enrollments.', 'enrollment_id');
        }

        if ($score < 0 || $score > (float) $assessment->max_score) {
            throw new DomainRuleException(
                "The score must be between 0 and {$assessment->max_score}.",
                'score',
            );
        }

        return Grade::query()->updateOrCreate(
            ['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id],
            [
                'score' => $score,
                'feedback' => $feedback,
                'graded_by' => $grader?->id,
                'graded_at' => now(),
            ],
        );
    }

    /**
     * Record several scores for one assessment atomically.
     *
     * @param  array<int, array{score: float|int|string|null, feedback?: string|null}>  $entries  keyed by enrollment id
     * @return Collection<int, Grade>
     *
     * @throws DomainRuleException
     */
    public function recordGrades(Assessment $assessment, array $entries, ?User $grader): Collection
    {
        return DB::transaction(function () use ($assessment, $entries, $grader) {
            $enrollments = Enrollment::query()->whereKey(array_keys($entries))->get()->keyBy('id');
            $grades = collect();

            foreach ($entries as $enrollmentId => $entry) {
                // A cleared score removes a previously recorded grade.
                if ($entry['score'] === null || $entry['score'] === '') {
                    $assessment->grades()->where('enrollment_id', $enrollmentId)->delete();

                    continue;
                }

                $enrollment = $enrollments->get($enrollmentId)
                    ?? throw new DomainRuleException('Unknown enrollment.', 'enrollment_id');

                $grades->push($this->recordGrade(
                    $assessment,
                    $enrollment,
                    (float) $entry['score'],
                    $entry['feedback'] ?? null,
                    $grader,
                ));
            }

            return $grades;
        });
    }

    /**
     * Current weighted score for an enrollment, based on graded assessments.
     */
    public function finalScore(Enrollment $enrollment): ?float
    {
        $assessments = Assessment::query()->where('section_id', $enrollment->section_id)->get();
        $grades = $enrollment->grades()->get()->keyBy('assessment_id');

        return $this->calculator->weightedPercentage($assessments->map(fn (Assessment $a) => [
            'score' => $grades->get($a->id)?->score,
            'max_score' => $a->max_score,
            'weight' => $a->weight,
        ]));
    }

    /**
     * Class gradebook summary: per-assessment averages and per-student scores.
     *
     * Keys: assessments (assessment, average, graded), students (enrollment,
     * score, letter, passing), class_average, distribution (letter => count)
     * and total_weight.
     *
     * @return array<string, mixed>
     */
    public function sectionSummary(Section $section): array
    {
        $assessments = $section->assessments()->orderBy('due_on')->orderBy('id')->get();
        $enrollments = $section->enrollments()
            ->whereIn('status', [EnrollmentStatus::Enrolled->value, EnrollmentStatus::Completed->value])
            ->with(['student', 'grades'])
            ->get()
            ->sortBy(fn (Enrollment $e) => $e->student->last_name.' '.$e->student->first_name)
            ->values();

        $assessmentSummaries = $assessments->map(function (Assessment $assessment) use ($enrollments) {
            $percentages = $enrollments
                ->map(fn (Enrollment $e) => $e->grades->firstWhere('assessment_id', $assessment->id)?->score)
                ->filter(fn ($score) => $score !== null)
                ->map(fn ($score) => (float) $score / max((float) $assessment->max_score, 0.01) * 100);

            return [
                'assessment' => $assessment,
                'average' => $this->calculator->average($percentages),
                'graded' => $percentages->count(),
            ];
        });

        $students = $enrollments->map(function (Enrollment $enrollment) use ($assessments) {
            $grades = $enrollment->grades->keyBy('assessment_id');
            $score = $enrollment->status === EnrollmentStatus::Completed
                ? ($enrollment->final_score !== null ? (float) $enrollment->final_score : null)
                : $this->calculator->weightedPercentage($assessments->map(fn (Assessment $a) => [
                    'score' => $grades->get($a->id)?->score,
                    'max_score' => $a->max_score,
                    'weight' => $a->weight,
                ]));

            return [
                'enrollment' => $enrollment,
                'score' => $score,
                'letter' => $this->calculator->letterFor($score),
                'passing' => $this->calculator->passes($score),
            ];
        });

        $distribution = $students->pluck('letter')->filter()->countBy()->all();

        return [
            'assessments' => $assessmentSummaries,
            'students' => $students,
            'class_average' => $this->calculator->average($students->pluck('score')),
            'distribution' => $distribution,
            'total_weight' => (float) $assessments->sum('weight'),
        ];
    }
}
