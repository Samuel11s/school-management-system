@props(['code', 'icon', 'title', 'message'])

{{-- Standalone error page: must not depend on the authenticated shell. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="d-flex align-items-center min-vh-100">
    <main class="container py-5" style="max-width: 34rem">
        <div class="card shadow-sm text-center">
            <div class="card-body p-4 p-sm-5">
                <span class="empty-state-icon mb-3"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                <p class="error-code mb-2" aria-hidden="true">{{ $code }}</p>
                <h1 class="h3 mb-2">{{ $title }}</h1>
                <p class="text-body-secondary mb-4">{{ $message }}</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>Go back
                    </a>
                    <a href="{{ url('/') }}" class="btn btn-primary">
                        <i class="bi bi-house" aria-hidden="true"></i>{{ auth()->check() ? 'Dashboard' : 'Home' }}
                    </a>
                </div>
            </div>
        </div>
        <p class="text-center small text-body-secondary mt-4 mb-0">Error {{ $code }} · {{ config('app.name') }}</p>
    </main>
</body>
</html>
