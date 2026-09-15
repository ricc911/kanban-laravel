@extends('layouts.auth', ['title' => 'Accedi - Kanban'])

@section('content')
<div class="auth-public-layout">
    <section class="auth-form-card" aria-labelledby="login-title">
        <span class="public-eyebrow">Account</span>
        <h1 id="login-title">Accedi</h1>
        <p class="auth-form-intro">Continua verso i tuoi workspace.</p>
        @if ($errors->any())
            <div class="auth-error-summary" role="alert"><strong>Controlla i dati inseriti.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form class="auth-public-form" method="POST" action="{{ url('/login') }}">
            @csrf
            <div class="auth-field"><label for="login-email">Email</label><input id="login-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror>@error('email')<p class="auth-field-error" id="login-email-error">{{ $message }}</p>@enderror</div>
            <div class="auth-field"><label for="login-password">Password</label><input id="login-password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror>@error('password')<p class="auth-field-error" id="login-password-error">{{ $message }}</p>@enderror</div>
            <label class="auth-checkbox"><input type="checkbox" name="remember" value="1"> <span>Ricordami</span></label>
            <button class="btn btn-primary auth-submit" type="submit">Accedi</button>
        </form>
        <p class="auth-switch">Non hai ancora un account? <a href="{{ route('register') }}">Crea account</a></p>
    </section>
    <aside class="auth-value-panel"><span class="public-eyebrow">Il tuo lavoro, dove l'hai lasciato</span><h2>Una board chiara per continuare a lavorare.</h2><ul><li>Board Kanban flessibili</li><li>Collaborazione realtime</li><li>AI integrata nel progetto</li></ul></aside>
</div>
@endsection
