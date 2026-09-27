<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AssessmentRequest;
use App\Http\Resources\V1\AssessmentResource;
use App\Models\Assessment;
use App\Models\Section;
use App\Services\GradebookService;
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
class AssessmentController extends ApiController
{
    public function __construct(private readonly GradebookService $gradebook) {}

    /**
     * List assessments.
     *
     * Assessments of the classes visible to the caller. Filters:
     * `filter[section_id]`, `filter[type]`. Sorts: `due_on`, `title`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $assessments = QueryBuilder::for(
            Assessment::query()->whereHas('section', fn ($q) => $q->visibleTo($request->user()))
        )
            ->allowedFilters(AllowedFilter::exact('section_id'), AllowedFilter::exact('type'))
            ->allowedSorts('due_on', 'title', 'id')
            ->defaultSort('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return AssessmentResource::collection($assessments);
    }

    /**
     * Create an assessment.
     *
     * The total weight of a class's assessments cannot exceed 100.
     */
    public function store(AssessmentRequest $request): JsonResponse
    {
        $section = Section::query()->findOrFail($request->integer('section_id'));
        Gate::authorize('manageGrades', $section);

        $assessment = $this->gradebook->saveAssessment($section, collect($request->validated())->except('section_id')->all());

        return (new AssessmentResource($assessment))->response()->setStatusCode(201);
    }

    /**
     * Show an assessment.
     */
    public function show(Assessment $assessment): AssessmentResource
    {
        Gate::authorize('view', $assessment);

        return new AssessmentResource($assessment);
    }

    /**
     * Update an assessment.
     */
    public function update(AssessmentRequest $request, Assessment $assessment): AssessmentResource
    {
        Gate::authorize('update', $assessment);

        $data = [...$assessment->only(['title', 'max_score', 'weight']), 'type' => $assessment->type, 'due_on' => $assessment->due_on?->toDateString(), ...$request->validated()];

        return new AssessmentResource($this->gradebook->saveAssessment($assessment->section, $data, $assessment));
    }

    /**
     * Delete an assessment and its grades.
     */
    public function destroy(Assessment $assessment): Response
    {
        Gate::authorize('delete', $assessment);

        $assessment->delete();

        return response()->noContent();
    }
}
