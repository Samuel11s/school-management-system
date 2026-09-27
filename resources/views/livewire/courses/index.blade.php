<div>
    <x-page-header title="Courses" subtitle="The course catalog offered by the school.">
        <x-slot:actions>
            @can('create', App\Models\Course::class)
                <a href="{{ route('courses.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add course
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end" role="search" wire:submit.prevent>
                <div class="col-md-6">
                    <x-search-input label="Search courses" placeholder="Code or title" />
                </div>
                <div class="col-sm-6 col-md-3">
                    <label for="filter-department" class="form-label small mb-1">Department</label>
                    <select id="filter-department" class="form-select" wire:model.live="department">
                        <option value="">All departments</option>
                        @foreach ($departments as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label for="filter-active" class="form-label small mb-1">Availability</label>
                    <select id="filter-active" class="form-select" wire:model.live="active">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="table-responsive" wire:loading.class="table-loading">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Courses</caption>
                <thead class="table-light">
                    <tr>
                        <x-sort-header field="code" label="Code" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" />
                        <x-sort-header field="title" label="Title" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" />
                        <x-sort-header field="department" label="Department" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" />
                        <x-sort-header field="credits" label="Credits" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" />
                        <th scope="col">Prerequisites</th>
                        <th scope="col">Classes</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($courses as $course)
                        <tr wire:key="course-{{ $course->id }}">
                            <td class="font-monospace">{{ $course->code }}</td>
                            <td>
                                {{ $course->title }}
                                @unless ($course->is_active) <span class="badge text-bg-secondary ms-1">Inactive</span> @endunless
                            </td>
                            <td>{{ $course->department ?? '—' }}</td>
                            <td>{{ $course->credits }}</td>
                            <td class="small">{{ $course->prerequisites->pluck('code')->join(', ') ?: '—' }}</td>
                            <td>
                                <a href="{{ route('sections.index', ['course' => $course->id, 'term' => '']) }}">{{ $course->sections_count }}</a>
                            </td>
                            <td class="text-end text-nowrap">
                                @can('update', $course)
                                    <a href="{{ route('courses.edit', $course) }}" class="btn btn-sm btn-outline-primary">
                                        Edit<span class="visually-hidden"> {{ $course->code }}</span>
                                    </a>
                                @endcan
                                @can('delete', $course)
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $course->id }})"
                                            wire:confirm="Delete course {{ $course->code }}?">
                                        Delete<span class="visually-hidden"> {{ $course->code }}</span>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-journal-bookmark" title="No courses found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($courses->hasPages())
            <div class="card-footer bg-body">{{ $courses->links() }}</div>
        @endif
    </div>
</div>
