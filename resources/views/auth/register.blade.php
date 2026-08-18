@extends('layouts.app')

@section('title', 'Δημιουργία λογαριασμού — Dromos')

@section('content')
    <section class="auth-page">
        <div class="auth-card">
            <span class="eyebrow">Νέος λογαριασμός</span>
            <h1>Εγγραφή</h1>
            <p>Δημιουργήστε λογαριασμό για να αποθηκεύετε τις δικές σας διαδρομές.</p>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('register') }}">
                @csrf

                <label for="name">Όνομα</label>
                <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>

                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>

                <label for="password">Κωδικός πρόσβασης</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required>

                <label for="password_confirmation">Επιβεβαίωση κωδικού</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>

                <button class="button button-wide">Δημιουργία λογαριασμού <span>→</span></button>
            </form>

            <p class="auth-switch">Έχετε ήδη λογαριασμό; <a href="{{ route('login') }}">Σύνδεση</a></p>
        </div>
    </section>
@endsection
