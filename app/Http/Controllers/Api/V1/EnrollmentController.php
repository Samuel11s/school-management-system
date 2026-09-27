<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\EnrollmentRequest;
use App\Http\Requests\Api\V1\EnrollmentStatusRequest;
use App\Http\Resources\V1\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Enrollments
 */
class EnrollmentController extends ApiController
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /**
     * List enrollments.
     *
     * Scoped to the caller: admins see all, teachers their classes, students
     * their own. Filters: `filter[student_id]`, `filter[section_id]`,
     * `filter[status]`. Include: `student`, `section`. Sorts: `enrolled_at`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Enrollment::class);

        $enrollments = QueryBuilder::for(Enrollment::query()->visibleTo($request->user()))
            ->allowedFilters(
                AllowedFilter::exact('student_id'),
                AllowedFilter::exact('section_id'),
                AllowedFilter::exact('status'),
            )
            ->allowedIncludes('student', 'section')
            ->allowedSorts('enrolled_at', 'id')
            ->defaultSort('-enrolled_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return EnrollmentResource::collection($enrollments);
    }

    /**
     * Enroll a student in a class.
     *
     * Enforces student status, class status, the term's enrollment window,
     * prerequisites, duplicate enrollments and class capacity. Rule
     * violations return 422 with the reason.
     */
    public function store(EnrollmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Enrollment::class);

        $student = Student::query()->findOrFail($request->integer('student_id'));
        $section = Section::query()->findOrFail($request->integer('section_id'));
        Gate::authorize('manageEnrollments', $section);

        $enrollment = $this->enrollments->enroll($student, $section, $request->input('reason'));

        return (new EnrollmentResource($enrollment->load(['student', 'section'])))->response()->setStatusCode(201);
    }

    /**
     * Show an enrollment with its status history.
     */
    public function show(Enrollment $enrollment): EnrollmentResource
    {
        Gate::authorize('view', $enrollment);

        return new EnrollmentResource($enrollment->load(['student', 'section', 'statusHistories']));
    }

    /**
     * Drop an enrollment.
     */
    public function drop(EnrollmentStatusRequest $request, Enrollment $enrollment): EnrollmentResource
    {
        Gate::authorize('update', $enrollment);

        return new EnrollmentResource($this->enrollments->drop($enrollment, $request->input('reason')));
    }

    /**
     * Complete an enrollment.
     *
     * Freezes the current weighted score as the final score and letter grade.
     */
    public function complete(EnrollmentStatusRequest $request, Enrollment $enrollment): EnrollmentResource
    {
        Gate::authorize('update', $enrollment);

        return new EnrollmentResource($this->enrollments->complete($enrollment, $request->input('reason')));
    }
}
