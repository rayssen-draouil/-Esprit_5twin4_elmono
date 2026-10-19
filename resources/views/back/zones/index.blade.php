@extends('layouts.back')

@section('title', 'Zones')

@push('scripts')
    @vite(['resources/js/zones-alerts.js'])
@endpush

@section('content')
    @php
        $currentSort = request('sort', 'name');
        $currentDir = request('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortUrl = function (string $column) use ($currentSort, $currentDir): string {
            $dir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
            return request()->fullUrlWithQuery(['sort' => $column, 'dir' => $dir]);
        };
        $sortIcon = function (string $column) use ($currentSort, $currentDir): string {
            if ($currentSort !== $column) return '↕';
            return $currentDir === 'asc' ? '↑' : '↓';
        };
        $sortState = function (string $column) use ($currentSort, $currentDir): ?string {
            if ($currentSort !== $column) return null;
            return $currentDir === 'asc' ? 'ascending' : 'descending';
        };
    @endphp

    <div class="aq-page">
        <x-page-header
            eyebrow="SURVEILLANCE"
            title="Zones"
            description="Gérez les zones surveillées, leur niveau de risque et leur dernière analyse IA.">
            <a class="button button-outline" href="{{ route('back.zones.export', request()->query()) }}">⬇ Exporter CSV</a>
            <x-ui.button :href="route('back.zones.create')">+ Ajouter une zone</x-ui.button>
        </x-page-header>

        {{-- Indicateurs clés --}}
        <div class="aq-stats" aria-label="Indicateurs des zones">
            <x-stat-card label="Zones totales" :value="$kpis['total']" hint="Périmètre surveillé" />
            <x-stat-card label="Zones critiques" :value="$kpis['critical']" hint="Risque élevé ou IA critique" accent="danger" />
            <x-stat-card label="Score IA moyen" :value="$kpis['average_score'] !== null ? $kpis['average_score'].'/100' : '—'" hint="Moyenne des analyses" accent="info" />
            <x-stat-card label="Sans analyse récente" :value="$kpis['stale']" hint="Aucune analyse depuis 7 jours" accent="warning" />
        </div>

        {{-- Filtres serveur (persistés dans l'URL) --}}
        <x-filter-bar :action="route('back.zones.index')" data-auto-search>
            <div class="toolbar">
                <input class="search" type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher une zone…" autocomplete="off" aria-label="Rechercher une zone">

                <select name="risk" class="aq-filter-select" aria-label="Niveau de risque">
                    <option value="">Tous les niveaux</option>
                    @foreach(\App\Models\Zone::RISK_LEVELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('risk') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="coords" class="aq-filter-select" aria-label="Géolocalisation">
                    <option value="">Toutes les zones</option>
                    <option value="with" @selected(request('coords') === 'with')>Avec coordonnées</option>
                    <option value="without" @selected(request('coords') === 'without')>Sans coordonnées</option>
                </select>

                <select name="sort" class="aq-filter-select" aria-label="Trier par">
                    <option value="name" @selected($currentSort === 'name')>Nom</option>
                    <option value="risk_score" @selected($currentSort === 'risk_score')>Score IA</option>
                    <option value="last_alert" @selected($currentSort === 'last_alert')>Dernière alerte</option>
                    <option value="last_analysis" @selected($currentSort === 'last_analysis')>Dernière analyse IA</option>
                </select>

                <select name="dir" class="aq-filter-select" aria-label="Ordre">
                    <option value="asc" @selected($currentDir === 'asc')>Croissant</option>
                    <option value="desc" @selected($currentDir === 'desc')>Décroissant</option>
                </select>

                <button type="submit" class="button button-outline">Filtrer</button>

                @if(request()->hasAny(['q', 'risk', 'coords', 'sort', 'dir']))
                    <a class="aq-filterbar__clear" href="{{ route('back.zones.index') }}">Réinitialiser</a>
                @endif
            </div>
        </x-filter-bar>

        <div class="aq-toolbar-row">
            <p class="aq-toolbar-row__meta"><strong>{{ $zones->total() }}</strong> zone(s)</p>

            <div class="aq-actions">
                <div class="aq-viewswitch" data-layout-toggle role="group" aria-label="Disposition de la liste">
                    <button type="button" class="aq-viewswitch__btn" data-layout="table" aria-pressed="true">▤ Tableau</button>
                    <button type="button" class="aq-viewswitch__btn" data-layout="cards" aria-pressed="false">▦ Cartes</button>
                </div>

                {{-- Bascule liste / carte (carte globale alimentée par back.zones.map-data) --}}
                <div class="zone-view-toggle" data-zone-view-toggle role="group" aria-label="Affichage des zones" style="margin:0">
                    <button type="button" class="button button-outline zone-view-toggle__btn" data-zone-view="list" aria-pressed="true">☰ Liste</button>
                    <button type="button" class="button button-outline zone-view-toggle__btn" data-zone-view="map" aria-pressed="false">◎ Carte</button>
                </div>
            </div>
        </div>

        @if($zones->isEmpty())
            <x-empty-state
                icon="◌"
                title="Aucune zone ne correspond"
                message="Ajustez vos filtres ou créez une première zone pour commencer la surveillance.">
                <x-ui.button :href="route('back.zones.create')">Ajouter une zone</x-ui.button>
            </x-empty-state>
        @else
            <section class="aq-panel aq-panel-pad aq-scroll-x" id="zones-list-panel">
                {{-- Vue tableau --}}
                <div data-layout-table>
                    <table class="aq-table" style="width:100%">
                        <thead>
                            <tr>
                                <th scope="col" @if($sortState('name')) aria-sort="{{ $sortState('name') }}" @endif>
                                    <a class="aq-sort" href="{{ $sortUrl('name') }}">Zone <span class="aq-sort__icon" aria-hidden="true">{{ $sortIcon('name') }}</span></a>
                                </th>
                                <th scope="col">Risque</th>
                                <th scope="col" @if($sortState('risk_score')) aria-sort="{{ $sortState('risk_score') }}" @endif>
                                    <a class="aq-sort" href="{{ $sortUrl('risk_score') }}">Score IA <span class="aq-sort__icon" aria-hidden="true">{{ $sortIcon('risk_score') }}</span></a>
                                </th>
                                <th scope="col">Infrastructures</th>
                                <th scope="col">Incidents</th>
                                <th scope="col" @if($sortState('last_analysis')) aria-sort="{{ $sortState('last_analysis') }}" @endif>
                                    <a class="aq-sort" href="{{ $sortUrl('last_analysis') }}">Dernière analyse <span class="aq-sort__icon" aria-hidden="true">{{ $sortIcon('last_analysis') }}</span></a>
                                </th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($zones as $zone)
                                <tr>
                                    <td>
                                        <span class="aq-cell-primary">{{ $zone->name }}</span>
                                        <span class="aq-cell-sub">{{ $zone->address }}</span>
                                    </td>
                                    <td><x-risk-badge :level="$zone->risk_level" /></td>
                                    <td>
                                        @if($zone->ai_risk_score !== null)
                                            @php($scoreLevel = $zone->ai_risk_level ?: $zone->risk_level)
                                            <div class="aq-gauge-wrap">
                                                <span class="aq-gauge" role="img" aria-label="Score IA {{ $zone->ai_risk_score }} sur 100">
                                                    <span class="aq-gauge__fill" data-level="{{ $scoreLevel }}" style="width: {{ max(0, min(100, (int) $zone->ai_risk_score)) }}%"></span>
                                                </span>
                                                <strong>{{ $zone->ai_risk_score }}</strong>
                                            </div>
                                            @php($trend = $zone->aiRiskTrend())
                                            @if($trend > 0)
                                                <span class="aq-cell-sub" title="L'IA estime le risque plus élevé que le niveau manuel">▲ IA supérieure</span>
                                            @elseif($trend < 0)
                                                <span class="aq-cell-sub" title="L'IA estime le risque plus faible">▼ IA inférieure</span>
                                            @else
                                                <span class="aq-cell-sub">= IA stable</span>
                                            @endif
                                        @else
                                            <span class="aq-cell-sub">Non analysée</span>
                                        @endif
                                    </td>
                                    <td>{{ $zone->infrastructures_count }}</td>
                                    <td>{{ $zone->incidents_count }}</td>
                                    <td>{{ $zone->ai_analyzed_at?->diffForHumans() ?? '—' }}</td>
                                    <td>
                                        <div class="aq-row-actions">
                                            <a href="{{ route('back.zones.show', $zone) }}">Voir</a>
                                            <a href="{{ route('back.zones.edit', $zone) }}">Modifier</a>
                                            <form method="POST" action="{{ route('back.zones.destroy', $zone) }}" data-confirm="Supprimer la zone « {{ $zone->name }} » ?">
                                                @csrf @method('DELETE')
                                                <button type="submit" style="border:0;background:none;color:#bd5149;cursor:pointer;font:inherit;font-size:12px">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Vue cartes --}}
                <div data-layout-cards hidden style="padding:16px">
                    <div class="aq-cardgrid">
                        @foreach($zones as $zone)
                            <article class="aq-card">
                                <div class="aq-card__head">
                                    <h3 class="aq-card__title"><a href="{{ route('back.zones.show', $zone) }}">{{ $zone->name }}</a></h3>
                                    <x-risk-badge :level="$zone->risk_level" />
                                </div>
                                <ul class="aq-card__meta">
                                    <li>◎ {{ $zone->address ?: 'Adresse non renseignée' }}</li>
                                    <li>▤ {{ $zone->infrastructures_count }} infrastructure(s)</li>
                                    <li>⚠ {{ $zone->incidents_count }} incident(s)</li>
                                </ul>
                                <p class="aq-card__body">{{ \Illuminate\Support\Str::limit($zone->description ?: 'Aucune description.', 120) }}</p>
                                <div class="aq-card__foot">
                                    <span class="aq-cell-sub">Score IA : {{ $zone->ai_risk_score ?? '—' }}{{ $zone->ai_risk_score !== null ? '/100' : '' }}
                                        @if($zone->ai_risk_level) · {{ $zone->ai_risk_level_label }}@endif
                                    </span>
                                    <div class="aq-row-actions">
                                        <a href="{{ route('back.zones.show', $zone) }}">Détails</a>
                                        <a href="{{ route('back.zones.edit', $zone) }}">Modifier</a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            {{ $zones->links() }}
        @endif

        {{-- Carte globale : alimentée par la route back.zones.map-data (zones géolocalisées uniquement) --}}
        @include('back.zones._map', ['mode' => 'index'])
    </div>
@endsection
