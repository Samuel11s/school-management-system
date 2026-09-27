<div>
    <x-page-header :title="$student->full_name" :subtitle="'Student number '.$student->student_number"
                   :back="auth()->user()->can('viewAny', App\Models\Student::class) ? route('students.index') : null">
        <x-slot:actions>
            @can('update', $student)
                <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit
                </a>
            @endcan
            @can('delete', $student)
                <button type="button" class="btn btn-outline-danger" wire:click="delete"
                        wire:confirm="Delete {{ $student->full_name }}? Active enrollments will be dropped and their login disabled.">
                    <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-4">
            <section class="card shadow-sm mb-4" aria-labelledby="profile-heading">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        @if ($student->photo_path)
                            <img src="{{ route('students.photo', $student) }}" alt="Photo of {{ $student->full_name }}" class="rounded-circle avatar-lg">
                        @else
                            <span class="rounded-circle avatar-lg bg-secondary-subtle d-inline-flex align-items-center justify-content-center fs-2" aria-hidden="true">
                                {{ mb_substr($student->first_name, 0, 1) }}{{ mb_substr($student->last_name, 0, 1) }}
                            </span>
                        @endif
                        <div>
                            <h2 id="profile-heading" class="h5 mb-1">Profile</h2>
                            <x-status-badge :status="$student->status" />
                        </div>
                    </div>

                    <dl class="row small mb-0">
                        <dt class="col-5">Email</dt><dd class="col-7 text-break">{{ $student->email }}</dd>
                        <dt class="col-5">Grade level</dt><dd class="col-7">{{ $student->grade_level ?? '—' }}</dd>
                        <dt class="col-5">Admitted</dt><dd class="col-7">{{ $student->admission_date->toFormattedDateString() }}</dd>
                        @if ($canSeeSensitive)
                            <dt class="col-5">Phone</dt><dd class="col-7">{{ $student->phone ?? '—' }}</dd>
                            <dt class="col-5">Date of birth</dt><dd class="col-7">{{ $student->date_of_birth?->toFormattedDateString() ?? '—' }}</dd>
                            <dt class="col-5">Address</dt><dd class="col-7">{{ $student->address ?? '—' }}</dd>
                            <dt class="col-5">Guardian</dt><dd class="col-7">{{ $student->guardian_name ?? '—' }}</dd>
                            <dt class="col-5">Guardian phone</dt><dd class="col-7">{{ $student->guardian_phone ?? '—' }}</dd>
                            <dt class="col-5">Login account</dt>
                            <dd class="col-7">{{ $student->user ? ($student->user->is_active ? 'Active' : 'Disabled') : 'None' }}</dd>
                        @endif
                    </dl>

                    @if ($canSeeSensitive && $student->notes && auth()->user()->can('update', $student))
                        <hr>
                        <h3 class="h6">Notes</h3>
                        <p class="small mb-0" style="white-space: pre-line">{{ $student->notes }}</p>
                    @endif
                </div>
            </section>

            <section class="card shadow-sm mb-4" aria-labelledby="summary-heading">
                <div class="card-body">
                    <h2 id="summary-heading" class="h5 mb-3">Academic summary</h2>
                    <dl class="row small mb-0">
                        <dt class="col-7">Cumulative average</dt>
                        <dd class="col-5">{{ $cumulativeAverage !== null ? number_format($cumulativeAverage, 1).'%' : '—' }}</dd>
                        <dt class="col-7">Credits earned</dt><dd class="col-5">{{ $creditsEarned }}</dd>
                        <dt class="col-7">Attendance rate</dt>
                        <dd class="col-5">
                            @if ($attendanceSummary && $attendanceSummary['rate'] !== null)
                                <span @class(['text-danger fw-semibold' => $attendanceSummary['below_threshold']])>{{ $attendanceSummary['rate'] }}%</span>
                            @else
                                —
                            @endif
                        </dd>
                        @if ($attendanceSummary)
                            <dt class="col-7">Absences / late</dt>
                            <dd class="col-5">{{ $attendanceSummary['absent'] }} / {{ $attendanceSummary['late'] }}</dd>
                        @endif
                    </dl>
                </div>
            </section>

            @if ($canSeeSensitive)
                <section class="card shadow-sm" aria-labelledby="history-heading">
                    <div class="card-body">
                        <h2 id="history-heading" class="h5 mb-3">Status history</h2>
                        @forelse ($student->statusHistories as $entry)
                            <div class="small border-start border-3 ps-2 mb-2">
                                <strong>{{ ucfirst($entry->to_status) }}</strong>
                                @if ($entry->from_status) <span class="text-body-secondary">(from {{ $entry->from_status }})</span> @endif
                                <div class="text-body-secondary">
                                    {{ $entry->created_at->toDayDateTimeString() }}@if ($entry->changedBy) · {{ $entry->changedBy->name }}@endif
                                </div>
                                @if ($entry->reason)<div>{{ $entry->reason }}</div>@endif
                            </div>
                        @empty
                            <p class="small text-body-secondary mb-0">No status changes recorded.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>

        <div class="col-lg-8">
            <section class="card shadow-sm" aria-labelledby="enrollments-heading">
                <div class="card-header bg-body"><h2 id="enrollments-heading" class="h5 mb-0">Enrollments and grades</h2></div>
                @if ($enrollments->isEmpty())
                    <x-empty-state icon="bi-journal" title="No enrollments" />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <caption class="visually-hidden">Enrollments with status and scores</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Class</th>
                                    <th scope="col">Term</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Score</th>
                                    <th scope="col" class="text-end">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($enrollments as $row)
                                    @php($enrollment = $row['enrollment'])
                                    <tr wire:key="enrollment-{{ $enrollment->id }}">
                                        <td>
                                            @can('view', $enrollment->section)
                                                <a href="{{ route('sections.show', $enrollment->section) }}" class="fw-semibold">{{ $enrollment->section->name }}</a>
                                            @else
                                                <span class="fw-semibold">{{ $enrollment->section->name }}</span>
                                            @endcan
                                            <span class="d-block small text-body-secondary">{{ $enrollment->section->course->title }} · {{ $enrollment->section->course->credits }} cr</span>
                                        </td>
                                        <td class="small">{{ $enrollment->section->term->name }}</td>
                                        <td><x-status-badge :status="$enrollment->status" /></td>
                                        <td class="text-end">{{ $row['score'] !== null ? number_format($row['score'], 1).'%' : '—' }}</td>
                                        <td class="text-end">
                                            @php($letter = $enrollment->letter_grade ?? $calculator->letterFor($row['score']))
                                            {{ $letter ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
