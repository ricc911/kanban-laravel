<article class="public-plan-card {{ $plan->slug === 'team' ? 'is-recommended' : '' }}">
    <div class="public-plan-card-head">
        <div><h3>{{ $plan->name }}</h3><p class="public-plan-price">@if ($plan->price_cents === 0)
            &euro;0
        @else
            &euro;{{ number_format($plan->price_cents / 100, 2, ',', '.') }} / mese
        @endif</p></div>
        @if ($plan->slug === 'team')<span class="public-plan-badge">Consigliato</span>@endif
    </div>
    <ul class="public-plan-features">
        <li>{{ $plan->max_projects === null ? 'Progetti illimitati' : $plan->max_projects.' '.($plan->max_projects === 1 ? 'progetto' : 'progetti') }}</li>
        <li>{{ $plan->max_shared_workspaces === 0 ? 'Workspace condivisi non inclusi' : $plan->max_shared_workspaces.' workspace condivisi' }}</li>
        <li>Fino a {{ $plan->max_members_per_workspace }} {{ $plan->max_members_per_workspace === 1 ? 'membro' : 'membri' }} totali per workspace, proprietario incluso</li>
        <li>{{ $plan->ai_enabled ? number_format($plan->ai_monthly_credits, 0, ',', '.').' crediti AI / mese' : 'AI non inclusa' }}</li>
    </ul>
    @auth
        <a class="btn {{ $plan->slug === 'free' ? 'btn-primary' : '' }} public-plan-cta" href="{{ route('plans') }}">Gestisci il tuo piano</a>
    @else
        @if ($plan->slug === 'free')
            <a class="btn btn-primary public-plan-cta" href="{{ route('register') }}">Inizia gratis</a>
        @else
            <button class="btn public-plan-cta is-disabled" type="button" disabled>Disponibile a breve</button>
        @endif
    @endauth
</article>
