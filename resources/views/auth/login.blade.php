<x-layouts::guest title="Sign in">
    <h1 class="h3 mb-1">Welcome back</h1>
    <p class="text-body-secondary mb-4">Sign in to continue to your dashboard.</p>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <x-form.input name="email" type="email" label="Email address" :livewire="false"
                      autocomplete="username" autofocus required />

        <x-form.input name="password" type="password" label="Password" :livewire="false"
                      autocomplete="current-password" required />

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <a href="{{ route('password.request') }}" class="small fw-medium">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Sign in
        </button>
    </form>

    @if (app()->environment('local'))
        <div class="alert alert-info small mt-4 mb-0" role="note">
            <p class="fw-semibold mb-1"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Demo accounts</p>
            Password <code>password</code> for admin@school.test, teacher@school.test and student@school.test.
        </div>
    @endif
</x-layouts::guest>
