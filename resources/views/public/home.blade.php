@extends('layouts.public', ['title' => 'Kanban - Organizza il lavoro', 'metaDescription' => 'Una board Kanban per progetti personali e team, con collaborazione in tempo reale e AI integrata nel progetto.'])

@section('content')
<section class="public-hero">
    <div class="public-hero-copy">
        <span class="public-eyebrow">Workspace personali e condivisi</span>
        <h1>Organizza il lavoro. Collabora in tempo reale. Usa l'AI quando serve.</h1>
        <p>Kanban riunisce task, scadenze, commenti e responsabilit&agrave; in board chiare, per lavorare da soli o insieme al team senza perdere il filo.</p>
        <div class="public-hero-actions">
            @auth<a class="btn btn-primary public-button-large" href="{{ route('dashboard') }}">Vai alla dashboard</a>@else<a class="btn btn-primary public-button-large" href="{{ route('register') }}">Inizia gratis</a><a class="btn public-button-large" href="#funzionalita">Scopri le funzionalit&agrave;</a>@endauth
        </div>
        <span class="public-microcopy">Nessuna carta richiesta</span>
    </div>
    <div class="public-product-preview" aria-label="Anteprima dimostrativa di una board Kanban">
        <div class="public-preview-bar"><span class="public-preview-dot"></span><strong>Progetto marketing</strong><span class="public-preview-presence">2 utenti online</span></div>
        <div class="public-preview-toolbar"><span>Board</span><span>Assegnate a me</span><span class="public-preview-ai">AI progetto</span></div>
        <div class="public-mini-board">
            <div class="public-mini-column"><div><strong>Da fare</strong><span>2</span></div><article><b>Definire il pubblico</b><small>Alta &middot; Nessuna scadenza</small></article><article><b>Raccogliere i contenuti</b><small>Media &middot; 2 assegnatari</small></article></div>
            <div class="public-mini-column"><div><strong>In corso</strong><span>1</span></div><article><b>Preparare la landing page</b><small>Alta &middot; In scadenza</small><i>Giulia sta modificando</i></article></div>
            <div class="public-mini-column"><div><strong>Revisione</strong><span>1</span></div><article><b>Controllo finale</b><small>Commenti &middot; Scadenza</small></article></div>
        </div>
        <p class="public-preview-caption">Anteprima dimostrativa &middot; le colonne sono configurabili</p>
    </div>
</section>

<div class="public-value-strip" aria-label="Funzionalit&agrave; principali"><span>Task</span><span>Realtime</span><span>Commenti</span><span>Scadenze</span><span>AI</span><span>Ruoli</span></div>

<section class="public-section" id="funzionalita">
    <div class="public-section-intro"><span class="public-eyebrow">Il progetto, senza dispersioni</span><h2>Tutto il progetto, in un'unica board</h2><p>Un flusso semplice per vedere cosa c'&egrave; da fare, chi se ne occupa e cosa richiede attenzione.</p></div>
    <div class="public-feature-grid">
        <article class="public-feature-card"><span class="public-feature-index">01</span><h3>Board Kanban flessibili</h3><p>Colonne dinamiche, drag &amp; drop e riordino delle task per adattare il progetto al tuo modo di lavorare.</p></article>
        <article class="public-feature-card"><span class="public-feature-index">02</span><h3>Task complete</h3><p>Titolo, descrizione, priorit&agrave;, categoria, scadenza e pi&ugrave; assegnatari in un unico posto.</p></article>
        <article class="public-feature-card"><span class="public-feature-index">03</span><h3>Commenti sulla task</h3><p>Una conversazione direttamente accanto al lavoro, senza dover ricostruire il contesto altrove.</p></article>
        <article class="public-feature-card"><span class="public-feature-index">04</span><h3>Scadenze visibili</h3><p>Individua le task in scadenza e quelle scadute, con reminder interni per gli assegnatari.</p></article>
        <article class="public-feature-card"><span class="public-feature-index">05</span><h3>Filtri utili</h3><p>Passa dalla vista completa alle task assegnate a te o alle scadenze che richiedono attenzione.</p></article>
        <article class="public-feature-card"><span class="public-feature-index">06</span><h3>Archiviazione</h3><p>Metti da parte gli elementi non pi&ugrave; attivi mantenendo il progetto ordinato.</p></article>
    </div>
</section>

<section class="public-section public-collaboration" id="collaborazione">
    <div class="public-split-copy"><span class="public-eyebrow">Collaborazione controllata</span><h2>Il team vede quello che succede, mentre succede</h2><p>Invita le persone giuste, assegna il livello di accesso corretto e lavora nella stessa board con aggiornamenti realtime.</p><a class="public-text-link" href="{{ route('pricing') }}">Scopri i piani per i team &rarr;</a></div>
    <div class="public-collaboration-card"><div class="public-role-list"><div><b>Owner</b><span>Controllo completo</span></div><div><b>Admin</b><span>Gestione operativa</span></div><div><b>Member</b><span>Collabora al progetto</span></div><div><b>Viewer</b><span>Sola lettura</span></div></div><div class="public-live-feed"><span class="public-live-label">Esempio realtime</span><p><i class="public-live-dot"></i> Giulia sta modificando questa task</p><p>Marco ha aggiornato la priorit&agrave;</p><p>2 utenti online</p></div></div>
</section>

