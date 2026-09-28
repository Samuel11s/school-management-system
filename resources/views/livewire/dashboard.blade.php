@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', trim($user->name))[0];
    $attended = $attendance['counts']['present'] + $attendance['counts']['late'];
    $countable = $attendance['total'] - $attendance['counts']['excused'];
    $attendanceRate = $countable > 0 ? round($attended / $countable * 100, 1) : null;
@endphp

<div>
    <x-page-header :title="$greeting.', '.$firstName"
                   :subtitle="$term
                        ? now()->format('l, F j').' · '.$term->name.' runs '.$term->starts_on->toFormattedDateString().' – '.$term->ends_on->toFormattedDateString()
                        : now()->format('l, F j').' · No current academic term is set.'">
        @if ($admin)
            <x-slot:actions>
                @if (Route::has('students.create'))
                    <a href="{{ route('students.create') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus" aria-hidden="true"></i>Add student</a>
                @endif
                @if (Route::has('sections.create'))
                    <a href="{{ route('sections.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i>New class</a>
                @endif
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($admin)
        <section aria-labelledby="overview-heading" class="mb-4">
            <h2 id="overview-heading" class="visually-hidden">School overview</h2>
            <div class="row g-3">
                <div class="col-6 col-xl-3">
                    <x-stat-card label="Active students" :value="$admin['activeStudents']" icon="bi-people-fill" variant="primary"
                                 hint="Currently enrolled at the school"
                                 :href="Route::has('students.index') ? route('students.index') : null" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-stat-card label="Active courses" :value="$admin['courses']" icon="bi-journal-bookmark-fill" variant="success"
                                 hint="Available in the catalog"
                                 :href="Route::has('courses.index') ? route('courses.index') : null" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-stat-card label="Classes this term" :value="$admin['openSections']" icon="bi-easel2-fill" variant="violet"
                                 :hint="$term?->name"
                                 :href="Route::has('sections.index') ? route('sections.index') : null" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-stat-card label="Active enrollments" :value="$admin['activeEnrollments']" icon="bi-person-check-fill" variant="warning"
                                 :hint="$attendanceRate !== null ? 'Attendance rate '.$attendanceRate.'%' : null" />
                </div>
            </div>
        </section>

        <div class="row g-4 mb-4">
            <section class="col-xl-8" aria-labelledby="capacity-heading">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 id="capacity-heading" class="h6 mb-0 d-flex align-items-center">
                            <span class="card-title-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>Class capacity
                        </h2>
                        <span class="small text-body-secondary">Fullest classes this term</span>
                    </div>
                    <div class="card-body">
                        @if ($admin['classFill']->isEmpty())
                            <x-empty-state icon="bi-easel2" title="No classes this term" class="py-4" />
                        @else
                            <ul class="bar-chart">
                                @foreach ($admin['classFill'] as $section)
                                    @php($pct = (int) round($section->seatsTaken() / $section->capacity * 100))
                                    <li>
                                        <div class="d-flex justify-content-between align-items-baseline mb-1 gap-2">
                                            <span class="min-w-0 text-truncate">
                                                @if (Route::has('sections.show'))
                                                    <a href="{{ route('sections.show', $section) }}" class="fw-semibold text-decoration-none">{{ $section->name }}</a>
                                                @else
                                                    <span class="fw-semibold">{{ $section->name }}</span>
                                                @endif
                                                <span class="small text-body-secondary ms-1">{{ $section->course->title }}</span>
                                            </span>
                                            <span class="small text-nowrap"><strong>{{ $section->seatsTaken() }}</strong> / {{ $section->capacity }} seats</span>
                                        </div>
                                        <div class="bar-track" role="progressbar" aria-label="{{ $section->name }} capacity used"
                                             aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                            <div @class(['bar-fill', 'is-full' => $pct >= 100, 'is-high' => $pct >= 80 && $pct < 100]) style="width: {{ min($pct, 100) }}%"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>

            <div class="col-xl-4">
                <x-attendance-breakdown :data="$attendance" :href="Route::has('attendance.index') ? route('attendance.index') : null" />
            </div>
        </div>

        <section class="card shadow-sm" aria-labelledby="activity-heading">
            <div class="card-header d-flex align-items-center">
                <h2 id="activity-heading" class="h6 mb-0 d-flex align-items-center">
                    <span class="card-title-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>Recent enrollment activity
                </h2>
            </div>
            @if ($admin['recentActivity']->isEmpty())
                <x-empty-state icon="bi-clock-history" title="No recent activity" />
            @else
                <ul class="list-unstyled mb-0">
                    @foreach ($admin['recentActivity'] as $entry)
                        @php($enrollment = $entry->subject)
                        @php($studentName = $enrollment?->student?->full_name ?? 'Removed student')
                        <li class="activity-item align-items-center">
                            <x-avatar :name="$studentName" />
                            <div class="min-w-0 flex-grow-1">
                                <p class="mb-0 text-truncate">
                                    <strong>{{ $studentName }}</strong>
                                    {{ $entry->to_status === 'enrolled' ? 'enrolled in' : $entry->to_status.' from' }}
                                    <strong>{{ $enrollment?->section?->name }}</strong>
                                </p>
                                <p class="small text-body-secondary mb-0">
                                    {{ $entry->created_at->diffForHumans() }}@if ($entry->changedBy) · by {{ $entry->changedBy->name }}@endif
                                </p>
                            </div>
                            <span @class(['badge badge-soft', 'badge-soft-success' => $entry->to_status === 'enrolled', 'badge-soft-primary' => $entry->to_status === 'completed', 'badge-soft-secondary' => ! in_array($entry->to_status, ['enrolled', 'completed'], true)])>
                                {{ ucfirst($entry->to_status) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if ($user->isTeacher())
        @php($studentsTaught = $teaching->sum(fn ($s) => $s->seatsTaken()))
        @php($takenToday = $teaching->where('attendance_taken_today', true)->count())

        <section aria-labelledby="teacher-overview-heading" class="mb-4">
            <h2 id="teacher-overview-heading" class="visually-hidden">Teaching overview</h2>
            <div class="row g-3">
                <div class="col-6 col-xl-3">
                    <x-stat-card label="My classes" :value="$teaching->count()" icon="bi-easel2-fill" variant="primary" :hint="$term?->name" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-stat-card label="Students taught" :value="$studentsTaught" icon="bi-people-fill" variant="success" hint="Active enrollments" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-stat-card label="Registers taken today" :value="$takenToday.' / '.$teaching->count()" icon="bi-clipboard2-check-fill"
                                 :variant="$teaching->isNotEmpty() && $takenToday === $teaching->count() ? 'success' : 'warning'" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-stat-card label="Attendance rate" :value="$attendanceRate !== null ? $attendanceRate.'%' : '—'" icon="bi-graph-up-arrow" variant="info" hint="Across my classes this term" />
                </div>
            </div>
        </section>

        <div class="row g-4 mb-4">
            <section class="col-xl-8" aria-labelledby="teaching-heading">
                <h2 id="teaching-heading" class="h5 mb-3">My classes this term</h2>
                @if ($teaching->isEmpty())
                    <div class="card shadow-sm"><x-empty-state icon="bi-easel2" title="You are not assigned to any classes this term" /></div>
                @else
                    <div class="row g-3">
                        @foreach ($teaching as $section)
                            @php($pct = $section->capacity > 0 ? (int) round($section->seatsTaken() / $section->capacity * 100) : 0)
                            <div class="col-md-6">
                                <div class="card shadow-sm h-100 card-interactive">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div class="min-w-0">
                                                <h3 class="h6 mb-0">{{ $section->name }}</h3>
                                                <p class="small text-body-secondary mb-0 text-truncate">{{ $section->course->title }}</p>
                                            </div>
                                            @if ($section->attendance_taken_today)
                                                <span class="badge badge-soft badge-soft-success">Register taken</span>
                                            @else
                                                <span class="badge badge-soft badge-soft-warning">Register due</span>
                                            @endif
                                        </div>
                                        <p class="small text-body-secondary mb-3">
                                            <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ $section->schedule ?? 'No schedule' }}
                                            <span class="mx-1">·</span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $section->room ?? 'No room' }}
                                        </p>
                                        <div class="meter small">
                                            <span class="text-nowrap">{{ $section->seatsTaken() }} / {{ $section->capacity }} students</span>
                                            <div class="progress" role="progressbar" aria-label="{{ $section->name }} seats filled" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar" style="width: {{ min($pct, 100) }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    @if (Route::has('sections.gradebook'))
                                        <div class="card-footer d-flex flex-wrap gap-2">
                                            <a href="{{ route('sections.show', $section) }}" class="btn btn-sm btn-outline-secondary">Roster<span class="visually-hidden"> for {{ $section->name }}</span></a>
                                            <a href="{{ route('sections.gradebook', $section) }}" class="btn btn-sm btn-outline-primary">Gradebook<span class="visually-hidden"> for {{ $section->name }}</span></a>
                                            <a href="{{ route('sections.attendance', $section) }}" class="btn btn-sm btn-primary ms-auto">Take attendance<span class="visually-hidden"> for {{ $section->name }}</span></a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="col-xl-4">
                <x-attendance-breakdown :data="$attendance" title="Attendance in my classes"
                                        :href="Route::has('attendance.index') ? route('attendance.index') : null" />
            </div>
        </div>
    @endif

    @if ($user->student)
        @php($scores = $studentClasses->pluck('score')->filter(fn ($s) => $s !== null))

        <section aria-labelledby="student-overview-heading" class="mb-4">
            <h2 id="student-overview-heading" class="visually-hidden">My overview</h2>
            <div class="row g-3">
                <div class="col-6 col-xl-4">
                    <x-stat-card label="My classes" :value="$studentClasses->count()" icon="bi-journal-bookmark-fill" variant="primary" :hint="$term?->name" />
                </div>
                <div class="col-6 col-xl-4">
                    <x-stat-card label="Average grade" :value="$scores->isNotEmpty() ? number_format($scores->avg(), 1).'%' : '—'" icon="bi-award-fill" variant="violet" hint="Across graded classes" />
                </div>
                <div class="col-6 col-xl-4">
                    <x-stat-card label="Attendance rate" :value="$attendanceRate !== null ? $attendanceRate.'%' : '—'" icon="bi-calendar2-check-fill" variant="success" hint="This term" />
                </div>
            </div>
        </section>

        <div class="row g-4">
            <section class="col-xl-8" aria-labelledby="my-classes-heading">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex align-items-center">
                        <h2 id="my-classes-heading" class="h6 mb-0 d-flex align-items-center">
                            <span class="card-title-icon"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></span>My classes
                        </h2>
                    </div>
                    @if ($studentClasses->isEmpty())
                        <x-empty-state icon="bi-journal" title="You are not enrolled in any classes this term" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <caption class="visually-hidden">My classes with current grade and attendance</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Class</th>
                                        <th scope="col">Teacher</th>
                                        <th scope="col">Schedule</th>
                                        <th scope="col" class="text-end">Current grade</th>
                                        <th scope="col" style="min-width: 9rem">Attendance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($studentClasses as $row)
                                        @php($section = $row['enrollment']->section)
                                        <tr>
                                            <td>
                                                @if (Route::has('sections.show'))
                                                    <a href="{{ route('sections.show', $section) }}" class="fw-semibold text-decoration-none">{{ $section->name }}</a>
                                                @else
                                                    <strong>{{ $section->name }}</strong>
                                                @endif
                                                <span class="d-block small text-body-secondary">{{ $section->course->title }}</span>
                                            </td>
                                            <td>{{ $section->teacher?->name ?? 'TBA' }}</td>
                                            <td class="small">{{ $section->schedule }}</td>
                                            <td class="text-end text-nowrap">
                                                @if ($row['score'] !== null)
                                                    {{ number_format($row['score'], 1) }}% <span class="badge badge-soft badge-soft-primary">{{ $row['letter'] }}</span>
                                                @else
                                                    <span class="text-body-secondary">No grades yet</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($row['attendance'] !== null)
                                                    <div class="meter small">
                                                        <span class="text-nowrap">{{ $row['attendance'] }}%</span>
                                                        <div class="progress" role="progressbar" aria-label="Attendance in {{ $section->name }}" aria-valuenow="{{ $row['attendance'] }}" aria-valuemin="0" aria-valuemax="100">
                                                            <div @class(['progress-bar', 'bg-chart-absent' => $row['attendance'] < config('school.attendance.warning_threshold'), 'bg-chart-present' => $row['attendance'] >= config('school.attendance.warning_threshold')]) style="width: {{ $row['attendance'] }}%"></div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-body-secondary">—</span>
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

            <div class="col-xl-4">
                <x-attendance-breakdown :data="$attendance" title="My attendance" />
            </div>
        </div>
    @endif
</div>
