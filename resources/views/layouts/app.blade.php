<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Dromos — Σχεδιασμός διαδρομής')</title>

    @vite([
        'resources/css/app.css',
        'resources/css/workspace.css',
        'resources/js/app.js',
    ])
</head>
<body>
    @php
        $activePlanner = isset($plan) && $plan
            ? $plan->route_mode
            : (request()->routeIs('ordered-route-plans.*') ? 'ordered' : 'optimized');
    @endphp
    <main class="shell">
        <nav class="nav" aria-label="Κύρια πλοήγηση">
            <a class="brand" href="{{ auth()->check() ? route('route-plans.create') : route('login') }}">
                <span class="brand-mark" aria-hidden="true">
                    <x-icons.car />
                </span>
                Dromos
            </a>

            @auth
            <div class="planner-nav">
                <a
                    class="planner-link {{ $activePlanner === 'optimized' ? 'is-active' : '' }}"
                    href="{{ route('route-plans.create') }}"
                    @if ($activePlanner === 'optimized') aria-current="page" @endif
                >Βελτιστοποιημένη</a>
                <a
                    class="planner-link {{ $activePlanner === 'ordered' ? 'is-active' : '' }}"
                    href="{{ route('ordered-route-plans.create') }}"
                    @if ($activePlanner === 'ordered') aria-current="page" @endif
                >Σειριακή</a>
            </div>

            <div class="nav-account">
                <span class="tag">{{ auth()->user()->name }}</span>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Αποσύνδεση</button>
                </form>
            </div>
            @else
                <div class="guest-nav">
                    <a href="{{ route('login') }}">Σύνδεση</a>
                    <a href="{{ route('register') }}" class="guest-register">Εγγραφή</a>
                </div>
            @endauth
        </nav>

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
