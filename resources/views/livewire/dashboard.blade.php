<div>
    <x-page-header :title="'Welcome, '.$user->name"
                   :subtitle="$term ? 'Current term: '.$term->name.' ('.$term->starts_on->toFormattedDateString().' – '.$term->ends_on->toFormattedDateString().')' : 'No current academic term is set.'" />

    @if ($admin)
        <section aria-labelledby="overview-heading" class="mb-4">
            <h2 id="overview-heading" class="visually-hidden">School overview</h2>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <x-stat-card label="Active students" :value="$admin['activeStudents']" icon="bi-people"
                                 :href="Route::has('students.index') ? route('students.index') : null" />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <x-stat-card label="Active courses" :value="$admin['courses']" icon="bi-journal-bookmark" variant="success"
                                 :href="Route::has('courses.index') ? route('courses.index') : null" />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <x-stat-card label="Classes this term" :value="$admin['openSections']" icon="bi-easel" variant="info"
                                 :href="Route::has('sections.index') ? route('sections.index') : null" />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <x-stat-card label="Active enrollments" :value="$admin['activeEnrollments']" icon="bi-person-check" variant="warning" />
                </div>
            </div>
        </section>

        <div class="row g-4">
            <section class="col-lg-6" aria-labelledby="capacity-heading">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-body"><h2 id="capacity-heading" class="h6 mb-0">Classes nearly full</h2></div>
                    @if ($admin['nearlyFull']->isEmpty())
                        <x-empty-state icon="bi-check2-circle" title="No classes above 80% capacity" />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($admin['nearlyFull'] as $section)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span>
                                            @if (Route::has('sections.show'))
                                                <a href="{{ route('sections.show', $section) }}">{{ $section->name }}</a>
                                            @else
                                                {{ $section->name }}
                                            @endif
                                            <span class="text-body-secondary small">{{ $section->course->title }}</span>
                                        </span>
                                        <span class="small">{{ $section->seatsTaken() }} / {{ $section->capacity }}</span>
                                    </div>
                                    @php($pct = (int) round($section->seatsTaken() / $section->capacity * 100))
                                    <div class="progress mt-1" role="progressbar" aria-label="{{ $section->name }} capacity used"
                                         aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" style="height: .5rem">
                                        <div @class(['progress-bar', 'bg-danger' => $pct >= 100, 'bg-warning' => $pct < 100]) style="width: {{ $pct }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <section class="col-lg-6" aria-labelledby="activity-heading">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-body"><h2 id="activity-heading" class="h6 mb-0">Recent enrollment activity</h2></div>
                    @if ($admin['recentActivity']->isEmpty())
                        <x-empty-state icon="bi-clock-history" title="No recent activity" />
                    @else
                        <ul class="list-group list-group-flush small">
                            @foreach ($admin['recentActivity'] as $entry)
                                @php($enrollment = $entry->subject)
                                <li class="list-group-item">
                                    <strong>{{ $enrollment?->student?->full_name ?? 'Removed student' }}</strong>
                                    {{ $entry->to_status === 'enrolled' ? 'enrolled in' : $entry->to_status.' from' }}
                                    {{ $enrollment?->section?->name }}
                                    <span class="text-body-secondary d-block">
                                        {{ $entry->created_at->diffForHumans() }}@if ($entry->changedBy) by {{ $entry->changedBy->name }}@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>
    @endif

    @if ($user->isTeacher())
        <section aria-labelledby="teaching-heading" class="mb-4">
            <h2 id="teaching-heading" class="h5">My classes this term</h2>
            @if ($teaching->isEmpty())
                <div class="card shadow-sm"><x-empty-state icon="bi-easel" title="You are not assigned to any classes this term" /></div>
            @else
                <div class="row g-3">
                    @foreach ($teaching as $section)
                        <div class="col-md-6 col-xl-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <h3 class="h6 mb-1">{{ $section->name }} · {{ $section->course->title }}</h3>
                                    <p class="small text-body-secondary mb-2">{{ $section->schedule }} · {{ $section->room }}</p>
                                    <p class="small mb-2">{{ $section->seatsTaken() }} of {{ $section->capacity }} students enrolled</p>
                                    @if ($section->attendance_taken_today)
                                        <span class="badge text-bg-success">Attendance taken today</span>
                                    @else
                                        <span class="badge text-bg-warning">Attendance not yet taken today</span>
                                    @endif
                                </div>
                                @if (Route::has('sections.gradebook'))
                                    <div class="card-footer bg-body d-flex gap-2">
                                        <a href="{{ route('sections.show', $section) }}" class="btn btn-sm btn-outline-secondary">Roster</a>
                                        <a href="{{ route('sections.gradebook', $section) }}" class="btn btn-sm btn-outline-primary">Gradebook</a>
                                        <a href="{{ route('sections.attendance', $section) }}" class="btn btn-sm btn-primary">Take attendance</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if ($user->student)
        <section aria-labelledby="my-classes-heading">
            <h2 id="my-classes-heading" class="h5">My classes</h2>
            <div class="card shadow-sm">
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
                                    <th scope="col" class="text-end">Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($studentClasses as $row)
                                    @php($section = $row['enrollment']->section)
                                    <tr>
                                        <td>
                                            <strong>{{ $section->name }}</strong>
                                            <span class="d-block small text-body-secondary">{{ $section->course->title }}</span>
                                        </td>
                                        <td>{{ $section->teacher?->name ?? 'TBA' }}</td>
                                        <td class="small">{{ $section->schedule }}</td>
                                        <td class="text-end">
                                            @if ($row['score'] !== null)
                                                {{ number_format($row['score'], 1) }}% <span class="badge text-bg-secondary">{{ $row['letter'] }}</span>
                                            @else
                                                <span class="text-body-secondary">No grades yet</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $row['attendance'] !== null ? $row['attendance'].'%' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>
