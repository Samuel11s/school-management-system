@php
    $user = auth()->user();
    $items = array_filter([
        ['Dashboard', 'dashboard', 'bi-speedometer2', 'dashboard', true],
        ['Students', 'students.index', 'bi-people', 'students.*', $user->can('viewAny', App\Models\Student::class)],
        [$user->isAdmin() ? 'Classes' : 'My classes', 'sections.index', 'bi-easel', 'sections.*', true],
        ['Courses', 'courses.index', 'bi-journal-bookmark', 'courses.*', true],
        ['Academic terms', 'terms.index', 'bi-calendar3', 'terms.*', $user->can('create', App\Models\AcademicTerm::class)],
        ['Attendance', 'attendance.index', 'bi-clipboard-check', 'attendance.*', true],
        ['Users', 'users.index', 'bi-person-gear', 'users.*', $user->can('viewAny', App\Models\User::class)],
    ], fn ($item) => $item[4] && Route::has($item[1]));
@endphp

<nav aria-label="Main navigation">
    <ul class="nav nav-pills flex-column gap-1">
        @foreach ($items as [$label, $route, $icon, $pattern])
            <li class="nav-item">
                <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($pattern)])
                   @if (request()->routeIs($pattern)) aria-current="page" @endif>
                    <i class="bi {{ $icon }} me-2" aria-hidden="true"></i>{{ $label }}
                </a>
            </li>
        @endforeach

        @if ($user->student && Route::has('students.show'))
            <li class="nav-item">
                <a href="{{ route('students.show', $user->student) }}" @class(['nav-link', 'active' => request()->routeIs('students.show')])>
                    <i class="bi bi-person-vcard me-2" aria-hidden="true"></i>My record
                </a>
            </li>
        @endif

        @if ($user->isAdmin())
            <li class="nav-item mt-3 pt-3 border-top">
                <a href="{{ url('docs/api') }}" class="nav-link">
                    <i class="bi bi-braces me-2" aria-hidden="true"></i>API documentation
                </a>
            </li>
        @endif
    </ul>
</nav>
