@extends('layouts.auth', ['title' => 'Crea account - Kanban'])

@section('content')
<div class="auth-public-layout auth-register-layout">
    <section class="auth-form-card" aria-labelledby="register-title">
        <span class="public-eyebrow">Inizia da qui</span>
        <h1 id="register-title">Crea il tuo account</h1>
        <p class="auth-form-intro">Inizia con il tuo workspace personale.</p>
        @if ($errors->any())
            <div class="auth-error-summary" role="alert"><strong>Controlla i dati inseriti.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form class="auth-public-form" method="POST" action="{{ url('/register') }}">
            @csrf
            <div class="auth-field-grid">
                <div class="auth-field"><label for="register-name">Nome</label><input id="register-name" name="name" type="text" value="{{ old('name') }}" autocomplete="given-name" required @error('name') aria-invalid="true" aria-describedby="register-name-error" @enderror>@error('name')<p class="auth-field-error" id="register-name-error">{{ $message }}</p>@enderror</div>
                <div class="auth-field"><label for="register-last-name">Cognome</label><input id="register-last-name" name="last_name" type="text" value="{{ old('last_name') }}" autocomplete="family-name" required @error('last_name') aria-invalid="true" aria-describedby="register-last-name-error" @enderror>@error('last_name')<p class="auth-field-error" id="register-last-name-error">{{ $message }}</p>@enderror</div>
            </div>
            <div class="auth-field"><label for="register-username">Username</label><input id="register-username" name="username" type="text" value="{{ old('username') }}" minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]+" autocomplete="username" required aria-describedby="register-username-help @error('username')register-username-error @enderror" @error('username') aria-invalid="true" @enderror><p class="auth-field-help" id="register-username-help">3&ndash;30 caratteri: lettere, numeri, _ e -</p>@error('username')<p class="auth-field-error" id="register-username-error">{{ $message }}</p>@enderror</div>
            <div class="auth-field"><label for="register-email">Email</label><input id="register-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="register-email-error" @enderror>@error('email')<p class="auth-field-error" id="register-email-error">{{ $message }}</p>@enderror</div>
            <div class="auth-field-grid">
                <div class="auth-field"><label for="register-password">Password</label><input id="register-password" name="password" type="password" autocomplete="new-password" minlength="8" required @error('password') aria-invalid="true" aria-describedby="register-password-error" @enderror>@error('password')<p class="auth-field-error" id="register-password-error">{{ $message }}</p>@enderror</div>
                <div class="auth-field"><label for="register-password-confirmation">Conferma password</label><input id="register-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>
            </div>
            <button class="btn btn-primary auth-submit" type="submit">Crea account</button>
        </form>
        <p class="auth-switch">Hai gi&agrave; un account? <a href="{{ route('login') }}">Accedi</a></p>
    </section>
    <aside class="auth-value-panel"><span class="public-eyebrow">Parti gratis</span><h2>Il tuo prossimo progetto comincia da una board.</h2><ul><li>1 progetto personale</li><li>Workspace personale</li><li>Nessuna carta richiesta</li></ul></aside>
</div>
@endsection
