<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Users')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $active = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $userId, UserService $users): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('deactivate', $user);

        $users->setActive($user, ! $user->is_active);

        $this->dispatch('notify', message: $user->is_active ? "{$user->name} was reactivated." : "{$user->name} was deactivated and signed out of the API.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', User::class);

        return view('livewire.users.index', [
            'users' => User::query()
                ->with('roles')
                ->search($this->search)
                ->when(Role::tryFrom($this->role), fn ($q, $role) => $q->role($role->value))
                ->when($this->active !== '', fn ($q) => $q->where('is_active', $this->active === '1'))
                ->orderBy('name')
                ->paginate(15),
            'roles' => Role::options(),
        ]);
    }
}
