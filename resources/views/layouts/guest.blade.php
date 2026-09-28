@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell">
        <aside class="auth-aside d-none d-lg-flex flex-column justify-content-between" aria-label="About {{ config('app.name') }}">
            <span class="brand text-white">
                <span class="brand-mark bg-white bg-opacity-25 shadow-none"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                {{ config('app.name') }}
            </span>

            <div style="max-width: 30rem">
                <h2 class="display-6 fw-bold mb-3">Everything your school runs on, in one place.</h2>
                <p class="lead mb-5">Students, classes, grades and attendance, with the right access for administrators, teachers and students.</p>

                <div class="d-grid gap-4">
                    <div class="auth-feature">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <div>
                            <p class="fw-semibold text-white mb-1">Student records</p>
                            <p class="small mb-0">Profiles, enrollment history and academic summaries.</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <i class="bi bi-journal-check" aria-hidden="true"></i>
                        <div>
                            <p class="fw-semibold text-white mb-1">Gradebooks</p>
                            <p class="small mb-0">Weighted assessments, averages and grade distributions.</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <i class="bi bi-clipboard2-check" aria-hidden="true"></i>
                        <div>
                            <p class="fw-semibold text-white mb-1">Attendance</p>
                            <p class="small mb-0">Daily registers with rates and low-attendance alerts.</p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="small mb-0" style="color: rgba(255, 255, 255, .85)">&copy; {{ now()->year }} {{ config('app.name') }}</p>
        </aside>

        <main class="auth-main">
            <div class="auth-card">
                <div class="d-lg-none text-center mb-4">
                    <span class="brand justify-content-center">
                        <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                        {{ config('app.name') }}
                    </span>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body p-4 p-sm-5">
                        @if (session('status'))
                            <div class="alert alert-success d-flex gap-2" role="status">
                                <i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>{{ session('status') }}</span>
                            </div>
                        @endif

                        {{ $slot }}
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
