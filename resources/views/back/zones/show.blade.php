@extends('layouts.back')

@section('title', $zone->name)

@push('scripts')
    @vite(['resources/js/zones-alerts.js'])
@endpush

@section('content')
    @php
        $trend = $zone->aiRiskTrend();
        $scoreLevel = $zone->ai_risk_level ?: $zone->risk_level;
    @endphp

    <div class="aq-page">
        {{-- Bandeau principal --}}
        <section class="aq-hero">
            <div class="aq-hero__top">
                <div>
                    <span class="eyebrow">ZONE</span>
                    <h1 class="aq-hero__title">{{ $zone->name }}</h1>
                    <ul class="aq-hero__meta">
                        <li>◎ {{ $zone->address ?: 'Adresse non renseignée' }}</li>
                        <li><x-risk-badge :level="$zone->risk_level" /></li>
                        @if($zone->ai_risk_level)
                            <li><x-risk-badge :level="$zone->ai_risk_level" :ai="true" /></li>
                        @endif
                        @if($trend > 0)
                            <li class="ai-trend ai-trend--up">▲ IA supérieure au niveau manuel</li>
                        @elseif($trend < 0)
                            <li class="ai-trend ai-trend--down">▼ IA inférieure au niveau manuel</li>
                        @endif
                    </ul>
                </div>

                <div class="aq-actions">
                    <button type="button" class="button-ai-scan" id="zone-hero-ai">⚡ Analyser avec l'IA</button>
                    <x-ui.button :href="route('back.zones.edit', $zone)">Modifier</x-ui.button>
                    <x-ui.button :href="route('back.zones.index')" variant="outline">Retour</x-ui.button>
                </div>
            </div>

            <div class="aq-hero__grid">
                <div class="aq-hero__kpi"><span>Infrastructures</span><strong>{{ $zone->infrastructures->count() }}</strong></div>
                <div class="aq-hero__kpi"><span>Incidents</span><strong>{{ $zone->incidents->count() }}</strong></div>
                <div class="aq-hero__kpi"><span>Alertes</span><strong>{{ $zone->alerts->count() }}</strong></div>
                <div class="aq-hero__kpi"><span>Score IA</span><strong>{{ $zone->ai_risk_score !== null ? $zone->ai_risk_score.'/100' : '—' }}</strong></div>
            </div>
        </section>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if($zone->risk_level === 'high')
            <div class="alert alert-danger" role="alert">⚠ Niveau de risque élevé dans cette zone. Une vigilance renforcée est recommandée.</div>
        @endif

        {{-- Onglets --}}
        <div data-tabs>
            <div class="aq-tabs" role="tablist" aria-label="Sections de la zone">
                <button type="button" class="aq-tabs__btn" role="tab" data-tab="overview" aria-selected="true">Vue d'ensemble</button>
                <button type="button" class="aq-tabs__btn" role="tab" data-tab="alerts" aria-selected="false">
                    Alertes <span class="aq-tabs__count">{{ $zone->alerts->count() }}</span>
                </button>
                <button type="button" class="aq-tabs__btn" role="tab" data-tab="ia" aria-selected="false">Analyse IA</button>
                <button type="button" class="aq-tabs__btn" role="tab" data-tab="map" aria-selected="false">Carte</button>
            </div>

            {{-- Vue d'ensemble --}}
            <div class="aq-tabpanel" data-tab-panel="overview" role="tabpanel">
                @if($zone->image_path)
                    <section class="aq-panel aq-section-gap aq-zone-photo">
                        <img src="{{ asset('storage/'.$zone->image_path) }}" alt="Photo de {{ $zone->name }}" loading="lazy">
                    </section>
                @endif

                <section class="aq-panel aq-section-gap" style="padding:22px">
                    <h2 style="margin-top:0;font-size:17px">Description</h2>
                    <p class="aq-inline-note" style="font-size:14px;color:var(--ink)">{{ $zone->description ?: 'Aucune description.' }}</p>
                </section>

                <div class="aq-panel aq-section-gap" style="padding:22px">
                    <div class="aq-fieldgrid aq-fieldgrid--2">
                        <div class="aq-field">
                            <span class="aq-label">Adresse</span>
                            <p class="aq-inline-note" style="font-size:14px;color:var(--ink)">{{ $zone->address ?: '—' }}</p>
                        </div>
                        <div class="aq-field">
                            <span class="aq-label">Coordonnées</span>
                            <p class="aq-inline-note" style="font-size:14px;color:var(--ink)">
                                @if($zone->hasCoordinates())
                                    {{ number_format((float) $zone->latitude, 5, '.', '') }} / {{ number_format((float) $zone->longitude, 5, '.', '') }}
                                    @if($zone->radius_m) · Rayon {{ number_format((int) $zone->radius_m, 0, '.', ' ') }} m @endif
                                @else
                                    Non géolocalisée
                                @endif
                            </p>
                        </div>
                        <div class="aq-field">
                            <span class="aq-label">Risque manuel</span>
                            <p><x-risk-badge :level="$zone->risk_level" /></p>
                        </div>
                        <div class="aq-field">
                            <span class="aq-label">Dernière analyse IA</span>
                            <p class="aq-inline-note" style="font-size:14px;color:var(--ink)">{{ $zone->ai_analyzed_at?->format('d/m/Y à H:i') ?? 'Jamais analysée' }}</p>
                        </div>
                    </div>
                </div>

                <div class="aq-cardgrid aq-section-gap">
                    <section class="aq-panel aq-panel-pad aq-scroll-x">
                        <h2 style="margin:0;padding:18px 18px 0;font-size:16px">Infrastructures ({{ $zone->infrastructures->count() }})</h2>
                        <table class="aq-table" style="width:100%"><thead><tr><th scope="col">Nom</th><th scope="col">Type</th><th scope="col">Statut</th></tr></thead><tbody>
                            @forelse($zone->infrastructures as $infra)
                                <tr><td><span class="aq-cell-primary">{{ $infra->name }}</span></td><td>{{ $infra->type }}</td><td>{{ $infra->status }}</td></tr>
                            @empty
                                <tr><td colspan="3">Aucune infrastructure.</td></tr>
                            @endforelse
                        </tbody></table>
                    </section>

                    <section class="aq-panel aq-panel-pad aq-scroll-x">
                        <h2 style="margin:0;padding:18px 18px 0;font-size:16px">Incidents ({{ $zone->incidents->count() }})</h2>
                        <table class="aq-table" style="width:100%"><thead><tr><th scope="col">Type</th><th scope="col">Statut</th><th scope="col">Date</th></tr></thead><tbody>
                            @forelse($zone->incidents as $incident)
                                <tr>
                                    <td><span class="aq-cell-primary">{{ $incident->type }}</span><span class="aq-cell-sub">{{ \Illuminate\Support\Str::limit($incident->description, 80) }}</span></td>
                                    <td>{{ $incident->status }}</td>
                                    <td>{{ $incident->reported_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">Aucun incident.</td></tr>
                            @endforelse
                        </tbody></table>
                    </section>
                </div>
            </div>

            {{-- Alertes --}}
            <div class="aq-tabpanel" data-tab-panel="alerts" role="tabpanel" hidden>
                <div class="aq-chips">
                    <span class="aq-chip is-active">Total <span class="aq-chip__count">{{ $zone->alerts->count() }}</span></span>
                    @foreach(\App\Models\Alert::SEVERITIES as $level => $label)
                        <span class="aq-chip" data-tone="{{ ['low' => 'info', 'medium' => 'info', 'high' => 'warning', 'critical' => 'critical'][$level] ?? 'info' }}">
                            {{ $label }} <span class="aq-chip__count">{{ $alertCounts[$level] ?? 0 }}</span>
                        </span>
                    @endforeach
                </div>

                <section class="aq-panel aq-section-gap" style="padding:20px">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                        <div>
                            <span class="eyebrow" style="color:var(--aqua)">30 DERNIERS JOURS</span>
                            <h2 style="margin:4px 0 0;font-size:16px">Activité des alertes</h2>
                        </div>
                        <small class="aq-inline-note">{{ $chart['total'] }} alerte(s) sur la période</small>
                    </div>
                    <div style="height:220px;margin-top:12px">
                        <canvas data-zone-alerts-chart
                                data-labels="{{ json_encode($chart['labels']) }}"
                                data-values="{{ json_encode($chart['values']) }}"
                                role="img" aria-label="Nombre d'alertes par jour sur les 30 derniers jours"></canvas>
                    </div>
                </section>

                <div class="aq-toolbar-row">
                    <h2 style="margin:0;font-size:16px">Liste des alertes</h2>
                    <x-ui.button :href="route('back.alerts.create', ['zone_id' => $zone->id])" variant="outline">+ Ajouter une alerte</x-ui.button>
                </div>

                @if($zone->alerts->isEmpty())
                    <x-empty-state icon="◌" title="Aucune alerte" message="Cette zone n'a pas encore généré d'alerte." />
                @else
                    <section class="aq-panel aq-panel-pad aq-scroll-x">
                        <table class="aq-table" style="width:100%">
                            <thead><tr><th scope="col">Alerte</th><th scope="col">Message</th><th scope="col">Gravité</th><th scope="col">Statut</th><th scope="col">Date</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                            <tbody>
                                @foreach($zone->alerts as $alert)
                                    <tr>
                                        <td><span class="aq-cell-primary">{{ $alert->type }}</span></td>
                                        <td>{{ \Illuminate\Support\Str::limit($alert->message, 90) }}</td>
                                        <td><x-severity-badge :severity="$alert->severity" /></td>
                                        <td><span class="aq-status" data-status="{{ $alert->resolved_at ? 'resolved' : ($alert->acknowledged_at ? 'acknowledged' : 'open') }}">{{ $alert->status_label }}</span></td>
                                        <td>{{ $alert->created_at?->format('d/m/Y H:i') }}</td>
                                        <td><div class="aq-row-actions"><a href="{{ route('back.alerts.show', $alert) }}">Voir</a></div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                @endif
            </div>

            {{-- Analyse IA --}}
            <div class="aq-tabpanel" data-tab-panel="ia" role="tabpanel" hidden>
                @include('back.zones._ai_panel', ['zone' => $zone])
            </div>

            {{-- Carte --}}
            <div class="aq-tabpanel" data-tab-panel="map" role="tabpanel" hidden>
                @include('back.zones._map', ['mode' => 'zone', 'zone' => $zone, 'alerts' => $zone->alerts])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var hero = document.getElementById('zone-hero-ai');
            if (!hero) return;
            hero.addEventListener('click', function () {
                var tab = document.querySelector('[data-tab="ia"]');
                if (tab) tab.click();
                if (typeof analyzeZoneAi === 'function') analyzeZoneAi();
            });
        })();
    </script>
@endpush