<section class="public-section public-ai-section" id="ai">
    <div class="public-section-intro"><span class="public-eyebrow">AI integrata nel progetto</span><h2>AI dentro il progetto, non in una chat separata</h2><p>L'AI lavora sul contesto della board corrente e produce proposte che restano sotto il tuo controllo.</p></div>
    <div class="public-ai-layout"><div class="public-ai-features"><article><h3>Genera descrizione</h3><p>Trasforma un titolo sintetico in una descrizione operativa.</p></article><article><h3>Scomponi obiettivo</h3><p>Ottieni proposte di task e scegli quali creare.</p></article><article><h3>Riassumi progetto</h3><p>Ottieni una panoramica dello stato della board.</p></article><article><h3>Analizza progetto</h3><p>Evidenzia rischi, priorit&agrave; e punti da chiarire.</p></article></div><div class="public-ai-mock"><div class="public-ai-mock-head"><strong>AI progetto</strong><span>Media</span></div><p>Capacit&agrave; di ragionamento</p><div class="public-reasoning"><span>Bassa</span><b>Media</b><span>Alta</span></div><div class="public-ai-mock-actions"><span>Scomponi obiettivo</span><span>Riassumi progetto</span><span>Analizza progetto</span></div><small>Le proposte AI non modificano il progetto senza conferma.</small></div></div>
</section>

<section class="public-section public-notifications-section"><div class="public-notification-copy"><span class="public-eyebrow">Promemoria interni</span><h2>Le cose importanti non si perdono.</h2><p>Assegnazioni, nuovi commenti e task in scadenza restano visibili nelle notifiche interne del prodotto.</p></div><div class="public-notification-list"><div><span class="public-notification-icon">A</span><p><b>Task assegnata</b><small>Preparare la landing page</small></p><time>ora</time></div><div><span class="public-notification-icon">C</span><p><b>Nuovo commento</b><small>Una risposta sulla task</small></p><time>2 min</time></div><div><span class="public-notification-icon">!</span><p><b>Task in scadenza</b><small>Controlla le prossime scadenze</small></p><time>oggi</time></div></div></section>

<section class="public-section public-workflow"><div class="public-section-intro"><span class="public-eyebrow">Come funziona</span><h2>Dal primo spazio alla prossima consegna</h2></div><div class="public-steps"><article><span>01</span><h3>Crea il progetto</h3><p>Parti da una board personale o condividila con il team.</p></article><article><span>02</span><h3>Organizza e collabora</h3><p>Dividi il lavoro, assegna le task e aggiorna il progetto insieme.</p></article><article><span>03</span><h3>Usa l'AI quando serve</h3><p>Genera proposte, rivedile e applica solo ci&ograve; che ti &egrave; utile.</p></article></div></section>

<section class="public-section public-pricing-preview"><div class="public-section-intro public-section-intro-row"><div><span class="public-eyebrow">Piani chiari</span><h2>Parti dal piano giusto per te</h2></div><a class="public-text-link" href="{{ route('pricing') }}">Vedi tutti i piani &rarr;</a></div><div class="public-pricing-grid">@foreach ($plans as $plan) @include('public.partials.plan-card', ['plan' => $plan]) @endforeach</div></section>

<section class="public-section public-faq" id="faq"><div class="public-section-intro"><span class="public-eyebrow">Domande frequenti</span><h2>Le risposte che servono per iniziare</h2></div><div class="public-faq-list"><details><summary>Posso iniziare gratuitamente?</summary><p>S&igrave;. Il piano Free include un progetto personale e non richiede una carta per iniziare. Se ti servono pi&ugrave; progetti, collaborazione o AI, puoi passare a un piano superiore.</p></details><details><summary>Posso usare il Kanban anche da solo?</summary><p>S&igrave;. Free e Pro sono pensati anche per l'utilizzo personale: puoi organizzare task, priorit&agrave;, categorie e scadenze senza creare un team.</p></details><details><summary>Come funzionano i workspace condivisi?</summary><p>Nei piani che li includono puoi invitare altre persone e collaborare sulle stesse board. Un workspace utilizza uno slot condiviso quando contiene almeno un altro membro oltre al proprietario.</p></details><details><summary>Quali ruoli posso assegnare?</summary><p>Sono disponibili Owner, Admin, Member e Viewer. I ruoli distinguono chi gestisce il workspace, chi pu&ograve; modificarne i contenuti e chi pu&ograve; soltanto consultarli.</p></details><details><summary>L'AI modifica automaticamente le mie task?</summary><p>No. L'AI genera proposte sotto il tuo controllo: descrizioni, scomposizioni, riepiloghi e analisi. Il progetto non viene modificato senza una tua conferma.</p></details><details><summary>Come funzionano i crediti AI?</summary><p>Ogni piano che include l'AI dispone di crediti mensili. Il consumo dipende dalla richiesta e dal livello scelto; nei workspace condivisi il pool dipende dal piano del proprietario.</p></details><details><summary>Cosa succede se supero un limite dopo un downgrade?</summary><p>Nessun dato viene eliminato: progetti, workspace e membri esistenti restano disponibili. Vengono bloccate solo le nuove operazioni che aumenterebbero l'utilizzo oltre il limite.</p></details><details><summary>Come funzionano scadenze e notifiche?</summary><p>Le task possono avere una scadenza con data e ora. L'app evidenzia le attivit&agrave; in scadenza o scadute e pu&ograve; inviare notifiche interne e reminder secondo le funzionalit&agrave; disponibili.</p></details></div></section>

<section class="public-final-cta"><span class="public-eyebrow">Il prossimo progetto parte da qui</span><h2>Porta il prossimo progetto nella tua board.</h2><p class="public-final-cta-copy">Crea il tuo primo progetto e parti dal piano Free.</p>@auth<a class="btn btn-primary public-button-large" href="{{ route('dashboard') }}">Vai alla dashboard</a>@else<a class="btn btn-primary public-button-large" href="{{ route('register') }}">Inizia gratis</a>@endauth</section>
@endsection
