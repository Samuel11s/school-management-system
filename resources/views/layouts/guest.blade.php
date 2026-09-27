@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center py-5">
    <main class="container" style="max-width: 28rem;">
        <div class="text-center mb-4">
            <i class="bi bi-mortarboard-fill display-5 text-primary" aria-hidden="true"></i>
            <p class="h4 mt-2 mb-0">{{ config('app.name') }}</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
