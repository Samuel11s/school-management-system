<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SectionRequest;
use App\Http\Resources\V1\SectionResource;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Services\AttendanceService;
use App\Services\CatalogService;
use App\Services\GradebookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Classes ("sections") are offerings of a course in an academic term.
 *
 * @tags Classes
 */
class SectionController extends ApiController
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * List classes.
     *
     * Admins see every class, teachers the classes they teach and students
     * the classes they are enrolled in. Filters: `filter[academic_term_id]`,
     * `filter[course_id]`, `filter[teacher_id]`, `filter[status]`.
     * Include: `course`, `term`, `teacher`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Section::class);

        $sections = QueryBuilder::for(Section::query()->visibleTo($request->user())->withEnrolledCount())
            ->allowedFilters(
                AllowedFilter::exact('academic_term_id'),
                AllowedFilter::exact('course_id'),
                AllowedFilter::exact('teacher_id'),
                AllowedFilter::exact('status'),
            )
            ->allowedIncludes('course', 'term', 'teacher')
            ->allowedSorts('code', 'capacity', 'created_at')
            ->defaultSort('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return SectionResource::collection($sections);
    }

    /**
     * Create a class.
     */
    public function store(SectionRequest $request): JsonResponse
    {
        Gate::authorize('create', Section::class);

        $section = $this->catalog->saveSection($request->validated());

        return (new SectionResource($section->load(['course', 'term', 'teacher'])))->response()->setStatusCode(201);
    }

    /**
     * Show a class.
     */
    public function show(Section $section): SectionResource
    {
        Gate::authorize('view', $section);

        return new SectionResource($section->load(['course', 'term', 'teacher']));
    }

    /**
     * Update a class.
     *
     * Capacity cannot be reduced below the current enrollment; cancelling a
     * class drops its active enrollments.
     */
    public function update(SectionRequest $request, Section $section): SectionResource
    {
        Gate::authorize('update', $section);

        return new SectionResource($this->catalog->saveSection($request->validated(), $section)->load(['course', 'term', 'teacher']));
    }

    /**
     * Delete a class.
     *
     * Classes with enrollment records cannot be deleted; cancel them instead.
     */
    public function destroy(Section $section): Response
    {
        Gate::authorize('delete', $section);

        $this->catalog->deleteSection($section);

        return response()->noContent();
    }

    /**
     * Class grade summary.
     *
     * Per-assessment averages, each student's weighted score and letter
     * grade, the class average and the grade distribution.
     */
    public function gradeSummary(Section $section, GradebookService $gradebook): JsonResponse
    {
        Gate::authorize('manageGrades', $section);

        $summary = $gradebook->sectionSummary($section);

        return response()->json(['data' => [
            'section_id' => $section->id,
            'class_average' => $summary['class_average'],
            'total_weight' => $summary['total_weight'],
            'distribution' => (object) $summary['distribution'],
            'assessments' => $summary['assessments']->map(fn ($row) => [
                'assessment_id' => $row['assessment']->id,
                'title' => $row['assessment']->title,
                'average_percentage' => $row['average'],
                'graded_count' => $row['graded'],
            ])->values(),
            'students' => $summary['students']->map(fn ($row) => [
                'enrollment_id' => $row['enrollment']->id,
                'student_id' => $row['enrollment']->student_id,
                'score' => $row['score'],
                'letter_grade' => $row['letter'],
                'passing' => $row['passing'],
            ])->values(),
        ]]);
    }

    /**
     * Class attendance summary.
     *
     * Per-student attendance counts and rates for the class. Optional
     * `from` and `to` (Y-m-d) limit the date range.
     */
    public function attendanceSummary(Request $request, Section $section, AttendanceService $attendance): JsonResponse
    {
        Gate::authorize('manageAttendance', $section);

        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = AttendanceRecord::query()
            ->where('section_id', $section->id)
            ->when($request->input('from'), fn ($q, $from) => $q->whereDate('attended_on', '>=', $from))
            ->when($request->input('to'), fn ($q, $to) => $q->whereDate('attended_on', '<=', $to));

        return response()->json(['data' => [
            'section_id' => $section->id,
            'warning_threshold' => (float) config('school.attendance.warning_threshold'),
            'students' => $attendance->summarize($query),
        ]]);
    }
}
