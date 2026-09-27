<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Http\Requests\Api\V1\AttendanceRegisterRequest;
use App\Http\Requests\Api\V1\AttendanceRequest;
use App\Http\Resources\V1\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Attendance
 */
class AttendanceController extends ApiController
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * List attendance records.
     *
     * Scoped to the caller. Filters: `filter[section_id]`,
     * `filter[student_id]`, `filter[status]`, `filter[from]` and
     * `filter[to]` (Y-m-d). Sorts: `date`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $records = QueryBuilder::for(AttendanceRecord::query()->visibleTo($request->user()))
            ->allowedFilters(
                AllowedFilter::exact('section_id'),
                AllowedFilter::exact('student_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('from', fn (Builder $q, $value) => $q->whereDate('attended_on', '>=', Carbon::parse($value)->toDateString())),
                AllowedFilter::callback('to', fn (Builder $q, $value) => $q->whereDate('attended_on', '<=', Carbon::parse($value)->toDateString())),
            )
            ->allowedSorts($dateSort = AllowedSort::field('date', 'attended_on'), 'id')
            // Pass the AllowedSort itself so the "date" alias maps to attended_on.
            ->defaultSort((clone $dateSort)->defaultDirection(SortDirection::Descending))
            ->paginate($this->perPage($request))
            ->withQueryString();

        return AttendanceRecordResource::collection($records);
    }

    /**
     * Record attendance for one student.
     *
     * Duplicate records for the same student, class and date are rejected
     * with 422; use the class register endpoint to update a whole session.
     */
    public function store(AttendanceRequest $request): JsonResponse
    {
        $section = Section::query()->findOrFail($request->integer('section_id'));
        Gate::authorize('manageAttendance', $section);

        $record = $this->attendance->record(
            $section,
            Student::query()->findOrFail($request->integer('student_id')),
            Carbon::parse($request->string('date')),
            AttendanceStatus::from($request->string('status')),
            $request->input('remarks'),
            $request->user(),
        );

        return (new AttendanceRecordResource($record))->response()->setStatusCode(201);
    }

    /**
     * Save a class register.
     *
     * Records (or updates) attendance for several students of the class on
     * one date in a single transaction. Every student must be actively
     * enrolled in the class.
     */
    public function register(AttendanceRegisterRequest $request, Section $section): AnonymousResourceCollection
    {
        Gate::authorize('manageAttendance', $section);

        /** @var list<array{student_id: int, status: string, remarks?: string|null}> $validatedEntries */
        $validatedEntries = $request->validated('entries');

        $entries = collect($validatedEntries)
            ->mapWithKeys(fn (array $entry) => [$entry['student_id'] => [
                'status' => $entry['status'],
                'remarks' => $entry['remarks'] ?? null,
            ]])
            ->all();

        $records = $this->attendance->recordRegister($section, Carbon::parse($request->string('date')), $entries, $request->user());

        return AttendanceRecordResource::collection($records);
    }

    /**
     * Show an attendance record.
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        Gate::authorize('view', $attendanceRecord);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * Update an attendance record's status or remarks.
     */
    public function update(AttendanceRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        Gate::authorize('update', $attendanceRecord);

        $attendanceRecord->fill([...$request->validated(), 'recorded_by' => $request->user()->id])->save();

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * Delete an attendance record.
     */
    public function destroy(AttendanceRecord $attendanceRecord): Response
    {
        Gate::authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
