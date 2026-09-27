@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a href="#main-content" class="skip-link btn btn-primary">Skip to main content</a>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-brand-mark sticky-top" aria-label="Top navigation">
        <div class="container-fluid">
            <button class="navbar-toggler me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar"
                    aria-controls="sidebar" aria-label="Open navigation menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">
                <i class="bi bi-mortarboard-fill me-1" aria-hidden="true"></i>{{ config('app.name') }}
            </a>
            <div class="ms-auto dropdown">
                <button class="btn btn-link link-light text-decoration-none dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                    <span class="visually-hidden d-sm-none">Account menu</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-body-secondary">{{ auth()->user()->primaryRole()?->label() }}</span></li>
                    <li><a class="dropdown-item" href="{{ route('account') }}"><i class="bi bi-gear me-2" aria-hidden="true"></i>Account settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="d-lg-flex">
        <aside class="offcanvas-lg offcanvas-start sidebar bg-body border-end" tabindex="-1" id="sidebar" aria-labelledby="sidebar-label">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h5" id="sidebar-label">Menu</h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
            </div>
            <div class="offcanvas-body p-3">
                @include('layouts.partials.nav')
            </div>
        </aside>

        <main id="main-content" class="flex-grow-1 p-3 p-lg-4" tabindex="-1">
            @if (session('status'))
                <span hidden data-flash-message="{{ session('status') }}" data-flash-type="{{ session('status-type', 'success') }}"></span>
            @endif

            {{ $slot }}
        </main>
    </div>

    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" aria-live="polite"></div>

    @livewireScripts
</body>
</html>
