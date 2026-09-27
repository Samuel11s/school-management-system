<div>
    <x-page-header title="Users" subtitle="Staff and student login accounts.">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add user</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end" role="search" wire:submit.prevent>
                <div class="col-md-6"><x-search-input label="Search users" placeholder="Name or email" /></div>
                <div class="col-sm-6 col-md-3">
                    <label for="filter-role" class="form-label small mb-1">Role</label>
                    <select id="filter-role" class="form-select" wire:model.live="role">
                        <option value="">All roles</option>
                        @foreach ($roles as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label for="filter-active" class="form-label small mb-1">Status</label>
                    <select id="filter-active" class="form-select" wire:model.live="active">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Deactivated</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="table-responsive" wire:loading.class="table-loading">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">User accounts</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last sign-in</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td>{{ $user->name }}</td>
                            <td class="small">{{ $user->email }}</td>
                            <td>{{ $user->roles->pluck('name')->map(fn ($r) => ucfirst($r))->join(', ') ?: '—' }}</td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Deactivated</span>
                                @endif
                            </td>
                            <td class="small">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit<span class="visually-hidden"> {{ $user->name }}</span></a>
                                @can('deactivate', $user)
                                    <button type="button" @class(['btn btn-sm', 'btn-outline-danger' => $user->is_active, 'btn-outline-success' => ! $user->is_active])
                                            wire:click="toggleActive({{ $user->id }})"
                                            wire:confirm="{{ $user->is_active ? 'Deactivate '.$user->name.'? They will be signed out and unable to log in.' : 'Reactivate '.$user->name.'?' }}">
                                        {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}<span class="visually-hidden"> {{ $user->name }}</span>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-person" title="No users found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer bg-body">{{ $users->links() }}</div>
        @endif
    </div>
</div>
