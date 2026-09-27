<x-layouts::guest title="Forgot password">
    <h1 class="h4 mb-2">Forgot your password?</h1>
    <p class="text-body-secondary small">
        Enter your email address and, if an account exists, we will send you a link to choose a new password.
    </p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <x-form.input name="email" type="email" label="Email address" :livewire="false"
                      autocomplete="email" autofocus required />

        <button type="submit" class="btn btn-primary w-100">Email password reset link</button>
    </form>

    <p class="text-center small mt-3 mb-0"><a href="{{ route('login') }}">Back to sign in</a></p>
</x-layouts::guest>
