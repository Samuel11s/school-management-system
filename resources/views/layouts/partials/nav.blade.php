@php
    $user = auth()->user();

    // [label, route, icon, active pattern, visible]
    $sections = [
        'Overview' => [
            ['Dashboard', 'dashboard', 'bi-grid-1x2', 'dashboard', true],
        ],
        'Academics' => [
            ['Students', 'students.index', 'bi-people', 'students.*', $user->can('viewAny', App\Models\Student::class)],
            [$user->isAdmin() ? 'Classes' : 'My classes', 'sections.index', 'bi-easel2', 'sections.*', true],
            ['Courses', 'courses.index', 'bi-journal-bookmark', 'courses.*', true],
            ['Academic terms', 'terms.index', 'bi-calendar3', 'terms.*', $user->can('create', App\Models\AcademicTerm::class)],
            ['Attendance', 'attendance.index', 'bi-clipboard2-check', 'attendance.*', true],
        ],
        'Administration' => [
            ['Users', 'users.index', 'bi-person-gear', 'users.*', $user->can('viewAny', App\Models\User::class)],
        ],
    ];

    foreach ($sections as $heading => $items) {
        $sections[$heading] = array_filter($items, fn ($item) => $item[4] && Route::has($item[1]));
    }

    // A student viewing their own profile is under "My record", not "Students".
    $routeStudent = request()->route('student');
    $ownRecord = $user->student && $routeStudent instanceof App\Models\Student && $routeStudent->is($user->student);
@endphp

<nav aria-label="Main navigation" class="sidebar-nav">
    @foreach ($sections as $heading => $items)
        @if (count($items))
            <h2 class="nav-section-title">{{ $heading }}</h2>
            <ul class="nav flex-column gap-1">
                @foreach ($items as [$label, $route, $icon, $pattern])
                    @php($active = request()->routeIs($pattern) && ! ($pattern === 'students.*' && $ownRecord))
                    <li class="nav-item">
                        <a href="{{ route($route) }}" @class(['nav-link', 'active' => $active])
                           @if ($active) aria-current="page" @endif>
                            <i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}
                        </a>
                    </li>
                @endforeach

                @if ($heading === 'Academics' && $user->student && Route::has('students.show'))
                    <li class="nav-item">
                        <a href="{{ route('students.show', $user->student) }}" @class(['nav-link', 'active' => $ownRecord])
                           @if ($ownRecord) aria-current="page" @endif>
                            <i class="bi bi-person-vcard" aria-hidden="true"></i>My record
                        </a>
                    </li>
                @endif
            </ul>
        @endif
    @endforeach

    @if ($user->isAdmin())
        <h2 class="nav-section-title">Developers</h2>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a href="{{ url('docs/api') }}" class="nav-link">
                    <i class="bi bi-braces" aria-hidden="true"></i>API documentation
                </a>
            </li>
        </ul>
    @endif
</nav>
