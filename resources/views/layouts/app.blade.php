<!doctype html>
<html lang="el" class="{{ request()->routeIs('route-plans.index') ? 'route-history-document' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Dromos')</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    @vite([
        'resources/css/app.css',
        'resources/css/workspace.css',
        'resources/js/app.js',
    ])
    <script>
        try {
            if (localStorage.getItem('dromos.sidebar-collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-is-collapsed');
            }
        } catch {
            // Keep the sidebar expanded when browser storage is unavailable.
        }
    </script>
</head>
<body class="{{ collect([
    auth()->user()?->isAdmin() ? 'admin-session' : null,
    request()->routeIs('route-plans.index') ? 'route-history-page' : null,
    request()->routeIs('route-plans.create', 'optimized-route-plans.create', 'ordered-route-plans.create') ? 'route-create-page' : null,
])->filter()->implode(' ') }}">
    @php
        $activeSection = request()->routeIs('admin.*')
            ? 'admin'
            : (request()->routeIs('colored-routes.*') ? 'colored-routes' : (request()->routeIs('route-plans.index', 'route-plans.show', 'route-plans.shared') ? 'routes' : 'new-route'));
    @endphp

    @auth
        <div class="app-layout">
            <aside class="app-sidebar">
                <div class="sidebar-header">
                    <a class="brand" href="{{ route('route-plans.create') }}">
                        <span class="brand-mark" aria-hidden="true">
                            <x-icons.car />
                        </span>
                        <span class="brand-label">Dromos</span>
                    </a>
                    <button type="button" class="sidebar-toggle" aria-label="Σύμπτυξη πλαϊνής μπάρας" aria-expanded="true" title="Σύμπτυξη πλαϊνής μπάρας">‹</button>
                </div>

                <nav class="app-navigation" aria-label="Κύρια πλοήγηση">
                    <a
                        href="{{ route('route-plans.create') }}"
                        class="app-navigation-link {{ $activeSection === 'new-route' ? 'is-active' : '' }}"
                        @if ($activeSection === 'new-route') aria-current="page" @endif
                    >
                        <span class="navigation-icon" aria-hidden="true">＋</span>
                        <span class="navigation-label">Νέα διαδρομή</span>
                    </a>
                    <a
                        href="{{ route('route-plans.index') }}"
                        class="app-navigation-link {{ $activeSection === 'routes' ? 'is-active' : '' }}"
                        @if ($activeSection === 'routes') aria-current="page" @endif
                    >
                        <span class="navigation-icon" aria-hidden="true"><x-icons.route-history /></span>
                        <span class="navigation-label">{{ auth()->user()->isAdmin() ? 'Όλες οι διαδρομές' : 'Οι διαδρομές μου' }}</span>
                    </a>
                    @if (auth()->user()->isAdmin())
                        <a
                            href="{{ route('colored-routes.index') }}"
                            class="app-navigation-link {{ $activeSection === 'colored-routes' ? 'is-active' : '' }}"
                            @if ($activeSection === 'colored-routes') aria-current="page" @endif
                        >
                            <span class="navigation-icon" aria-hidden="true"><i class="fas fa-palette"></i></span>
                            <span class="navigation-label">Χρώματα διαδρομών</span>
                        </a>
                        <a
                            href="{{ route('admin.dashboard') }}"
                            class="app-navigation-link {{ $activeSection === 'admin' ? 'is-active' : '' }}"
                            @if ($activeSection === 'admin') aria-current="page" @endif
                        >
                            <span class="navigation-icon" aria-hidden="true">◆</span>
                            <span class="navigation-label">Διαχείριση</span>
                        </a>
                    @endif
                </nav>

                <div class="sidebar-account">
                    <div>
                        <strong>{{ auth()->user()->name }}</strong>
                        <small>{{ auth()->user()->email }}</small>
                        @if (auth()->user()->isAdmin())
                            <span class="admin-account-badge">ADMIN</span>
                        @endif
                    </div>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Αποσύνδεση</button>
                    </form>
                </div>
            </aside>

            <main class="app-content">
                @yield('content')
            </main>
        </div>
    @else
        <main class="shell">
            <nav class="nav" aria-label="Κύρια πλοήγηση">
                <a class="brand" href="{{ route('login') }}">
                    <span class="brand-mark" aria-hidden="true">
                        <x-icons.car />
                    </span>
                    Dromos
                </a>

                <div class="guest-nav">
                    {{-- <a href="{{ route('login') }}">Σύνδεση</a> --}}
                    {{-- <a href="{{ route('register') }}" class="guest-register">Εγγραφή</a> --}}
                </div>
            </nav>

            @yield('content')
        </main>
    @endauth

    @stack('scripts')
    @auth
        <script>
            const sidebarToggle = document.querySelector('.sidebar-toggle');

            function updateSidebarToggle() {
                const collapsed = document.documentElement.classList.contains('sidebar-is-collapsed');
                sidebarToggle.setAttribute('aria-expanded', String(! collapsed));
                sidebarToggle.setAttribute('aria-label', collapsed ? 'Ανάπτυξη πλαϊνής μπάρας' : 'Σύμπτυξη πλαϊνής μπάρας');
                sidebarToggle.title = collapsed ? 'Ανάπτυξη πλαϊνής μπάρας' : 'Σύμπτυξη πλαϊνής μπάρας';
            }

            sidebarToggle.addEventListener('click', () => {
                document.documentElement.classList.toggle('sidebar-is-collapsed');

                try {
                    localStorage.setItem('dromos.sidebar-collapsed', String(document.documentElement.classList.contains('sidebar-is-collapsed')));
                } catch {
                    // The toggle still works for this page when browser storage is unavailable.
                }

                updateSidebarToggle();
            });

            updateSidebarToggle();
        </script>
    @endauth
</body>
</html>
