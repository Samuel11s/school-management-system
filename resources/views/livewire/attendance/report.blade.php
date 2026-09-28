<div>
    <x-page-header title="Attendance" subtitle="Attendance rates and records. Excused absences do not count against the rate." />

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form class="row g-2 align-items-end" role="search" wire:submit.prevent>
                <div class="col-sm-6 col-lg-2">
                    <label for="att-term" class="form-label small mb-1">Term</label>
                    <select id="att-term" class="form-select" wire:model.live="term">
                        <option value="">All terms</option>
                        @foreach ($terms as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="att-section" class="form-label small mb-1">Class</label>
                    <select id="att-section" class="form-select" wire:model.live="section">
                        <option value="">All classes</option>
                        @foreach ($sections as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="att-status" class="form-label small mb-1">Status (records)</label>
                    <select id="att-status" class="form-select" wire:model.live="status">
                        <option value="">Any status</option>
                        @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="att-from" class="form-label small mb-1">From</label>
                    <input type="date" id="att-from" class="form-control" wire:model.live="from">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="att-to" class="form-label small mb-1">To</label>
                    <input type="date" id="att-to" class="form-control" wire:model.live="to">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <x-search-input label="Search student" placeholder="Student" />
                </div>
            </form>
        </div>
    </div>

    <section class="card shadow-sm mb-4" aria-labelledby="summary-heading">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 id="summary-heading" class="h5 mb-0">Summary by student <span class="small text-body-secondary">({{ $summaryTotal }})</span></h2>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="below-threshold" wire:model.live="belowThresholdOnly">
                <label class="form-check-label small" for="below-threshold">Only below {{ $threshold }}%</label>
            </div>
        </div>
        @if ($summary->isEmpty())
            <x-empty-state icon="bi-clipboard-data" title="No attendance recorded for these filters" />
        @else
            <div class="table-responsive" wire:loading.class="table-loading">
                <table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Attendance summary by student, lowest rate first</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Student</th>
                            <th scope="col" class="text-end">Sessions</th>
                            <th scope="col" class="text-end">Present</th>
                            <th scope="col" class="text-end">Late</th>
                            <th scope="col" class="text-end">Absent</th>
                            <th scope="col" class="text-end">Excused</th>
                            <th scope="col">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $row)
                            @php($student = $students->get($row['student_id']))
                            <tr wire:key="sum-{{ $row['student_id'] }}">
                                <td>
                                    @if ($student && ! $student->trashed())
                                        <a href="{{ route('students.show', $student) }}">{{ $student->full_name }}</a>
                                    @else
                                        {{ $student?->full_name ?? 'Unknown' }}
                                    @endif
                                </td>
                                <td class="text-end">{{ $row['total'] }}</td>
                                <td class="text-end">{{ $row['present'] }}</td>
                                <td class="text-end">{{ $row['late'] }}</td>
                                <td class="text-end">{{ $row['absent'] }}</td>
                                <td class="text-end">{{ $row['excused'] }}</td>
                                <td style="min-width: 10rem">
                                    @if ($row['rate'] !== null)
                                        <div class="meter small">
                                            <div class="progress" aria-hidden="true">
                                                <div @class(['progress-bar', 'bg-chart-absent' => $row['below_threshold'], 'bg-chart-present' => ! $row['below_threshold']]) style="width: {{ $row['rate'] }}%"></div>
                                            </div>
                                            <span @class(['text-nowrap text-end', 'fw-semibold text-danger' => $row['below_threshold']]) style="min-width: 3.5rem">{{ $row['rate'] }}%</span>
                                            @if ($row['below_threshold']) <i class="bi bi-exclamation-triangle-fill text-danger" role="img" aria-label="Below threshold"></i> @endif
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($summaryTotal > $summary->count())
                <div class="card-footer small text-body-secondary">Showing the {{ $summary->count() }} lowest rates. Narrow the filters to see others.</div>
            @endif
        @endif
    </section>

    <section class="card shadow-sm" aria-labelledby="records-heading">
        <div class="card-header"><h2 id="records-heading" class="h5 mb-0">Records</h2></div>
        @if ($records->isEmpty())
            <x-empty-state icon="bi-list-check" title="No records" />
        @else
            <div class="table-responsive" wire:loading.class="table-loading">
                <table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Attendance records</caption>
                    <thead class="table-light">
                        <tr><th scope="col">Date</th><th scope="col">Class</th><th scope="col">Student</th><th scope="col">Status</th><th scope="col">Remarks</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr wire:key="rec-{{ $record->id }}">
                                <td class="text-nowrap">{{ $record->attended_on->format('D, M j, Y') }}</td>
                                <td>{{ $record->section->name }}</td>
                                <td>{{ $record->student->full_name }}</td>
                                <td><x-status-badge :status="$record->status" /></td>
                                <td class="small">{{ $record->remarks }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($records->hasPages())
                <div class="card-footer">{{ $records->links() }}</div>
            @endif
        @endif
    </section>
</div>
