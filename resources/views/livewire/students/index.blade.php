<div>
    <x-page-header title="Students" subtitle="Search, filter and manage student records.">
        <x-slot:actions>
            @can('create', App\Models\Student::class)
                <a href="{{ route('students.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add student
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end" role="search" wire:submit.prevent>
                <div class="col-md-5">
                    <x-search-input label="Search students" placeholder="Name, email or student number" />
                </div>
                <div class="col-sm-6 col-md-3">
                    <label for="filter-status" class="form-label small mb-1">Status</label>
                    <select id="filter-status" class="form-select" wire:model.live="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label for="filter-grade" class="form-label small mb-1">Grade level</label>
                    <select id="filter-grade" class="form-select" wire:model.live="gradeLevel">
                        <option value="">All</option>
                        @foreach (range(1, 12) as $level)
                            <option value="{{ $level }}">Grade {{ $level }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" wire:click="resetFilters">Clear</button>
                </div>
            </form>
        </div>

        <div class="table-responsive" wire:loading.class="table-loading">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Students</caption>
                <thead class="table-light">
                    <tr>
                        <x-sort-header field="student_number" label="Number" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" />
                        <x-sort-header field="last_name" label="Name" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" />
                        <th scope="col">Email</th>
                        <x-sort-header field="grade_level" label="Grade" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" />
                        <x-sort-header field="status" label="Status" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" />
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr wire:key="student-{{ $student->id }}">
                            <td class="font-monospace small">{{ $student->student_number }}</td>
                            <td>
                                <a href="{{ route('students.show', $student) }}" class="fw-semibold text-decoration-none">
                                    {{ $student->last_name }}, {{ $student->first_name }}
                                </a>
                            </td>
                            <td class="small">{{ $student->email }}</td>
                            <td>{{ $student->grade_level ?? '—' }}</td>
                            <td><x-status-badge :status="$student->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('students.show', $student) }}" class="btn btn-sm btn-outline-secondary">
                                    View<span class="visually-hidden"> {{ $student->full_name }}</span>
                                </a>
                                @can('update', $student)
                                    <a href="{{ route('students.edit', $student) }}" class="btn btn-sm btn-outline-primary">
                                        Edit<span class="visually-hidden"> {{ $student->full_name }}</span>
                                    </a>
                                @endcan
                                @can('delete', $student)
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            wire:click="delete({{ $student->id }})"
                                            wire:confirm="Delete {{ $student->full_name }}? Active enrollments will be dropped and their login disabled.">
                                        Delete<span class="visually-hidden"> {{ $student->full_name }}</span>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-people" title="No students found"
                                               message="Try a different search or clear the filters." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($students->hasPages())
            <div class="card-footer bg-body">{{ $students->links() }}</div>
        @endif
    </div>
</div>
