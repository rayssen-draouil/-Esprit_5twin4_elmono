@extends('layouts.front')

@section('title', $zone->name)
@section('meta_description', $summary)

@push('scripts')
    @vite(['resources/js/zone-map.js'])
@endpush

@php
    $zoneMapConfig = [
        'zone' => [
            'name' => $zone->name,
            'risk_level' => $riskLevel,
            'risk_label' => $zone->risk_label,
            'ai_risk_score' => $zone->ai_risk_score,
            'ai_risk_level_label' => $zone->ai_risk_level ? $zone->ai_risk_level_label : null,
            'latitude' => $zone->latitude,
            'longitude' => $zone->longitude,
            'radius_m' => $zone->radius_m,
            'url' => route('front.zones.show', $zone),
        ],
        'alerts' => collect($activeAlerts)->map(fn ($alert) => [
            'type' => $alert['type'],
            'severity' => $alert['severity'],
            'severity_label' => $alert['severityLabel'],
            'message' => $alert['message'],
            'date' => $alert['date'],
            'url' => $alert['url'],
        ])->all(),
    ];
@endphp

@section('content')
    <nav class="aq-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('home') }}">Accueil</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('front.zones.index') }}">Zones</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $zone->name }}</span>
    </nav>

    <section class="page-hero compact zone-hero">
        <div class="zone-hero__main">
            <span class="eyebrow">ZONE SURVEILLÉE</span>
            <h1>{{ $zone->name }}</h1>
            <p class="zone-hero__address">◎ {{ $zone->address ?: 'Adresse non renseignée' }}</p>

            <div class="zone-hero__badges">
                <x-risk-badge :level="$riskLevel" />
                @if($zone->ai_risk_level && $zone->ai_risk_level !== $riskLevel)
                    <x-risk-badge :level="$zone->ai_risk_level" :ai="true" />
                @endif
                <span class="aq-inline-note">{{ $activeCount }} alerte(s) active(s)</span>
            </div>

            <p class="zone-hero__summary">{{ $summary }}</p>

            <div class="zone-hero__actions">
                <a class="button" href="{{ route('front.incidents.create', ['zone_id' => $zone->id]) }}">
                    Signaler un problème dans cette zone
                </a>
                <button type="button"
                        class="button button-outline"
                        data-favorite-zone="{{ $zone->id }}"
                        aria-pressed="false">
                    <span data-favorite-icon aria-hidden="true">☆</span>
                    <span data-favorite-text>Suivre cette zone</span>
                </button>
            </div>
        </div>

        @if($zone->image_path)
            <figure class="zone-hero__media">
                <img src="{{ $zone->imageUrl() }}" alt="Photo de la zone {{ $zone->name }}" loading="lazy">
            </figure>
        @endif
    </section>

    <section class="section aq-front">
        <div class="aq-zone-layout">
            <div class="aq-zone-layout__main">
                <div class="aq-toolbar-row">
                    <h2>Alertes en cours</h2>
                    <a class="text-link" href="{{ route('front.alerts.index', ['zone' => $zone->id]) }}">Toutes les alertes de la zone ↗</a>
                </div>

                @if(empty($activeAlerts))
                    <x-empty-state
                        icon="✓"
                        title="Aucune alerte active"
                        message="La situation est calme dans cette zone. Nos équipes continuent de surveiller le réseau en continu." />
                @else
                    <div class="alert-card-grid">
                        @foreach($activeAlerts as $alert)
                            <x-alert-card
                                :type="$alert['type']"
                                :message="$alert['message']"
                                :severity="$alert['severity']"
                                :severity-label="$alert['severityLabel']"
                                :ai-severity="$alert['aiSeverity']"
                                :status="$alert['status']"
                                :status-level="$alert['statusLevel']"
                                :date="$alert['date']"
                                :url="$alert['url']" />
                        @endforeach
                    </div>
                @endif

                <section class="aq-chart-panel">
                    <div class="aq-chart-head">
                        <div>
                            <span class="eyebrow" style="color:var(--aqua)">30 DERNIERS JOURS</span>
                            <h2>Activité des alertes</h2>
                        </div>
                        <small>{{ $chart['total'] }} alerte(s) sur la période</small>
                    </div>
                    <div class="aq-chart-wrap">
                        <div class="aq-skeleton aq-skeleton--chart" data-chart-skeleton aria-hidden="true"></div>
                        <canvas id="aq-front-chart" role="img"
                                aria-label="Nombre d’alertes par jour sur les 30 derniers jours"></canvas>
                    </div>
                    <script type="application/json" id="aq-chart-data">@json($chart)</script>
                </section>

                <x-safety-tips :level="$riskLevel" />

                @if($zone->hasCoordinates())
                    <section class="zone-map-panel">
                        <h2>Localisation</h2>
                        <div class="zone-map-canvas"
                             data-zone-map
                             data-mode="zone"
                             data-config="{{ json_encode($zoneMapConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                             role="application"
                             aria-label="Carte de la zone {{ $zone->name }}"></div>
                    </section>
                @else
                    <p class="aq-inline-note">Cette zone n’est pas encore géolocalisée sur la carte.</p>
                @endif
            </div>

            <aside class="aq-zone-layout__aside">
                <section class="aq-panel aq-panel-pad">
                    <h2 class="aq-panel__title">En un coup d’œil</h2>
                    <ul class="aq-facts">
                        <li><span>Niveau de risque</span><strong>{{ $zone->risk_label }}</strong></li>
                        <li><span>Alertes actives</span><strong>{{ $activeCount }}</strong></li>
                        <li><span>Dernière analyse</span><strong>{{ $zone->ai_analyzed_at?->format('d/m/Y') ?? '—' }}</strong></li>
                    </ul>
                </section>

                <x-emergency-contacts compact />
            </aside>
        </div>
    </section>

    <section class="section">
        <x-faq />
    </section>
@endsection
