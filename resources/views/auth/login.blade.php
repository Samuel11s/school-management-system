<x-layouts::guest title="Sign in">
    <h1 class="h4 mb-3">Sign in</h1>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <x-form.input name="email" type="email" label="Email address" :livewire="false"
                      autocomplete="username" autofocus required />

        <x-form.input name="password" type="password" label="Password" :livewire="false"
                      autocomplete="current-password" required />

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Forgot your password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>

    @if (app()->environment('local'))
        <div class="alert alert-info small mt-4 mb-0" role="note">
            <strong>Demo accounts</strong> (password <code>password</code>):
            admin@school.test, teacher@school.test, student@school.test
        </div>
    @endif
</x-layouts::guest>
