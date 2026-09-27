<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StudentRequest;
use App\Http\Resources\V1\StudentResource;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @tags Students
 */
class StudentController extends ApiController
{
    public function __construct(private readonly StudentService $students) {}

    /**
     * List students.
     *
     * Admins see all students, teachers see students enrolled in their classes.
     * Filters: `filter[search]` (name, email or number), `filter[status]`,
     * `filter[grade_level]`. Sorts: `last_name`, `student_number`,
     * `admission_date`, `grade_level`, `created_at`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Student::class);

        $students = QueryBuilder::for(Student::query()->visibleTo($request->user()))
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('grade_level'),
            )
            ->allowedSorts('last_name', 'student_number', 'admission_date', 'grade_level', 'created_at')
            ->defaultSort('last_name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return StudentResource::collection($students);
    }

    /**
     * Create a student.
     */
    public function store(StudentRequest $request): JsonResponse
    {
        Gate::authorize('create', Student::class);

        $student = $this->students->create(
            collect($request->validated())->except('create_account')->all(),
            $request->boolean('create_account'),
        );

        return (new StudentResource($student->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Show a student.
     */
    public function show(Student $student): StudentResource
    {
        Gate::authorize('view', $student);

        return new StudentResource($student);
    }

    /**
     * Update a student.
     *
     * Partial updates are supported. Status changes are recorded in the
     * student's history; withdrawing a student drops their active enrollments.
     */
    public function update(StudentRequest $request, Student $student): StudentResource
    {
        Gate::authorize('update', $student);

        $data = collect($request->validated())->except('status_reason')->all();

        return new StudentResource($this->students->update($student, $data, $request->input('status_reason')));
    }

    /**
     * Delete a student.
     *
     * Soft delete: active enrollments are dropped and the login is disabled.
     * The record is purged after the configured retention period.
     */
    public function destroy(Student $student): Response
    {
        Gate::authorize('delete', $student);

        $this->students->delete($student);

        return response()->noContent();
    }

    /**
     * Download a student's photo.
     */
    public function photo(Student $student): StreamedResponse
    {
        Gate::authorize('view', $student);

        $disk = Storage::disk(config('school.uploads.disk'));
        abort_unless($student->photo_path && $disk->exists($student->photo_path), 404);

        return $disk->response($student->photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
