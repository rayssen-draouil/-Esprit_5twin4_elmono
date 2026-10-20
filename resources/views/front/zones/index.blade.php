@extends('layouts.front')

@section('title', 'Zones surveillées')
@section('meta_description', 'Découvrez les zones surveillées par AquaSecure, leur niveau de risque et les alertes en cours près de chez vous.')

@push('scripts')
    @vite(['resources/js/zone-map.js'])
@endpush

@php
    $mapConfig = [
        'endpoint' => route('front.zones.map-data'),
        'defaultCenter' => [
            (float) config('aquasecure.map.center.lat'),
            (float) config('aquasecure.map.center.lng'),
        ],
        'defaultZoom' => (int) config('aquasecure.map.zoom'),
    ];
    $hasFilters = request()->hasAny(['q', 'risk', 'region', 'sort']);
@endphp

@section('content')
    <section class="page-hero compact">
        <span class="eyebrow">ZONES SURVEILLÉES</span>
        <h1>Chaque territoire<br><em>sous surveillance.</em></h1>
        <p>Consultez le niveau de risque de votre zone, les alertes en cours et les conseils à suivre.</p>
    </section>

    <section class="section aq-front">
        <div class="aq-stats">
            <x-stat-counter label="Zones surveillées" :value="$kpis['total']" accent="info" />
            <x-stat-counter label="Zones à risque élevé" :value="$kpis['highRisk']" accent="critical" />
            <x-stat-counter label="Zones analysées par l’IA" :value="$kpis['analyzed']" accent="success" />
            <x-stat-counter
                label="Score IA moyen"
                :value="$kpis['avgScore'] !== null ? (int) round($kpis['avgScore']) : '—'"
                :suffix="$kpis['avgScore'] !== null ? '/100' : ''"
                accent="warning" />
        </div>

        <div class="aq-tools">
            <x-filter-bar :action="route('front.zones.index')" data-auto-search>
                <div class="toolbar">
                    <input class="search" type="search" name="q" value="{{ request('q') }}"
                           placeholder="Rechercher une zone, une ville…" autocomplete="off"
                           aria-label="Rechercher une zone">

                    <select name="risk" class="aq-filter-select" aria-label="Niveau de risque">
                        <option value="">Tous les niveaux de risque</option>
                        @foreach(\App\Models\Zone::RISK_LEVELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('risk') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="region" class="aq-filter-select" aria-label="Région">
                        <option value="">Toutes les régions</option>
                        @foreach(($regions ?? []) as $region)
                            <option value="{{ $region }}" @selected(request('region') === $region)>{{ $region }}</option>
                        @endforeach
                    </select>

                    <select name="sort" class="aq-filter-select" aria-label="Trier par">
                        <option value="name" @selected(request('sort', 'name') === 'name')>Nom</option>
                        <option value="risk" @selected(request('sort') === 'risk')>Risque décroissant</option>
                        <option value="alerts" @selected(request('sort') === 'alerts')>Alertes actives</option>
                        <option value="incidents" @selected(request('sort') === 'incidents')>Incidents</option>
                    </select>

                    <button type="submit" class="button button-outline">Filtrer</button>
                    @if($hasFilters)
                        <a class="aq-filterbar__clear" href="{{ route('front.zones.index') }}">Réinitialiser</a>
                    @endif
                </div>
            </x-filter-bar>

            <div class="aq-tools__row">
                <div class="aq-viewswitch" data-zone-view-toggle role="group" aria-label="Affichage des zones">
                    <button type="button" class="aq-viewswitch__btn" data-zone-view="list" aria-pressed="true">Cartes</button>
                    <button type="button" class="aq-viewswitch__btn" data-zone-view="map" aria-pressed="false">Carte</button>
                </div>

                <div class="aq-nearest" data-nearest-zone data-endpoint="{{ route('front.zones.nearest') }}">
                    <button type="button" class="button button-outline button-small" data-nearest-trigger>
                        ◎ Trouver ma zone
                    </button>
                    <p class="aq-nearest__result" data-nearest-result hidden aria-live="polite"></p>
                </div>
            </div>
        </div>

        <div id="zones-list-panel">
            @if($zones->isEmpty())
                <x-empty-state
                    title="Aucune zone trouvée"
                    message="Essayez un autre mot-clé ou réinitialisez les filtres pour voir toutes les zones surveillées.">
                    @if($hasFilters)
                        <a class="button button-outline" href="{{ route('front.zones.index') }}">Voir toutes les zones</a>
                    @endif
                </x-empty-state>
            @else
                <div class="zone-card-grid">
                    @foreach($zones as $zone)
                        <x-zone-card
                            :name="$zone['name']"
                            :address="$zone['address']"
                            :description="$zone['description']"
                            :risk="$zone['riskLevel'] ?? 'low'"
                            :risk-label="$zone['risk']"
                            :ai-risk="$zone['aiRiskLevel']"
                            :image="$zone['image']"
                            :alerts="$zone['alertsCount']"
                            :incidents="$zone['incidents']"
                            :infrastructures="$zone['infrastructures']"
                            :url="$zone['url']"
                            :zone-id="$zone['id']"
                            :last-update="$zone['lastAlert']" />
                    @endforeach
                </div>

                @if($zones->hasPages())
                    <nav class="aq-pagination" aria-label="Pagination des zones">
                        {{ $zones->links() }}
                    </nav>
                @endif
            @endif
        </div>

        <div id="zones-map-panel" class="zone-map-panel" hidden>
            <p class="aq-inline-note">
                Les zones sans coordonnées ne sont pas affichées sur la carte.
            </p>
            <div class="zone-map-canvas"
                 data-zone-map
                 data-mode="index"
                 data-config="{{ json_encode($mapConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                 role="application"
                 aria-label="Carte des zones surveillées"></div>
        </div>
    </section>

    <section class="section">
        <x-safety-tips level="medium" title="Les bons réflexes, quel que soit le niveau de risque" />
        <x-faq />
        <x-emergency-contacts />
    </section>
@endsection
