<div>
    <x-page-header title="Academic terms" subtitle="Terms define class dates and enrollment windows.">
        <x-slot:actions>
            <a href="{{ route('terms.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add term</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card shadow-sm">
        <div class="table-responsive" wire:loading.class="table-loading">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Academic terms</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Term</th>
                        <th scope="col">Dates</th>
                        <th scope="col">Enrollment window</th>
                        <th scope="col">Classes</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($terms as $term)
                        <tr wire:key="term-{{ $term->id }}">
                            <td>
                                <strong>{{ $term->name }}</strong> <span class="font-monospace small text-body-secondary">{{ $term->code }}</span>
                                @if ($term->is_current) <span class="badge badge-soft badge-soft-success ms-1">Current</span> @endif
                            </td>
                            <td class="small">{{ $term->starts_on->toFormattedDateString() }} – {{ $term->ends_on->toFormattedDateString() }}</td>
                            <td class="small">
                                {{ $term->enrollment_opens_on->toFormattedDateString() }} – {{ $term->enrollment_closes_on->toFormattedDateString() }}
                                @if ($term->isEnrollmentOpen()) <span class="badge badge-soft badge-soft-info ms-1">Open</span> @endif
                            </td>
                            <td><a href="{{ route('sections.index', ['term' => $term->id]) }}">{{ $term->sections_count }}</a></td>
                            <td class="text-end text-nowrap">
                                @unless ($term->is_current)
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="makeCurrent({{ $term->id }})"
                                            wire:confirm="Make {{ $term->name }} the current term?">Set current</button>
                                @endunless
                                <a href="{{ route('terms.edit', $term) }}" class="btn btn-sm btn-outline-primary">Edit<span class="visually-hidden"> {{ $term->name }}</span></a>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $term->id }})"
                                        wire:confirm="Delete {{ $term->name }}?">Delete<span class="visually-hidden"> {{ $term->name }}</span></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="bi-calendar3" title="No academic terms yet" message="Create a term before adding classes." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($terms->hasPages())
            <div class="card-footer">{{ $terms->links() }}</div>
        @endif
    </div>
</div>
