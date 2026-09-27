<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CourseRequest;
use App\Http\Resources\V1\CourseResource;
use App\Models\Course;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Courses
 */
class CourseController extends ApiController
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * List courses.
     *
     * Filters: `filter[search]` (code or title), `filter[department]`,
     * `filter[is_active]`. Sorts: `code`, `title`, `credits`, `department`.
     * Include: `prerequisites`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Course::class);

        $courses = QueryBuilder::for(Course::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('department'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('code', 'title', 'credits', 'department')
            ->allowedIncludes('prerequisites')
            ->defaultSort('code')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CourseResource::collection($courses);
    }

    /**
     * Create a course.
     */
    public function store(CourseRequest $request): JsonResponse
    {
        Gate::authorize('create', Course::class);

        $data = $request->validated();
        $course = $this->catalog->saveCourse(
            collect($data)->except('prerequisite_ids')->all(),
            $data['prerequisite_ids'] ?? [],
        );

        return (new CourseResource($course->load('prerequisites')))->response()->setStatusCode(201);
    }

    /**
     * Show a course.
     */
    public function show(Course $course): CourseResource
    {
        Gate::authorize('view', $course);

        return new CourseResource($course->load('prerequisites'));
    }

    /**
     * Update a course.
     *
     * Circular prerequisite chains are rejected.
     */
    public function update(CourseRequest $request, Course $course): CourseResource
    {
        Gate::authorize('update', $course);

        $data = $request->validated();
        $course = $this->catalog->saveCourse(
            collect($data)->except('prerequisite_ids')->all(),
            $data['prerequisite_ids'] ?? $course->prerequisites()->pluck('courses.id')->all(),
            $course,
        );

        return new CourseResource($course->load('prerequisites'));
    }

    /**
     * Delete a course.
     *
     * Courses that have classes cannot be deleted (mark them inactive instead).
     */
    public function destroy(Course $course): Response
    {
        Gate::authorize('delete', $course);

        $this->catalog->deleteCourse($course);

        return response()->noContent();
    }
}
