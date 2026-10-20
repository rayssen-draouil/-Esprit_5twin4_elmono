@extends('layouts.front')

@section('title', 'L’eau sous surveillance')
@section('meta_description', 'AquaSecure surveille le réseau d’eau en continu : zones, alertes en temps réel, signalements citoyens et conseils pour agir.')

@php
    $critical = $criticalAlerts ?? [];
    $liveStats = $publicStats ?? ['active_alerts' => 0, 'zones' => 0, 'resolved_month' => 0, 'critical' => 0];
@endphp

@section('content')
    @if(! empty($critical))
        <section class="aq-critical-banner" role="alert" aria-label="Alertes critiques en cours">
            <div class="aq-critical-banner__intro">
                <span class="aq-critical-banner__dot" aria-hidden="true"></span>
                <p>
                    <strong>{{ count($critical) }} alerte(s) critique(s) en cours</strong>
                    <small>Consultez les consignes avant tout usage de l’eau.</small>
                </p>
            </div>
            <div class="aq-critical-banner__list">
                @foreach($critical as $alert)
                    <a href="{{ $alert['url'] }}">
                        {{ $alert['type'] }} — {{ $alert['zone'] }}
                        <small>{{ $alert['date'] }}</small>
                    </a>
                @endforeach
            </div>
            <a class="button button-small" href="{{ route('front.alerts.index', ['severity' => 'critical']) }}">Voir les alertes</a>
        </section>
    @endif

    <section class="hero reveal">
        <div class="hero-copy">
            <span class="eyebrow"><span class="pulse-dot"></span> AQUASECURE · RÉSEAU EN DIRECT</span>
            <h1>L'eau sous surveillance.<br><em>La ville en confiance.</em></h1>
            <p>La plateforme intelligente qui transforme chaque signal en décision, pour des réseaux d'eau plus résilients et des villes plus sereines.</p>
            <div class="actions">
                <x-ui.button :href="route('front.incidents.create')">Signaler un incident <span>↗</span></x-ui.button>
                <x-ui.button :href="route('front.alerts.index')" variant="ghost">Voir les alertes <span>→</span></x-ui.button>
            </div>
        </div>
        <div class="hero-visual" aria-label="Réseau AquaSecure actif">
            <div class="ring ring-one"></div><div class="ring ring-two"></div>
            <div class="water-orb">◒<small>{{ number_format($liveStats['zones']) }} zones<br>surveillées</small></div>
            <div class="float-card float-card-main"><span class="eyebrow">ÉTAT DU RÉSEAU</span><strong><span class="pulse"></span> OPÉRATIONNEL</strong><small>{{ number_format($liveStats['zones']) }} zones surveillées</small></div>
            <div class="float-card float-card-health"><span class="eyebrow">QUALITÉ DE L'EAU</span><strong>98,7%</strong><small>Excellent</small></div>
            <div class="float-card float-card-alert"><span class="eyebrow">ALERTES ACTIVES</span><strong>{{ number_format($liveStats['active_alerts']) }}</strong><small>{{ number_format($liveStats['critical']) }} critique(s)</small></div>
        </div>
    </section>

    <section class="stats-strip" aria-label="Statistiques AquaSecure">
        <div class="counter-card"><strong data-counter="{{ $liveStats['zones'] }}">0</strong><span>Zones surveillées</span></div>
        <div class="counter-card"><strong data-counter="{{ $liveStats['active_alerts'] }}">0</strong><span>Alertes actives</span></div>
        <div class="counter-card"><strong data-counter="{{ $liveStats['resolved_month'] }}">0</strong><span>Alertes résolues ce mois</span></div>
        <div class="counter-card"><strong data-counter="98">0</strong><span>Qualité de l'eau (%)</span></div>
    </section>

    <section class="section">
        <div class="aq-nearby-card" data-nearest-zone data-endpoint="{{ route('front.zones.nearest') }}">
            <div>
                <span class="eyebrow">AUTOUR DE CHEZ VOUS</span>
                <h2>Quelle est ma zone ?</h2>
                <p>Autorisez la géolocalisation pour connaître la zone surveillée la plus proche et son niveau de risque.</p>
            </div>
            <div class="aq-nearby-card__action">
                <button type="button" class="button" data-nearest-trigger>◎ Trouver ma zone</button>
                <p class="aq-nearest__result" data-nearest-result hidden aria-live="polite"></p>
                <a class="text-link" href="{{ route('front.zones.index') }}">Explorer toutes les zones ↗</a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><div><span class="eyebrow">SURVEILLANCE CONTINUE</span><h2>État du réseau<br>en temps réel.</h2></div><span class="live-indicator"><i></i> Données actualisées en continu</span></div>
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

    <section class="section network-map-section"><div class="map-copy"><span class="eyebrow">VUE TERRITORIALE</span><h2>La carte du réseau.</h2><p>Visualisez les points surveillés et l'état global de nos infrastructures.</p><a class="text-link" href="{{ route('front.zones.index') }}">Consulter la carte des zones ↗</a></div><div class="network-map" aria-label="Représentation du réseau"><span class="map-node node-a"></span><span class="map-node node-b"></span><span class="map-node node-c"></span><span class="map-node node-d"></span><span class="map-line line-a"></span><span class="map-line line-b"></span><span class="map-line line-c"></span><span class="map-label">Réseau opérationnel · 99.2%</span></div></section>

    <section class="section tinted">
        <x-faq />
    </section>

    <section class="section">
        <x-emergency-contacts />
        <x-safety-tips level="medium" />
    </section>

    <section class="cta-section"><div><span class="eyebrow">AGIR ENSEMBLE</span><h2>Un problème sur le réseau ?</h2><p>Chaque signalement contribue à préserver une eau sûre et accessible.</p></div><x-ui.button :href="route('front.incidents.create')">Signaler un incident <span>↗</span></x-ui.button></section>
@endsection
