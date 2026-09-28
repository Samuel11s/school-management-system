<x-layouts::guest title="Reset password">
    <span class="empty-state-icon mb-3"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
    <h1 class="h3 mb-1">Choose a new password</h1>
    <p class="text-body-secondary mb-4">Use at least 10 characters with upper and lower case letters and a number.</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input name="email" type="email" label="Email address" :livewire="false"
                      :value="$request->email" autocomplete="username" required />

        <x-form.input name="password" type="password" label="New password" :livewire="false"
                      autocomplete="new-password" required />

        <x-form.input name="password_confirmation" type="password" label="Confirm new password" :livewire="false"
                      autocomplete="new-password" required />

        <button type="submit" class="btn btn-primary w-100 py-2">Reset password</button>
    </form>
</x-layouts::guest>
