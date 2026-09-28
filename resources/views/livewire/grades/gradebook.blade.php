<div>
    <x-page-header :title="'Gradebook · '.$section->name" :subtitle="$section->course->title.' · '.$section->term->name"
                   :back="route('sections.show', $section)">
        <x-slot:actions>
            <button type="button" class="btn btn-outline-primary" wire:click="newAssessment">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add assessment
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card label="Class average" :value="$summary['class_average'] !== null ? number_format($summary['class_average'], 1).'%' : '—'" icon="bi-graph-up" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Students" :value="$summary['students']->count()" icon="bi-people" variant="info" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Assessments" :value="$summary['assessments']->count()" icon="bi-journal-text" variant="secondary" /></div>
        <div class="col-6 col-lg-3">
            <x-stat-card label="Weight allocated" :value="rtrim(rtrim(number_format($summary['total_weight'], 2), '0'), '.').'%'" icon="bi-pie-chart"
                         :variant="$summary['total_weight'] == 100 ? 'success' : 'warning'" />
        </div>
    </div>

    @php($letters = array_keys(config('school.grading.scale')))
    @php($maxCount = max([1, ...array_values($summary['distribution'])]))
    @if ($summary['distribution'] !== [])
        <section class="card shadow-sm mb-4" aria-labelledby="distribution-heading">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 id="distribution-heading" class="h6 mb-0 d-flex align-items-center">
                    <span class="card-title-icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></span>Grade distribution
                </h2>
                <span class="small text-body-secondary">Students by current letter grade</span>
            </div>
            <div class="card-body">
                <div class="column-chart" role="img"
                     aria-label="Grade distribution: {{ collect($letters)->map(fn ($l) => $l.' '.($summary['distribution'][$l] ?? 0))->join(', ') }}">
                    @foreach ($letters as $letter)
                        @php($count = $summary['distribution'][$letter] ?? 0)
                        <div class="column">
                            <span class="column-value">{{ $count }}</span>
                            <div class="column-bar" style="height: {{ $count / $maxCount * 100 }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="column-labels" aria-hidden="true">
                    @foreach ($letters as $letter)<span>{{ $letter }}</span>@endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($showAssessmentForm)
        <section class="card shadow-sm mb-4 border-primary" aria-labelledby="assessment-form-heading">
            <form wire:submit="saveAssessment" novalidate class="card-body">
                <h2 id="assessment-form-heading" class="h5">{{ $editingAssessmentId ? 'Edit assessment' : 'New assessment' }}</h2>
                <div class="row">
                    <x-form.input class="col-md-4" name="assessment.title" label="Title" required />
                    <x-form.select class="col-md-2" name="assessment.type" label="Type" :options="$types" required />
                    <x-form.input class="col-md-2" name="assessment.max_score" type="number" step="0.01" min="0.01" label="Max score" required />
                    <x-form.input class="col-md-2" name="assessment.weight" type="number" step="0.01" min="0.01" max="100" label="Weight (%)" required />
                    <x-form.input class="col-md-2" name="assessment.due_on" type="date" label="Due date" />
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save assessment</button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('showAssessmentForm', false)">Cancel</button>
                </div>
            </form>
        </section>
    @endif

    <form wire:submit="saveGrades" class="card shadow-sm" novalidate>
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Scores</h2>
            <div class="d-flex align-items-center gap-2">
                <x-loading target="saveGrades" label="Saving" />
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" @disabled($summary['assessments']->isEmpty())>
                    <i class="bi bi-save me-1" aria-hidden="true"></i>Save grades
                </button>
            </div>
        </div>

        @if ($summary['assessments']->isEmpty())
            <x-empty-state icon="bi-journal-plus" title="No assessments yet"
                           message="Add an assessment (exam, quiz, assignment…) to start recording grades. Weights should add up to 100%." />
        @elseif ($summary['students']->isEmpty())
            <x-empty-state icon="bi-people" title="No students are enrolled in this class" />
        @else
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <caption class="px-3">
                        Enter scores and select “Save grades”. Clear a score to remove it. The current grade is the weighted average of graded assessments.
                    </caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Student</th>
                            @foreach ($summary['assessments'] as $item)
                                @php($a = $item['assessment'])
                                <th scope="col" class="text-center" wire:key="head-{{ $a->id }}">
                                    <span class="d-block">{{ $a->title }}</span>
                                    <span class="small fw-normal text-body-secondary">/{{ rtrim(rtrim($a->max_score, '0'), '.') }} · {{ rtrim(rtrim($a->weight, '0'), '.') }}%</span>
                                    <span class="d-block">
                                        <button type="button" class="btn btn-link btn-sm p-0" wire:click="editAssessment({{ $a->id }})">Edit<span class="visually-hidden"> {{ $a->title }}</span></button>
                                        ·
                                        <button type="button" class="btn btn-link btn-sm p-0 text-danger" wire:click="deleteAssessment({{ $a->id }})"
                                                wire:confirm="Delete {{ $a->title }} and all of its recorded grades?">Delete<span class="visually-hidden"> {{ $a->title }}</span></button>
                                    </span>
                                </th>
                            @endforeach
                            <th scope="col" class="text-end">Current grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['students'] as $row)
                            @php($enrollment = $row['enrollment'])
                            <tr wire:key="row-{{ $enrollment->id }}">
                                <th scope="row" class="fw-normal">
                                    {{ $enrollment->student->last_name }}, {{ $enrollment->student->first_name }}
                                    @unless ($enrollment->isActive()) <x-status-badge :status="$enrollment->status" /> @endunless
                                </th>
                                @foreach ($summary['assessments'] as $item)
                                    @php($a = $item['assessment'])
                                    @php($field = 'scores.'.$enrollment->id.'.'.$a->id)
                                    <td class="text-center">
                                        @if ($enrollment->isActive())
                                            <label for="score-{{ $enrollment->id }}-{{ $a->id }}" class="visually-hidden">
                                                {{ $a->title }} score for {{ $enrollment->student->full_name }}
                                            </label>
                                            <input type="number" step="0.01" min="0" max="{{ $a->max_score }}" inputmode="decimal"
                                                   id="score-{{ $enrollment->id }}-{{ $a->id }}"
                                                   wire:model="{{ $field }}"
                                                   @class(['form-control form-control-sm gradebook-input text-end mx-auto', 'is-invalid' => $errors->has($field)])
                                                   @error($field) aria-invalid="true" title="{{ $message }}" @enderror>
                                            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        @else
                                            {{ $enrollment->grades->firstWhere('assessment_id', $a->id)?->score ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-end text-nowrap">
                                    @if ($row['score'] !== null)
                                        <span @class(['text-danger' => ! $row['passing']])>{{ number_format($row['score'], 1) }}%</span>
                                        <span class="badge badge-soft badge-soft-secondary">{{ $row['letter'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th scope="row">Average</th>
                            @foreach ($summary['assessments'] as $item)
                                <td class="text-center small">
                                    {{ $item['average'] !== null ? number_format($item['average'], 1).'%' : '—' }}
                                    <span class="d-block text-body-secondary">{{ $item['graded'] }} graded</span>
                                </td>
                            @endforeach
                            <td class="text-end">
                                {{ $summary['class_average'] !== null ? number_format($summary['class_average'], 1).'%' : '—' }}
                                <span class="d-block small text-body-secondary">
                                    @foreach ($summary['distribution'] as $letter => $count){{ $letter }}: {{ $count }}@if (! $loop->last), @endif @endforeach
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </form>
</div>
