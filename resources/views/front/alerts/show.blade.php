@extends('layouts.front')

@section('title', $alert->type ?: 'Alerte')
@section('meta_description', $metaDescription)

@php
    $severityRank = ['info' => 1, 'low' => 1, 'warning' => 2, 'medium' => 2, 'high' => 3, 'critical' => 4];
    $severityLevel = collect([$alert->severity, $alert->ai_severity])
        ->filter()
        ->sortByDesc(fn ($level) => $severityRank[$level] ?? 0)
        ->first() ?: 'medium';
    $severityLabels = array_merge(\App\Models\Alert::SEVERITIES, \App\Models\Alert::AI_SEVERITIES);
    $severityLabel = $severityLabels[$severityLevel] ?? ucfirst($severityLevel);
    $reportedAt = $alert->created_at?->format('d/m/Y à H:i');
@endphp

@section('content')
    <nav class="aq-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('home') }}">Accueil</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('front.alerts.index') }}">Alertes</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $alert->type ?: 'Alerte' }}</span>
    </nav>

    <section class="page-hero compact alert-hero" data-severity="{{ $severityLevel }}">
        <span class="eyebrow">ALERTE PUBLIQUE</span>
        <h1>{{ $alert->type ?: 'Alerte réseau' }}</h1>

        <div class="alert-hero__meta">
            <x-severity-badge :severity="$severityLevel" :label="$severityLabel" />
            @if($reportedAt)
                <span class="aq-inline-note">Publiée le {{ $reportedAt }}</span>
            @endif
            @if($zone)
                <a class="aq-zone-pill" href="{{ route('front.zones.show', $zone) }}">
                    ◎ {{ $zone->name }}
                </a>
            @endif
        </div>

        <p class="zone-hero__summary">{{ $alert->message }}</p>
    </section>

    <section class="section aq-front">
        <div class="aq-zone-layout">
            <div class="aq-zone-layout__main">
                <section class="aq-panel aq-panel-pad">
                    <h2 class="aq-panel__title">Ce que cela signifie</h2>
                    <p class="aq-lead">{{ $explanation }}</p>
                </section>

                <section class="aq-panel aq-panel-pad">
                    <h2 class="aq-panel__title">Les bons réflexes</h2>
                    @if(empty($actions))
                        <p class="aq-inline-note">Aucune consigne particulière pour le moment. Restez attentif aux prochaines mises à jour.</p>
                    @else
                        <ul class="aq-action-list">
                            @foreach($actions as $action)
                                <li><span aria-hidden="true">✓</span>{{ $action }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="aq-panel aq-panel-pad">
                    <h2 class="aq-panel__title">Avancement du traitement</h2>
                    <ol class="aq-timeline">
                        @foreach($timeline as $step)
                            <li class="aq-timeline__item is-{{ $step['state'] }}">
                                <span class="aq-timeline__dot" aria-hidden="true"></span>
                                <div>
                                    <strong>{{ $step['label'] }}</strong>
                                    @if($step['date'])
                                        <small>{{ $step['date'] }}</small>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if($zone && $zone->image_path)
                    <figure class="aq-alert-photo">
                        <img src="{{ $zone->imageUrl() }}" alt="Photo de la zone {{ $zone->name }}" loading="lazy">
                    </figure>
                @endif
            </div>

            <aside class="aq-zone-layout__aside">
                <section class="aq-panel aq-panel-pad">
                    <h2 class="aq-panel__title">Partager l’alerte</h2>
                    <p class="aq-inline-note">Aidez vos proches à rester informés.</p>
                    <button type="button"
                            class="button button-outline"
                            data-share-report
                            data-share-title="{{ $alert->type ?: 'Alerte AquaSecure' }}"
                            data-share-url="{{ route('front.alerts.show', $alert) }}">
                        Partager
                    </button>
                </section>

                @if($zone)
                    <section class="aq-panel aq-panel-pad">
                        <h2 class="aq-panel__title">Cette zone vous concerne ?</h2>
                        <p class="aq-inline-note">Suivez-la pour retrouver ses alertes en priorité.</p>
                        <a class="button button-outline" href="{{ route('front.zones.show', $zone) }}">
                            Voir la zone {{ $zone->name }}
                        </a>
                    </section>
                @endif

                <section class="aq-panel aq-panel-pad">
                    <h2 class="aq-panel__title">Un problème persiste ?</h2>
                    <p class="aq-inline-note">Signalez-le à nos équipes en quelques minutes.</p>
                    <a class="button" href="{{ route('front.incidents.create', $zone ? ['zone_id' => $zone->id] : []) }}">
                        Signaler un incident
                    </a>
                </section>

                <x-emergency-contacts compact />
            </aside>
        </div>
    </section>

    <section class="section">
        <x-safety-tips level="{{ in_array($severityLevel, ['critical', 'high', 'medium', 'low'], true) ? $severityLevel : 'medium' }}" />
        <x-faq />
    </section>
@endsection
