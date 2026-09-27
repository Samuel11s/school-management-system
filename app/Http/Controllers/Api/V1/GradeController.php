<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\GradeRequest;
use App\Http\Resources\V1\GradeResource;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\GradebookService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Grades
 */
class GradeController extends ApiController
{
    public function __construct(private readonly GradebookService $gradebook) {}

    /**
     * List grades.
     *
     * Students only ever receive their own grades; teachers the grades of
     * their classes. Filters: `filter[section_id]`, `filter[student_id]`,
     * `filter[assessment_id]`, `filter[enrollment_id]`. Include: `assessment`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Grade::class);

        $grades = QueryBuilder::for(Grade::query()->visibleTo($request->user())->with('enrollment'))
            ->allowedFilters(
                AllowedFilter::exact('assessment_id'),
                AllowedFilter::exact('enrollment_id'),
                AllowedFilter::callback('section_id', fn (Builder $q, $value) => $q->whereHas('assessment', fn ($a) => $a->where('section_id', $value))),
                AllowedFilter::callback('student_id', fn (Builder $q, $value) => $q->whereHas('enrollment', fn ($e) => $e->where('student_id', $value))),
            )
            ->allowedIncludes('assessment')
            ->allowedSorts('graded_at', 'score', 'id')
            ->defaultSort('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return GradeResource::collection($grades);
    }

    /**
     * Record a grade.
     *
     * Creates the grade, or updates the existing one for the same assessment
     * and enrollment (201 when created, 200 when updated). The score must be
     * between 0 and the assessment's maximum and the enrollment must be
     * active in the assessment's class.
     */
    public function store(GradeRequest $request): JsonResponse
    {
        $assessment = Assessment::query()->findOrFail($request->integer('assessment_id'));
        $enrollment = Enrollment::query()->findOrFail($request->integer('enrollment_id'));
        Gate::authorize('manageGrades', $assessment->section);

        $grade = $this->gradebook->recordGrade(
            $assessment,
            $enrollment,
            (float) $request->input('score'),
            $request->input('feedback'),
            $request->user(),
        );

        return (new GradeResource($grade->load(['assessment', 'enrollment'])))
            ->response()
            ->setStatusCode($grade->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Show a grade.
     */
    public function show(Grade $grade): GradeResource
    {
        Gate::authorize('view', $grade);

        return new GradeResource($grade->load(['assessment', 'enrollment']));
    }

    /**
     * Update a grade.
     */
    public function update(GradeRequest $request, Grade $grade): GradeResource
    {
        Gate::authorize('update', $grade);

        $grade = $this->gradebook->recordGrade(
            $grade->assessment,
            $grade->enrollment,
            (float) $request->input('score', $grade->score),
            $request->has('feedback') ? $request->input('feedback') : $grade->feedback,
            $request->user(),
        );

        return new GradeResource($grade->load(['assessment', 'enrollment']));
    }

    /**
     * Delete a grade.
     */
    public function destroy(Grade $grade): Response
    {
        Gate::authorize('delete', $grade);

        $grade->delete();

        return response()->noContent();
    }
}
