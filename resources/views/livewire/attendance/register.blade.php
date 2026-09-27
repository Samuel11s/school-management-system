<div>
    <x-page-header :title="'Attendance · '.$section->name" :subtitle="$section->course->title.' · '.$section->term->name"
                   :back="route('sections.show', $section)" />

    <div class="row g-4">
        <div class="col-xl-9">
            <form wire:submit="save" class="card shadow-sm" novalidate>
                <div class="card-header bg-body d-flex flex-wrap gap-3 align-items-end justify-content-between">
                    <div>
                        <label for="register-date" class="form-label small mb-1">Session date</label>
                        <input type="date" id="register-date" wire:model.live="date"
                               min="{{ $section->term->starts_on->toDateString() }}" max="{{ now()->min($section->term->ends_on)->toDateString() }}"
                               @class(['form-control', 'is-invalid' => $errors->has('date')])
                               @error('date') aria-invalid="true" aria-describedby="register-date-error" @enderror>
                        @error('date') <div id="register-date-error" class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @if ($alreadyRecorded)
                            <span class="badge text-bg-info">Already recorded — saving updates it</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="markAll('present')">Mark all present</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="markAll('absent')">Mark all absent</button>
                    </div>
                </div>

                @error('entries') <div class="alert alert-danger m-3" role="alert">{{ $message }}</div> @enderror

                @if ($students->isEmpty())
                    <x-empty-state icon="bi-people" title="No students are enrolled in this class" />
                @else
                    <div class="table-responsive" wire:loading.class="table-loading" wire:target="date,save">
                        <table class="table align-middle mb-0">
                            <caption class="visually-hidden">Attendance register</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    <tr wire:key="att-{{ $student->id }}">
                                        <th scope="row" class="fw-normal">{{ $student->last_name }}, {{ $student->first_name }}</th>
                                        <td>
                                            <fieldset>
                                                <legend class="visually-hidden">Attendance status for {{ $student->full_name }}</legend>
                                                <div class="btn-group btn-group-sm flex-wrap" role="group">
                                                    @foreach ($statuses as $status)
                                                        <input type="radio" class="btn-check" id="att-{{ $student->id }}-{{ $status->value }}"
                                                               value="{{ $status->value }}" wire:model="entries.{{ $student->id }}.status" autocomplete="off">
                                                        <label class="btn btn-outline-{{ $status->badge() }}" for="att-{{ $student->id }}-{{ $status->value }}">{{ $status->label() }}</label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                            @error('entries.'.$student->id.'.status') <div class="text-danger small">{{ $message }}</div> @enderror
                                        </td>
                                        <td>
                                            <label for="remarks-{{ $student->id }}" class="visually-hidden">Remarks for {{ $student->full_name }}</label>
                                            <input type="text" id="remarks-{{ $student->id }}" class="form-control form-control-sm" maxlength="255"
                                                   wire:model="entries.{{ $student->id }}.remarks">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-body d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <i class="bi bi-save me-1" aria-hidden="true"></i>Save register
                        </button>
                        <x-loading target="save" label="Saving" />
                    </div>
                @endif
            </form>
        </div>

        <aside class="col-xl-3" aria-labelledby="recent-heading">
            <div class="card shadow-sm">
                <div class="card-header bg-body"><h2 id="recent-heading" class="h6 mb-0">Recent sessions</h2></div>
                @if ($recentSessions->isEmpty())
                    <x-empty-state icon="bi-calendar-x" title="No sessions recorded" />
                @else
                    <ul class="list-group list-group-flush small">
                        @foreach ($recentSessions as $session)
                            @php($day = \Illuminate\Support\Carbon::parse($session->attended_on))
                            <li class="list-group-item d-flex justify-content-between">
                                <button type="button" class="btn btn-link btn-sm p-0" wire:click="$set('date', '{{ $day->toDateString() }}')">
                                    {{ $day->format('D, M j') }}
                                </button>
                                <span>{{ (int) $session->attended }} / {{ (int) $session->total }} present</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
</div>
