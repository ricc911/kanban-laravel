<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Piani e utilizzo - Kanban</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body>
    <header class="topbar plans-topbar">
        <a class="brand plans-brand" href="{{ route('dashboard') }}" aria-label="Torna ai progetti">
            <span class="brand-badge">KB</span>
            <span><span class="brand-title">Kanban</span><span class="brand-subtitle">Working boards</span></span>
        </a>
        <a class="btn with-icon" href="{{ route('dashboard') }}">Torna ai progetti</a>
    </header>
    <main class="page plans-page">
        <div class="page-head plans-page-head">
            <span class="section-label">Account</span>
            <h1>Piani e utilizzo</h1>
            <p class="page-note">Controlla il piano attivo e l'utilizzo dell'account.</p>
        </div>
        <section class="plans-current panel" aria-labelledby="current-plan-title">
            <div><span class="section-label">Il tuo piano</span><h2 id="current-plan-title">{{ $currentPlan->name }}</h2><p class="plans-price">{{ $currentPrice }}</p></div>
            <span class="plan-badge">Piano attuale</span>
        </section>
        <section class="plans-usage" aria-labelledby="usage-title">
            <div class="plans-section-head"><div><span class="section-label">Panoramica</span><h2 id="usage-title">Utilizzo del piano</h2></div><p class="plans-note">I dati mostrano l'utilizzo effettivo dell'account.</p></div>
            <div class="usage-grid">
                @foreach ([['label' => 'Progetti', 'used' => $usage['projects']['used'], 'limit' => $usage['projects']['limit']], ['label' => 'Workspace condivisi', 'used' => $usage['shared_workspaces']['used'], 'limit' => $usage['shared_workspaces']['limit']]] as $item)
                    @php $overLimit = $item['limit'] !== null && $item['used'] > $item['limit']; $percent = $item['limit'] ? min(100, ($item['used'] / $item['limit']) * 100) : 0; @endphp
                    <article class="usage-card {{ $overLimit ? 'is-over-limit' : '' }}">
                        <div class="usage-card-head"><h3>{{ $item['label'] }}</h3><strong>{{ $item['limit'] === null ? $item['used'].' utilizzati' : $item['used'].' / '.$item['limit'] }}</strong></div>
                        @if ($item['limit'] === null)<p class="usage-unlimited">Illimitati</p>@else<div class="usage-progress" role="progressbar" aria-label="{{ $item['label'] }}" aria-valuenow="{{ $item['used'] }}" aria-valuemin="0" aria-valuemax="{{ $item['limit'] }}"><span style="width: {{ $percent }}%"></span></div>@endif
                        @if ($overLimit)<p class="usage-warning">Sei oltre il limite del piano. I dati esistenti restano disponibili, ma non puoi aggiungere nuovi elementi finché non rientri nel limite.</p>@endif
                    </article>
                @endforeach
                <article class="usage-card {{ ! $currentPlan->ai_enabled ? 'is-unavailable' : '' }}">
                    <div class="usage-card-head"><h3>Crediti AI</h3><strong>{{ $currentPlan->ai_enabled ? number_format($usage['ai']->remaining_credits, 0, ',', '.') : '—' }}</strong></div>
                    @if ($currentPlan->ai_enabled)
                        @php $aiPercent = $usage['ai']->monthly_credits ? min(100, ($usage['ai']->used_credits / $usage['ai']->monthly_credits) * 100) : 0; @endphp
                        <div class="usage-progress" role="progressbar" aria-label="Crediti AI utilizzati" aria-valuenow="{{ $usage['ai']->used_credits }}" aria-valuemin="0" aria-valuemax="{{ $usage['ai']->monthly_credits }}"><span style="width: {{ $aiPercent }}%"></span></div>
                        <p class="usage-caption">{{ number_format($usage['ai']->remaining_credits, 0, ',', '.') }} disponibili su {{ number_format($usage['ai']->monthly_credits, 0, ',', '.') }}</p>
                    @else<p class="usage-unlimited">AI non disponibile nel piano</p>@endif
                </article>
            </div>
            <p class="plans-note">Un workspace utilizza uno slot condiviso quando include almeno un altro membro oltre al proprietario. Nei workspace condivisi, limiti e crediti AI dipendono dal piano del proprietario.</p>
        </section>
        <section class="plans-catalog" aria-labelledby="catalog-title">
            <div class="plans-section-head"><div><span class="section-label">Catalogo</span><h2 id="catalog-title">Piani disponibili</h2></div><p class="plans-note">Il cambio piano sarà disponibile a breve.</p></div>
            <div class="plans-grid">
                @foreach ($catalog as $plan)
                    <article class="plan-card {{ $plan->is($currentPlan) ? 'is-current' : '' }}" data-plan-slug="{{ $plan->slug }}">
                        <div class="plan-card-head"><div><h3>{{ $plan->name }}</h3><p class="plans-price">{{ $plan->price_cents === 0 ? '€0' : '€'.number_format($plan->price_cents / 100, 2, ',', '.').' / mese' }}</p></div>@if ($plan->is($currentPlan))<span class="plan-badge">Piano attuale</span>@endif</div>
                        <ul class="plan-features">
                            <li>{{ $plan->max_projects === null ? 'Progetti illimitati' : $plan->max_projects.' '.($plan->max_projects === 1 ? 'progetto' : 'progetti') }}</li>
                            <li>{{ $plan->max_shared_workspaces === null ? 'Workspace condivisi illimitati' : ($plan->max_shared_workspaces === 0 ? 'Workspace condivisi non inclusi' : $plan->max_shared_workspaces.' workspace condivisi') }}</li>
                            <li>{{ $plan->max_members_per_workspace === null ? 'Membri illimitati per workspace' : 'Fino a '.$plan->max_members_per_workspace.' membri totali per workspace, proprietario incluso' }}</li>
                            <li>{{ $plan->ai_enabled ? number_format($plan->ai_monthly_credits, 0, ',', '.').' crediti AI / mese' : 'AI non inclusa' }}</li>
                        </ul>
                        <button class="btn {{ $plan->is($currentPlan) ? 'btn-primary' : '' }}" type="button" disabled>{{ $plan->is($currentPlan) ? 'Piano attuale' : 'Disponibile a breve' }}</button>
                    </article>
                @endforeach
            </div>
            <p class="plans-note">I limiti membri includono il proprietario del workspace.</p>
        </section>
    </main>
</body>
</html>
