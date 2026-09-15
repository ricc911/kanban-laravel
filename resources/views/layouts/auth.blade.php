<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Account - Kanban' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="auth-public-body">
    <header class="auth-public-nav">
        <a class="public-brand" href="{{ route('public.home') }}" aria-label="Torna alla home di Kanban"><span class="brand-badge">KB</span><span class="public-brand-name">Kanban</span></a>
        <a class="auth-home-link" href="{{ route('public.home') }}">Torna alla home</a>
    </header>
    <main class="auth-public-main">@yield('content')</main>
</body>
</html>
