<a href="{{ route('public.home') }}#funzionalita">Funzionalità</a>
<a href="{{ route('public.home') }}#ai">AI</a>
<a href="{{ route('public.home') }}#collaborazione">Collaborazione</a>
<a href="{{ route('pricing') }}">Prezzi</a>
<a href="{{ route('public.home') }}#faq">FAQ</a>
<span class="public-nav-actions">
    @auth
        <a class="btn btn-primary" href="{{ route('dashboard') }}">Vai alla dashboard</a>
    @else
        <a href="{{ route('login') }}">Accedi</a>
        <a class="btn btn-primary" href="{{ route('register') }}">Inizia gratis</a>
    @endauth
</span>
