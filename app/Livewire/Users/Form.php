<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'teacher';

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->authorize('update', $user);
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->primaryRole()->value ?? Role::Teacher->value;

            return;
        }

        $this->authorize('create', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    public function save(UserService $users): void
    {
        $this->user ? $this->authorize('update', $this->user) : $this->authorize('create', User::class);
        $validated = $this->validate();
        $role = Role::from($validated['role']);

        // Administrators cannot remove their own admin role.
        if ($this->user?->is(auth()->user()) && $role !== Role::Admin) {
            $this->addError('role', 'You cannot remove your own administrator role.');

            return;
        }

        $this->user
            ? $users->update($this->user, $validated, $role)
            : $users->create($validated, $role);

        session()->flash('status', $this->user ? 'User updated.' : 'User created. A password setup link was emailed to them.');
        $this->redirectRoute('users.index');
    }

    public function render(): View
    {
        return view('livewire.users.form', ['roles' => Role::options()])
            ->title($this->user ? 'Edit '.$this->user->name : 'Add user');
    }
}
