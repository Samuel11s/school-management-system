<x-layouts::guest title="Reset password">
    <h1 class="h4 mb-3">Choose a new password</h1>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input name="email" type="email" label="Email address" :livewire="false"
                      :value="$request->email" autocomplete="username" required />

        <x-form.input name="password" type="password" label="New password" :livewire="false"
                      autocomplete="new-password" required
                      help="At least 10 characters with upper and lower case letters and a number." />

        <x-form.input name="password_confirmation" type="password" label="Confirm new password" :livewire="false"
                      autocomplete="new-password" required />

        <button type="submit" class="btn btn-primary w-100">Reset password</button>
    </form>
</x-layouts::guest>
