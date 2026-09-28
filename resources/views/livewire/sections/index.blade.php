<div>
    <x-page-header :title="auth()->user()->isAdmin() ? 'Classes' : 'My classes'" subtitle="Class sections by term, with teachers and capacity.">
        <x-slot:actions>
            @can('create', App\Models\Section::class)
                <a href="{{ route('sections.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add class</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card shadow-sm">
        <div class="card-body filter-bar">
            <form class="row g-2 align-items-end" role="search" wire:submit.prevent>
                <div class="col-md-4">
                    <x-search-input label="Search classes by course" placeholder="Course code or title" />
                </div>
                <div class="col-sm-6 col-md-2">
                    <label for="filter-term" class="form-label small mb-1">Term</label>
                    <select id="filter-term" class="form-select" wire:model.live="term">
                        <option value="">All terms</option>
                        @foreach ($terms as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label for="filter-course" class="form-label small mb-1">Course</label>
                    <select id="filter-course" class="form-select" wire:model.live="course">
                        <option value="">All courses</option>
                        @foreach ($courses as $id => $code)
                            <option value="{{ $id }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($teachers->isNotEmpty())
                    <div class="col-sm-6 col-md-2">
                        <label for="filter-teacher" class="form-label small mb-1">Teacher</label>
                        <select id="filter-teacher" class="form-select" wire:model.live="teacher">
                            <option value="">All teachers</option>
                            @foreach ($teachers as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-sm-6 col-md-2">
                    <label for="filter-status" class="form-label small mb-1">Status</label>
                    <select id="filter-status" class="form-select" wire:model.live="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <div class="table-responsive" wire:loading.class="table-loading">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Classes</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Class</th>
                        <th scope="col">Term</th>
                        <th scope="col">Teacher</th>
                        <th scope="col">Schedule</th>
                        <th scope="col">Seats</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sections as $section)
                        <tr wire:key="section-{{ $section->id }}">
                            <td>
                                <a href="{{ route('sections.show', $section) }}" class="fw-semibold">{{ $section->name }}</a>
                                <span class="d-block small text-body-secondary">{{ $section->course->title }}</span>
                            </td>
                            <td class="small">{{ $section->term->name }}</td>
                            <td>{{ $section->teacher?->name ?? 'Unassigned' }}</td>
                            <td class="small">{{ $section->schedule }}<span class="d-block text-body-secondary">{{ $section->room }}</span></td>
                            <td>
                                @php($fill = $section->capacity > 0 ? (int) round($section->seatsTaken() / $section->capacity * 100) : 0)
                                <div class="meter small" style="min-width: 8rem">
                                    <span @class(['text-nowrap', 'fw-semibold text-danger' => $section->isFull()])>{{ $section->seatsTaken() }} / {{ $section->capacity }}</span>
                                    <div class="progress" aria-hidden="true">
                                        <div @class(['progress-bar', 'bg-chart-absent' => $fill >= 100, 'bg-chart-late' => $fill >= 80 && $fill < 100]) style="width: {{ min($fill, 100) }}%"></div>
                                    </div>
                                </div>
                                @if ($section->isFull()) <span class="visually-hidden">(full)</span> @endif
                            </td>
                            <td><x-status-badge :status="$section->status" /></td>
                            <td class="text-end text-nowrap">
                                @can('update', $section)
                                    <a href="{{ route('sections.edit', $section) }}" class="btn btn-sm btn-outline-primary">Edit<span class="visually-hidden"> {{ $section->name }}</span></a>
                                @endcan
                                @can('delete', $section)
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $section->id }})"
                                            wire:confirm="Delete class {{ $section->name }}?">Delete<span class="visually-hidden"> {{ $section->name }}</span></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-easel" title="No classes found" message="Try another term or clear the filters." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sections->hasPages())
            <div class="card-footer">{{ $sections->links() }}</div>
        @endif
    </div>
</div>
