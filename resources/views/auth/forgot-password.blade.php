<x-layouts::guest title="Forgot password">
    <span class="empty-state-icon mb-3"><i class="bi bi-key" aria-hidden="true"></i></span>
    <h1 class="h3 mb-1">Forgot your password?</h1>
    <p class="text-body-secondary mb-4">
        Enter your email address and, if an account exists, we will send you a link to choose a new password.
    </p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <x-form.input name="email" type="email" label="Email address" :livewire="false"
                      autocomplete="email" autofocus required />

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-envelope" aria-hidden="true"></i>Email password reset link
        </button>
    </form>

    <p class="text-center small mt-4 mb-0">
        <a href="{{ route('login') }}" class="fw-medium"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to sign in</a>
    </p>
</x-layouts::guest>
