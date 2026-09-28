<div>
    <x-page-header :title="$section->name.' · '.$section->course->title"
                   :subtitle="$section->term->name.' · '.($section->teacher?->name ?? 'No teacher assigned')"
                   :back="route('sections.index')">
        <x-slot:actions>
            @can('manageGrades', $section)
                <a href="{{ route('sections.gradebook', $section) }}" class="btn btn-outline-primary"><i class="bi bi-journal-check me-1" aria-hidden="true"></i>Gradebook</a>
            @endcan
            @can('manageAttendance', $section)
                <a href="{{ route('sections.attendance', $section) }}" class="btn btn-outline-primary"><i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Attendance</a>
            @endcan
            @can('update', $section)
                <a href="{{ route('sections.edit', $section) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card label="Enrolled" :value="$section->seatsTaken().' / '.$section->capacity" icon="bi-people" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Seats available" :value="$section->seatsAvailable()" icon="bi-door-open" :variant="$section->isFull() ? 'danger' : 'success'" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Schedule" :value="$section->schedule ?? '—'" icon="bi-clock" variant="info" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Room" :value="$section->room ?? '—'" icon="bi-geo-alt" variant="secondary" /></div>
    </div>

    <p class="mb-4">
        <x-status-badge :status="$section->status" />
        <span class="small text-body-secondary ms-2">
            Enrollment window: {{ $section->term->enrollment_opens_on->toFormattedDateString() }} – {{ $section->term->enrollment_closes_on->toFormattedDateString() }}
            @if ($section->course->prerequisites->isNotEmpty())
                · Prerequisites: {{ $section->course->prerequisites->pluck('code')->join(', ') }}
            @endif
        </span>
    </p>

    @if ($canManage)
        <section class="card shadow-sm mb-4" aria-labelledby="enroll-heading">
            <div class="card-body">
                <h2 id="enroll-heading" class="h5">Enroll a student</h2>
                <p class="small text-body-secondary">Capacity, enrollment window, prerequisite and duplicate rules are checked automatically.</p>
                <x-search-input model="studentSearch" label="Search active students to enroll" placeholder="Type at least two characters of a name, email or number" />
                @error('enrollment')
                    <div class="alert alert-danger mt-3 mb-0" role="alert">{{ $message }}</div>
                @enderror
                <x-loading target="studentSearch,enroll" class="mt-2" />

                @if ($candidates->isNotEmpty())
                    <ul class="list-group mt-3">
                        @foreach ($candidates as $candidate)
                            <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="candidate-{{ $candidate->id }}">
                                <span>{{ $candidate->full_name }} <span class="small text-body-secondary font-monospace">{{ $candidate->student_number }}</span></span>
                                <button type="button" class="btn btn-sm btn-primary" wire:click="enroll({{ $candidate->id }})" wire:loading.attr="disabled">
                                    Enroll<span class="visually-hidden"> {{ $candidate->full_name }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @elseif (mb_strlen(trim($studentSearch)) >= 2)
                    <p class="small text-body-secondary mt-3 mb-0">No matching active students who are not already in this class.</p>
                @endif
            </div>
        </section>
    @endif

    @if ($canViewRoster)
        <section class="card shadow-sm mb-4" aria-labelledby="roster-heading">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 id="roster-heading" class="h5 mb-0">Class roster</h2>
                <div>
                    <label for="roster-filter" class="visually-hidden">Show enrollments</label>
                    <select id="roster-filter" class="form-select form-select-sm" wire:model.live="rosterFilter">
                        <option value="enrolled">Currently enrolled</option>
                        <option value="dropped">Dropped</option>
                        <option value="completed">Completed</option>
                        <option value="all">All</option>
                    </select>
                </div>
            </div>
            @if ($roster->isEmpty())
                <x-empty-state icon="bi-people" title="No students in this list" />
            @else
                <div class="table-responsive" wire:loading.class="table-loading">
                    <table class="table align-middle mb-0">
                        <caption class="visually-hidden">Class roster</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Status</th>
                                <th scope="col">Enrolled</th>
                                <th scope="col">Final</th>
                                @if ($canManage)<th scope="col" class="text-end">Actions</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roster as $enrollment)
                                <tr wire:key="roster-{{ $enrollment->id }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$enrollment->student->full_name" size="sm" />
                                            <div>
                                                <a href="{{ route('students.show', $enrollment->student) }}" class="fw-semibold text-decoration-none">{{ $enrollment->student->last_name }}, {{ $enrollment->student->first_name }}</a>
                                                <span class="d-block small text-body-secondary font-monospace">{{ $enrollment->student->student_number }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><x-status-badge :status="$enrollment->status" /></td>
                                    <td class="small">{{ $enrollment->enrolled_at->toFormattedDateString() }}</td>
                                    <td>{{ $enrollment->final_score !== null ? $enrollment->final_score.'% ('.$enrollment->letter_grade.')' : '—' }}</td>
                                    @if ($canManage)
                                        <td class="text-end text-nowrap">
                                            @if ($enrollment->isActive())
                                                <button type="button" class="btn btn-sm btn-outline-success" wire:click="complete({{ $enrollment->id }})"
                                                        wire:confirm="Mark {{ $enrollment->student->full_name }} as having completed this class? The current weighted score becomes the final grade.">
                                                    Complete<span class="visually-hidden"> {{ $enrollment->student->full_name }}</span>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="drop({{ $enrollment->id }})"
                                                        wire:confirm="Drop {{ $enrollment->student->full_name }} from {{ $section->name }}?">
                                                    Drop<span class="visually-hidden"> {{ $enrollment->student->full_name }}</span>
                                                </button>
                                            @elseif ($enrollment->status === App\Enums\EnrollmentStatus::Dropped)
                                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="enroll({{ $enrollment->student_id }})">
                                                    Re-enroll<span class="visually-hidden"> {{ $enrollment->student->full_name }}</span>
                                                </button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if ($mine)
        <div class="row g-4">
            <section class="col-lg-7" aria-labelledby="my-grades-heading">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between">
                        <h2 id="my-grades-heading" class="h5 mb-0">My grades</h2>
                        <span>
                            @if ($mine['score'] !== null)
                                <strong>{{ number_format($mine['score'], 1) }}%</strong> <span class="badge badge-soft badge-soft-secondary">{{ $mine['letter'] }}</span>
                            @endif
                        </span>
                    </div>
                    @if ($mine['assessments']->isEmpty())
                        <x-empty-state icon="bi-journal-check" title="No assessments yet" />
                    @else
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <caption class="visually-hidden">My assessment results</caption>
                                <thead class="table-light">
                                    <tr><th scope="col">Assessment</th><th scope="col">Weight</th><th scope="col" class="text-end">Score</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($mine['assessments'] as $assessment)
                                        @php($grade = $mine['grades']->get($assessment->id))
                                        <tr>
                                            <td>{{ $assessment->title }} <span class="d-block small text-body-secondary">{{ $assessment->type->label() }}@if ($assessment->due_on) · due {{ $assessment->due_on->toFormattedDateString() }}@endif</span></td>
                                            <td>{{ rtrim(rtrim($assessment->weight, '0'), '.') }}%</td>
                                            <td class="text-end">
                                                @if ($grade)
                                                    {{ rtrim(rtrim($grade->score, '0'), '.') }} / {{ rtrim(rtrim($assessment->max_score, '0'), '.') }}
                                                    @if ($grade->feedback)<span class="d-block small text-body-secondary">{{ $grade->feedback }}</span>@endif
                                                @else
                                                    <span class="text-body-secondary">Not graded</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
            <section class="col-lg-5" aria-labelledby="my-attendance-heading">
                <div class="card shadow-sm h-100">
                    <div class="card-header"><h2 id="my-attendance-heading" class="h5 mb-0">My attendance</h2></div>
                    @if ($mine['attendance']->isEmpty())
                        <x-empty-state icon="bi-clipboard" title="No attendance recorded yet" />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($mine['attendance'] as $record)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>{{ $record->attended_on->format('D, M j') }}</span>
                                    <x-status-badge :status="$record->status" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>
    @endif
</div>
