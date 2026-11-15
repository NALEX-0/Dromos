<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Dromos')</title>

    @vite([
        'resources/css/app.css',
        'resources/css/workspace.css',
        'resources/js/app.js',
    ])
</head>
<body>
    @php
        $activeSection = request()->routeIs('map-style-demo')
            ? 'map-style-demo'
            : (request()->routeIs('route-plans.index', 'route-plans.show', 'route-plans.shared') ? 'routes' : 'new-route');
    @endphp

    @auth
        <div class="app-layout">
            <aside class="app-sidebar">
                <a class="brand" href="{{ route('route-plans.index') }}">
                    <span class="brand-mark" aria-hidden="true">
                        <x-icons.car />
                    </span>
                    Dromos
                </a>

                <nav class="app-navigation" aria-label="Κύρια πλοήγηση">
                    <a
                        href="{{ route('route-plans.create') }}"
                        class="app-navigation-link {{ $activeSection === 'new-route' ? 'is-active' : '' }}"
                        @if ($activeSection === 'new-route') aria-current="page" @endif
                    >
                        <span aria-hidden="true">＋</span>
                        Νέα διαδρομή
                    </a>
                    <a
                        href="{{ route('route-plans.index') }}"
                        class="app-navigation-link {{ $activeSection === 'routes' ? 'is-active' : '' }}"
                        @if ($activeSection === 'routes') aria-current="page" @endif
                    >
                        <span aria-hidden="true">≡</span>
                        Οι διαδρομές μου
                    </a>
                    <a
                        href="{{ route('map-style-demo') }}"
                        class="app-navigation-link {{ $activeSection === 'map-style-demo' ? 'is-active' : '' }}"
                        @if ($activeSection === 'map-style-demo') aria-current="page" @endif
                    >
                        <span aria-hidden="true">◫</span>
                        Χρώματα χάρτη (δ)
                    </a>
                </nav>

                <div class="sidebar-account">
                    <div>
                        <strong>{{ auth()->user()->name }}</strong>
                        <small>{{ auth()->user()->email }}</small>
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
                    <a href="{{ route('login') }}">Σύνδεση</a>
                    <a href="{{ route('register') }}" class="guest-register">Εγγραφή</a>
                </div>
            </nav>

            @yield('content')
        </main>
    @endauth

    @stack('scripts')
</body>
</html>
