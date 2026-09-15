<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $metaDescription ?? 'Kanban per organizzare progetti, collaborare in tempo reale e usare l\'AI quando serve.' }}">
    <title>{{ $title ?? 'Kanban - Organizza il lavoro' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="public-body">
    <header class="public-nav-wrap">
        <nav class="public-nav" aria-label="Navigazione principale">
            <a class="public-brand" href="{{ route('public.home') }}" aria-label="Kanban, home"><span class="brand-badge">KB</span><span class="public-brand-name">Kanban</span></a>
            <details class="public-mobile-menu">
                <summary aria-label="Apri menu"><span></span><span></span><span></span></summary>
                <div class="public-mobile-panel">@include('public.partials.nav-links')</div>
            </details>
            <div class="public-nav-links">@include('public.partials.nav-links')</div>
        </nav>
    </header>
    <main>@yield('content')</main>
    <footer class="public-footer">
        <div class="public-footer-inner">
            <div><a class="public-brand" href="{{ route('public.home') }}"><span class="brand-badge">KB</span><span class="public-brand-name">Kanban</span></a><p>Progetti chiari, collaborazione concreta.</p></div>
            <div class="public-footer-links"><div><strong>Prodotto</strong><a href="{{ route('public.home') }}#funzionalita">Funzionalità</a><a href="{{ route('public.home') }}#ai">AI</a><a href="{{ route('pricing') }}">Prezzi</a></div><div><strong>Account</strong>@auth<a href="{{ route('dashboard') }}">Dashboard</a><a href="{{ route('plans') }}">Piani</a>@else<a href="{{ route('login') }}">Accedi</a><a href="{{ route('register') }}">Registrati</a>@endauth</div></div>
        </div>
        <p class="public-footer-copy">&copy; {{ date('Y') }} Kanban</p>
    </footer>
</body>
</html>
