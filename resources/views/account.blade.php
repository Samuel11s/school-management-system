<x-layouts::app title="Account settings">
    <x-page-header title="Account settings" subtitle="Manage your profile and password." />

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="card shadow-sm h-100" aria-labelledby="profile-heading">
                <div class="card-body">
                    <h2 id="profile-heading" class="h5">Profile information</h2>

                    @if (session('status') === 'profile-information-updated')
                        <div class="alert alert-success" role="status">Your profile has been updated.</div>
                    @endif

                    <form method="POST" action="{{ route('user-profile-information.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        @php($profileErrors = $errors->getBag('updateProfileInformation'))

                        <div class="mb-3">
                            <label for="profile-name" class="form-label">Name</label>
                            <input id="profile-name" name="name" type="text" autocomplete="name" required
                                   value="{{ old('name', $user->name) }}"
                                   @class(['form-control', 'is-invalid' => $profileErrors->has('name')])
                                   @if ($profileErrors->has('name')) aria-invalid="true" aria-describedby="profile-name-error" @endif>
                            @if ($profileErrors->has('name'))
                                <div id="profile-name-error" class="invalid-feedback">{{ $profileErrors->first('name') }}</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="profile-email" class="form-label">Email address</label>
                            <input id="profile-email" name="email" type="email" autocomplete="email" required
                                   value="{{ old('email', $user->email) }}"
                                   @class(['form-control', 'is-invalid' => $profileErrors->has('email')])
                                   @if ($profileErrors->has('email')) aria-invalid="true" aria-describedby="profile-email-error" @endif>
                            @if ($profileErrors->has('email'))
                                <div id="profile-email-error" class="invalid-feedback">{{ $profileErrors->first('email') }}</div>
                            @endif
                        </div>

                        <p class="small text-body-secondary">
                            Role: <strong>{{ $user->primaryRole()?->label() ?? 'None' }}</strong>
                            @if ($user->last_login_at)
                                &middot; Last sign-in {{ $user->last_login_at->diffForHumans() }}
                            @endif
                        </p>

                        <button type="submit" class="btn btn-primary">Save profile</button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card shadow-sm h-100" aria-labelledby="password-heading">
                <div class="card-body">
                    <h2 id="password-heading" class="h5">Change password</h2>

                    @if (session('status') === 'password-updated')
                        <div class="alert alert-success" role="status">Your password has been changed.</div>
                    @endif

                    <form method="POST" action="{{ route('user-password.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        @php($passwordErrors = $errors->getBag('updatePassword'))

                        @foreach ([
                            'current_password' => ['Current password', 'current-password'],
                            'password' => ['New password', 'new-password'],
                            'password_confirmation' => ['Confirm new password', 'new-password'],
                        ] as $field => [$label, $autocomplete])
                            <div class="mb-3">
                                <label for="pw-{{ $field }}" class="form-label">{{ $label }}</label>
                                <input id="pw-{{ $field }}" name="{{ $field }}" type="password" autocomplete="{{ $autocomplete }}" required
                                       @class(['form-control', 'is-invalid' => $passwordErrors->has($field)])
                                       @if ($passwordErrors->has($field)) aria-invalid="true" aria-describedby="pw-{{ $field }}-error" @endif>
                                @if ($passwordErrors->has($field))
                                    <div id="pw-{{ $field }}-error" class="invalid-feedback">{{ $passwordErrors->first($field) }}</div>
                                @endif
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-primary">Change password</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
