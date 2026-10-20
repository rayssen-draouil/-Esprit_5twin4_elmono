@extends('layouts.front')

@section('content')
<section class="hero reveal">
    <div class="hero-copy">
        <span class="eyebrow"><span class="pulse-dot"></span> AQUASECURE · LIVE NETWORK</span>
        <h1>L'eau sous surveillance.<br><em>La ville en confiance.</em></h1>
        <p>La plateforme intelligente qui transforme chaque signal en décision, pour des réseaux d'eau plus résilients et des villes plus sereines.</p>
        <div class="actions">
            <x-ui.button :href="route('front.incidents.create')">Signaler un incident <span>↗</span></x-ui.button>
            <x-ui.button :href="route('front.infrastructures.index')" variant="ghost">Explorer le réseau <span>→</span></x-ui.button>
        </div>
    </div>
    <div class="hero-visual" aria-label="Réseau AquaSecure actif">
        <div class="ring ring-one"></div><div class="ring ring-two"></div>
        <div class="water-orb">◒<small>128 sites<br>surveillés</small></div>
        <div class="float-card float-card-main"><span class="eyebrow">NETWORK STATUS</span><strong><span class="pulse"></span> OPERATIONAL</strong><small>128 sites surveillés</small></div>
        <div class="float-card float-card-health"><span class="eyebrow">WATER QUALITY</span><strong>98,7%</strong><small>Excellent</small></div>
        <div class="float-card float-card-alert"><span class="eyebrow">ACTIVE ALERTS</span><strong>12</strong><small>3 critiques</small></div>
    </div>
</section>

<section class="stats-strip" aria-label="Statistiques AquaSecure">
    @foreach($stats as $label => $value)
        <div class="counter-card"><strong data-counter="{{ str_replace(' ', '', $value) }}">0</strong><span>{{ $label }}</span></div>
    @endforeach
</section>

<section class="section">
    <div class="section-head"><div><span class="eyebrow">SURVEILLANCE CONTINUE</span><h2>État du réseau<br>en temps réel.</h2></div><span class="live-indicator"><i></i> Données actualisées il y a 2 min</span></div>
    <div class="network-grid">
        @foreach($networkStatus as $item)
            <article class="network-card reveal"><span class="network-icon">{{ $item['icon'] }}</span><span class="network-label">{{ $item['label'] }}</span><strong>{{ $item['value'] }}<small>{{ $item['unit'] }}</small></strong><div><x-ui.badge :variant="$item['tone']">{{ $item['status'] }}</x-ui.badge><small class="network-change">{{ $item['change'] }}</small></div></article>
        @endforeach
    </div>
</section>

<section class="section tinted">
    <div class="section-head"><div><span class="eyebrow">ALERTES CITOYENNES</span><h2>Incidents récents</h2></div><a class="text-link" href="{{ route('front.incidents.index') }}">Voir tous les incidents ↗</a></div>
    <div class="filter-tabs" role="tablist"><button class="active" data-filter="all">Tous</button><button data-filter="Fuite">Fuites</button><button data-filter="Contamination">Contamination</button><button data-filter="Sécheresse">Sécheresse</button><button data-filter="Coupure">Coupure</button></div>
    <div class="incident-grid">
        @foreach($recentIncidents as $incident)
            <article class="incident-card reveal" data-incident-type="{{ ['Coupure', 'Contamination', 'Fuite', 'Sécheresse'][$loop->index % 4] }}"><span class="incident-mark">!</span><div><span class="eyebrow">{{ $incident['priority'] }}</span><h3>{{ $incident['title'] }}</h3><p>{{ $incident['site'] }} · {{ $incident['date'] }}</p><x-ui.badge :variant="$incident['status'] === 'Ouvert' ? 'danger' : 'warning'">{{ $incident['status'] }}</x-ui.badge></div></article>
        @endforeach
    </div>
</section>

<section class="section"><div class="section-head"><div><span class="eyebrow">INFRASTRUCTURES</span><h2>Une vigilance à chaque point<br>du réseau.</h2></div><a class="text-link" href="{{ route('front.infrastructures.index') }}">Explorer le réseau ↗</a></div><div class="infrastructure-grid">@foreach($infrastructures as $item)<article class="infrastructure-card reveal"><span class="infrastructure-icon">⌁</span><h3>{{ $item['name'] }}</h3><p>{{ $item['type'] }} · {{ $item['region'] }}</p><div class="health-row"><span>Indice de santé</span><strong>{{ $item['health'] }}</strong></div><div class="progress"><i style="width:{{ $item['health'] }}"></i></div><small>Dernière inspection : aujourd'hui</small></article>@endforeach</div></section>

<section class="section tinted"><div class="section-head"><div><span class="eyebrow">PROJETS À LA UNE</span><h2>Des actions concrètes,<br>des résultats mesurables.</h2></div><a class="text-link" href="{{ route('front.projects.index') }}">Tous les projets ↗</a></div><div class="project-grid">@foreach($featuredProjects as $project)<article class="project-card reveal"><div class="project-art art-{{ $loop->index }}"><span>{{ $project['type'] }}</span></div><div class="card-body"><h3>{{ $project['name'] }}</h3><p>{{ $project['location'] }} · {{ $project['status'] }}</p><div class="progress"><i style="width:{{ $project['progress'] }}%"></i></div><small>{{ $project['progress'] }}% du projet réalisé</small></div></article>@endforeach</div></section>

<section class="section network-map-section"><div class="map-copy"><span class="eyebrow">VUE TERRITORIALE</span><h2>La carte du réseau.</h2><p>Visualisez les points surveillés et l'état global de nos infrastructures.</p><a class="text-link" href="{{ route('front.infrastructures.index') }}">Consulter les infrastructures ↗</a></div><div class="network-map" aria-label="Représentation du réseau"><span class="map-node node-a"></span><span class="map-node node-b"></span><span class="map-node node-c"></span><span class="map-node node-d"></span><span class="map-line line-a"></span><span class="map-line line-b"></span><span class="map-line line-c"></span><span class="map-label">Réseau opérationnel · 99.2%</span></div></section>

<section class="cta-section"><div><span class="eyebrow">AGIR ENSEMBLE</span><h2>Un problème sur le réseau ?</h2><p>Chaque signalement contribue à préserver une eau sûre et accessible.</p></div><x-ui.button :href="route('front.incidents.create')">Signaler un incident <span>↗</span></x-ui.button></section>
@endsection
