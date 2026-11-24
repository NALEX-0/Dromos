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
    <main class="shared-page-shell">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
