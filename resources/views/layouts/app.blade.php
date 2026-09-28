@props(['title' => null])

@php
    $authUser = auth()->user();
    $currentTerm = App\Models\AcademicTerm::query()->current()->value('name');
    $searchesStudents = $authUser->can('viewAny', App\Models\Student::class);
    $searchAction = $searchesStudents ? route('students.index') : route('sections.index');
    $searchLabel = $searchesStudents ? 'Search students' : 'Search classes';
    $searchPlaceholder = $searchesStudents ? 'Search students by name, email or number' : 'Search classes by course';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-debug" content="{{ config('app.debug') ? 'true' : 'false' }}">
    <meta name="theme-color" content="#4f46e5">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a href="#main-content" class="skip-link btn btn-primary">Skip to main content</a>

    <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebar-label">
        <div class="offcanvas-header border-bottom">
            <span id="sidebar-label" class="brand">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                {{ config('app.name') }}
            </span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
        </div>
        <div class="offcanvas-body p-3">
            <a href="{{ route('dashboard') }}" class="brand d-none d-lg-flex px-2 pt-1 pb-3">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                <span class="lh-sm">School<br><span class="fw-medium small text-body-secondary">Management</span></span>
            </a>

            <a href="{{ route('account') }}" class="sidebar-profile mb-1">
                <x-avatar :name="$authUser->name" size="md" />
                <span class="d-flex flex-column min-w-0 flex-grow-1">
                    <span class="fw-semibold text-truncate">{{ $authUser->name }}</span>
                    <span class="small text-body-secondary">{{ $authUser->primaryRole()?->label() ?? 'User' }}</span>
                </span>
                <i class="bi bi-chevron-right small text-body-secondary" aria-hidden="true"></i>
                <span class="visually-hidden">Account settings</span>
            </a>

            @include('layouts.partials.nav')

            <p class="small text-body-secondary px-2 mt-auto pt-4 mb-0">
                &copy; {{ now()->year }} {{ config('app.name') }}
            </p>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar d-flex align-items-center gap-2 gap-md-3 px-3 px-md-4">
            <button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar"
                    aria-controls="sidebar" aria-label="Open navigation menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>

            <form action="{{ $searchAction }}" method="GET" role="search" class="topbar-search position-relative flex-grow-1 d-none d-sm-block">
                <label for="global-search" class="visually-hidden">{{ $searchLabel }}</label>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="global-search" name="search" class="form-control rounded-pill"
                       placeholder="{{ $searchPlaceholder }}" value="{{ request()->routeIs('students.index', 'sections.index') ? request('search') : '' }}"
                       autocomplete="off">
            </form>

            <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">
                @if ($currentTerm)
                    <span class="term-chip d-none d-md-inline-flex" title="Current academic term">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i>{{ $currentTerm }}
                    </span>
                @endif

                <a href="{{ $searchAction }}" class="icon-btn d-sm-none" aria-label="{{ $searchLabel }}">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </a>

                <div class="dropdown">
                    <button class="user-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <x-avatar :name="$authUser->name" />
                        <span class="d-none d-md-flex flex-column text-start lh-sm">
                            <span class="fw-semibold small">{{ $authUser->name }}</span>
                            <span class="text-body-secondary" style="font-size: .75rem">{{ $authUser->primaryRole()?->label() }}</span>
                        </span>
                        <i class="bi bi-chevron-down small text-body-secondary d-none d-md-inline" aria-hidden="true"></i>
                        <span class="visually-hidden d-md-none">Account menu</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2">
                            <span class="d-block fw-semibold small">{{ $authUser->name }}</span>
                            <span class="d-block small text-body-secondary text-truncate" style="max-width: 14rem">{{ $authUser->email }}</span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @if ($authUser->student && Route::has('students.show'))
                            <li><a class="dropdown-item" href="{{ route('students.show', $authUser->student) }}"><i class="bi bi-person-vcard me-2" aria-hidden="true"></i>My record</a></li>
                        @endif
                        <li><a class="dropdown-item" href="{{ route('account') }}"><i class="bi bi-gear me-2" aria-hidden="true"></i>Account settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main id="main-content" class="app-content" tabindex="-1">
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
