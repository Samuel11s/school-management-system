<div>
    <x-page-header :title="$user ? 'Edit '.$user->name : 'Add user'" :back="route('users.index')" />

    <form wire:submit="save" novalidate class="card shadow-sm" style="max-width: 40rem">
        <div class="card-body">
            <x-form.input name="name" label="Full name" required autocomplete="off" />
            <x-form.input name="email" type="email" label="Email address" required autocomplete="off" />
            <x-form.select name="role" label="Role" :options="$roles" required
                           help="Student accounts are normally created from the student record so the two stay linked." />
            @unless ($user)
                <p class="small text-body-secondary mb-0">
                    <i class="bi bi-envelope me-1" aria-hidden="true"></i>
                    The new user receives an email with a link to set their own password.
                </p>
            @endunless
        </div>
        <div class="card-footer bg-body d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ $user ? 'Save changes' : 'Create user' }}</button>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
