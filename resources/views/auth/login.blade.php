@extends('layouts.app')

@section('title', 'Σύνδεση — Dromos')

@section('content')
    <section class="auth-page">
        <div class="auth-card">
            <span class="eyebrow">Καλώς ήρθατε</span>
            <h1>Σύνδεση</h1>
            <p>Συνδεθείτε για να σχεδιάσετε και να διαχειριστείτε τις διαδρομές σας.</p>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('login') }}">
                @csrf

                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>

                <label for="password">Κωδικός πρόσβασης</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>

                <label class="check auth-remember">
                    <input type="checkbox" name="remember" value="1">
                    Να παραμείνω συνδεδεμένος
                </label>

                <button class="button button-wide">Σύνδεση <span>→</span></button>
            </form>

            <p class="auth-switch">Δεν έχετε λογαριασμό; <a href="{{ route('register') }}">Δημιουργία λογαριασμού</a></p>
        </div>
    </section>
@endsection
