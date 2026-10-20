@extends('layouts.front')

@section('title', 'Alertes en cours')
@section('meta_description', 'Suivez en temps réel les alertes du réseau AquaSecure : gravité, zone concernée et conseils à suivre.')

@php
    $filters = request()->only(['q', 'severity', 'zone', 'period', 'sort']);
    $severityTones = ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'critical'];
    $hasFilters = request()->hasAny(['q', 'severity', 'zone', 'period']);
@endphp

@section('content')
    <section class="page-hero compact">
        <span class="eyebrow">SURVEILLANCE CONTINUE</span>
        <h1>Alertes<br><em>en temps réel.</em></h1>
        <p>Les alertes remontées par les zones et les incidents du réseau AquaSecure.</p>
    </section>

    <section class="section aq-front">
        <div id="aq-live"
             class="aq-live"
             data-live-url="{{ route('front.alerts.live') }}"
             data-live-interval="45000"
             hidden>
            <span class="aq-live__dot" aria-hidden="true"></span>
            <p class="aq-live__text">
                <span data-live-label>De nouvelles alertes sont disponibles.</span>
                <small data-live-stamp>Actualisation automatique toutes les 45 secondes.</small>
            </p>
            <button type="button" class="button button-small" data-live-refresh hidden>Actualiser</button>
        </div>

        <div class="aq-stats">
            <x-stat-counter label="Alertes en cours" :value="$kpis['open']" accent="warning" live-key="open" />
            <x-stat-counter label="Alertes critiques" :value="$kpis['critical']" accent="critical" live-key="critical" />
            <x-stat-counter label="Dernières 24 h" :value="$kpis['last24h']" accent="info" />
        </div>

        <x-filter-bar :action="route('front.alerts.index')" data-auto-search>
            <div class="toolbar">
                <input class="search" type="search" name="q" value="{{ request('q') }}"
                       placeholder="Rechercher une alerte…" autocomplete="off"
                       aria-label="Rechercher une alerte">

                <select name="severity" class="aq-filter-select" aria-label="Gravité">
                    <option value="">Toutes les gravités</option>
                    @foreach(\App\Models\Alert::SEVERITIES as $value => $label)
                        <option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="zone" class="aq-filter-select" aria-label="Zone">
                    <option value="">Toutes les zones</option>
                    @foreach(($zones ?? []) as $zone)
                        <option value="{{ $zone->id }}" @selected((string) request('zone') === (string) $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>

                <select name="period" class="aq-filter-select" aria-label="Période">
                    <option value="">Toute la période</option>
                    <option value="today" @selected(request('period') === 'today')>Aujourd’hui</option>
                    <option value="7d" @selected(request('period') === '7d')>7 derniers jours</option>
                    <option value="30d" @selected(request('period') === '30d')>30 derniers jours</option>
                </select>

                <select name="sort" class="aq-filter-select" aria-label="Trier par">
                    <option value="priority" @selected(($sortKey ?? 'priority') === 'priority')>Gravité puis date</option>
                    <option value="date" @selected(($sortKey ?? '') === 'date')>Plus récentes</option>
                    <option value="oldest" @selected(($sortKey ?? '') === 'oldest')>Plus anciennes</option>
                </select>

                <button type="submit" class="button button-outline">Filtrer</button>
                @if($hasFilters)
                    <a class="aq-filterbar__clear" href="{{ route('front.alerts.index') }}">Réinitialiser</a>
                @endif
            </div>
        </x-filter-bar>

        <div class="aq-chips-head">
            <h2>Par gravité</h2>
            <div class="aq-chips">
                <a class="aq-chip {{ ! request('severity') ? 'is-active' : '' }}"
                   href="{{ route('front.alerts.index', array_filter(array_merge($filters, ['severity' => null]))) }}">
                    Toutes <span class="aq-chip__count">{{ array_sum($severityCounts->toArray()) }}</span>
                </a>
                @foreach(\App\Models\Alert::SEVERITIES as $value => $label)
                    <a class="aq-chip {{ request('severity') === $value ? 'is-active' : '' }}"
                       data-tone="{{ $severityTones[$value] ?? 'info' }}"
                       href="{{ route('front.alerts.index', array_filter(array_merge($filters, ['severity' => $value]))) }}">
                        {{ $label }} <span class="aq-chip__count">{{ $severityCounts[$value] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            <button type="button" class="aq-chip aq-chip--followed" data-followed-filter aria-pressed="false">
                ★ Mes zones suivies
            </button>
        </div>

        @if($alerts->isEmpty())
            @if($hasFilters)
                <x-empty-state
                    title="Aucune alerte ne correspond"
                    message="Modifiez vos filtres ou réinitialisez la recherche pour voir toutes les alertes.">
                    <a class="button button-outline" href="{{ route('front.alerts.index') }}">Voir toutes les alertes</a>
                </x-empty-state>
            @else
                <x-empty-state
                    icon="✓"
                    title="Aucune alerte active : tout va bien"
                    message="Le réseau est calme en ce moment. Nous continuons de le surveiller 24 h/24." />
            @endif
        @else
            <div class="alert-card-grid" data-alert-feed>
                @foreach($alerts as $alert)
                    <x-alert-card
                        :type="$alert['type']"
                        :message="$alert['message']"
                        :zone="$alert['zone']"
                        :zone-id="$alert['zoneId']"
                        :zone-image="$alert['zoneImage']"
                        :zone-url="$alert['zoneId'] ? route('front.zones.show', $alert['zoneId']) : null"
                        :severity="$alert['severityLevel']"
                        :severity-label="$alert['severity']"
                        :ai-severity="$alert['aiSeverity']"
                        :status="$alert['status']"
                        :status-level="$alert['statusLevel']"
                        :date="$alert['date']"
                        :url="$alert['url']" />
                @endforeach
            </div>

            <p class="aq-empty-filter" data-followed-empty hidden>
                Aucune alerte en cours dans vos zones suivies. Vous êtes prévenu ici dès qu’une alerte apparaît.
            </p>

            @if($alerts->hasPages())
                <nav class="aq-pagination" aria-label="Pagination des alertes">
                    {{ $alerts->links() }}
                </nav>
            @endif
        @endif
    </section>

    <section class="section">
        <x-safety-tips level="medium" title="Les bons réflexes en cas d’alerte" />
        <x-emergency-contacts compact />
    </section>
@endsection
