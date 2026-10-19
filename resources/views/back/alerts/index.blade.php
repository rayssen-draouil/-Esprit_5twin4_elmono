@extends('layouts.back')

@section('title', 'Alertes')

@push('scripts')
    @vite(['resources/js/zones-alerts.js'])
@endpush

@section('content')
    @php
        $currentSort = request('sort', 'recent');
        $currentDir = request('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $severityTone = ['low' => 'info', 'medium' => 'info', 'high' => 'warning', 'critical' => 'critical'];
    @endphp

    <div class="aq-page" data-alerts-live
         data-url="{{ route('back.alerts.live') }}"
         data-interval="45000"
         data-since="{{ now()->toIso8601String() }}"
         data-critical-since="{{ now()->toIso8601String() }}"
         data-sound="0">

        <x-page-header
            eyebrow="SURVEILLANCE"
            title="Alertes"
            description="Suivez les alertes générées par les zones et les incidents, et pilotez leur traitement.">
            <a class="button button-outline" href="{{ route('back.alerts.export', request()->query()) }}">⬇ Exporter CSV</a>
            <x-ui.button :href="route('back.alerts.create')">+ Créer une alerte</x-ui.button>
        </x-page-header>

        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

        {{-- Bannière « nouvelles alertes » (rafraîchissement live) --}}
        <div class="aq-live" role="status" hidden data-alerts-new-banner>
            <span class="aq-live__text"><span class="aq-live__dot" aria-hidden="true"></span>
                <span data-alerts-new-text>Nouvelles alertes détectées.</span></span>
            <span class="aq-live__actions">
                <button type="button" class="button button-outline" onclick="window.location.reload()">Actualiser</button>
            </span>
        </div>

        {{-- Indicateurs clés (mis à jour en direct) --}}
        <div class="aq-stats" aria-label="Indicateurs des alertes">
            <x-stat-card label="Ouvertes" :value="$kpis['open']" hint="En attente de traitement" accent="warning" live-key="open" />
            <x-stat-card label="Critiques" :value="$kpis['critical']" hint="Ouvertes de gravité critique" accent="danger" live-key="critical" />
            <x-stat-card label="Résolues aujourd'hui" :value="$kpis['resolved_today']" hint="Depuis minuit" accent="success" live-key="resolved_today" />
            <x-stat-card label="Âge moyen" :value="$kpis['average_age'].' j'" hint="Ancienneté moyenne des alertes ouvertes" accent="info" live-key="average_age" />
        </div>

        {{-- Filtres serveur --}}
        <x-filter-bar :action="route('back.alerts.index')" data-auto-search data-alerts-filter>
            <div class="aq-filterbar__field aq-filterbar__search">
                <label for="alerts-filter-q">Recherche</label>
                <input id="alerts-filter-q" class="search" type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un type ou un message…" autocomplete="off">
            </div>

            <div class="aq-filterbar__field">
                <label for="alerts-filter-severity">Gravité</label>
                <select id="alerts-filter-severity" name="severity" class="aq-filter-select">
                    <option value="">Toutes les gravités</option>
                    @foreach(\App\Models\Alert::SEVERITIES as $value => $label)
                        <option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="aq-filterbar__field">
                <label for="alerts-filter-zone">Zone</label>
                <select id="alerts-filter-zone" name="zone" class="aq-filter-select">
                    <option value="">Toutes les zones</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" @selected((string) request('zone') === (string) $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="aq-filterbar__field">
                <label for="alerts-filter-status">Statut</label>
                <select id="alerts-filter-status" name="status" class="aq-filter-select">
                    <option value="">Tous les statuts</option>
                    <option value="open" @selected(request('status') === 'open')>Ouverte</option>
                    <option value="acknowledged" @selected(request('status') === 'acknowledged')>Acquittée</option>
                    <option value="resolved" @selected(request('status') === 'resolved')>Résolue</option>
                    <option value="unread" @selected(request('status') === 'unread')>Non lue</option>
                    <option value="read" @selected(request('status') === 'read')>Lue</option>
                </select>
            </div>

            <div class="aq-filterbar__field">
                <label for="alerts-filter-period">Période</label>
                <select id="alerts-filter-period" name="period" class="aq-filter-select">
                    <option value="">Toute la période</option>
                    <option value="today" @selected(request('period') === 'today')>Aujourd'hui</option>
                    <option value="7d" @selected(request('period') === '7d')>7 derniers jours</option>
                    <option value="30d" @selected(request('period') === '30d')>30 derniers jours</option>
                    <option value="custom" @selected(request('period') === 'custom')>Période personnalisée</option>
                </select>
            </div>

            @if(request('period') === 'custom')
                <div class="aq-filterbar__field">
                    <label for="alerts-filter-from">Du</label>
                    <input id="alerts-filter-from" type="date" name="from" value="{{ request('from') }}" class="aq-filter-select">
                </div>
                <div class="aq-filterbar__field">
                    <label for="alerts-filter-to">Au</label>
                    <input id="alerts-filter-to" type="date" name="to" value="{{ request('to') }}" class="aq-filter-select">
                </div>
            @endif

            <div class="aq-filterbar__field">
                <label for="alerts-filter-sort">Tri</label>
                <select id="alerts-filter-sort" name="sort" class="aq-filter-select">
                    <option value="recent" @selected($currentSort === 'recent')>Plus récentes</option>
                    <option value="oldest" @selected($currentSort === 'oldest')>Plus anciennes</option>
                    <option value="priority" @selected($currentSort === 'priority')>Priorité (gravité)</option>
                    <option value="severity" @selected($currentSort === 'severity')>Gravité</option>
                    <option value="age" @selected($currentSort === 'age')>Âge</option>
                </select>
            </div>

            <div class="aq-filterbar__field">
                <label for="alerts-filter-dir">Ordre</label>
                <select id="alerts-filter-dir" name="dir" class="aq-filter-select">
                    <option value="desc" @selected($currentDir === 'desc')>Décroissant</option>
                    <option value="asc" @selected($currentDir === 'asc')>Croissant</option>
                </select>
            </div>

            <div class="aq-filterbar__actions">
                @if(request()->hasAny(['q', 'severity', 'zone', 'status', 'period', 'from', 'to', 'sort', 'dir']))
                    <a class="aq-filterbar__clear" href="{{ route('back.alerts.index') }}">Réinitialiser</a>
                @endif
                <button type="submit" class="button button-outline">Filtrer</button>
            </div>
        </x-filter-bar>

        {{-- Chips de gravité (compteurs) --}}
        <div class="aq-chips" aria-label="Répartition par gravité">
            <a class="aq-chip @if(! request('severity')) is-active @endif" href="{{ request()->fullUrlWithQuery(['severity' => null]) }}">
                Total <span class="aq-chip__count">{{ array_sum($severityCounts) }}</span>
            </a>
            @foreach(\App\Models\Alert::SEVERITIES as $value => $label)
                <a class="aq-chip @if(request('severity') === $value) is-active @endif" data-tone="{{ $severityTone[$value] ?? 'info' }}"
                   href="{{ request()->fullUrlWithQuery(['severity' => $value]) }}">
                    {{ $label }} <span class="aq-chip__count">{{ $severityCounts[$value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        <div class="aq-toolbar-row">
            <p class="aq-toolbar-row__meta"><strong>{{ $alerts->total() }}</strong> alerte(s)</p>
            <div class="aq-actions">
                <a class="button button-outline" href="{{ request()->fullUrlWithQuery(['group' => $grouped ? null : 1]) }}">
                    {{ $grouped ? '▤ Vue simple' : '▤ Regrouper les doublons' }}
                </a>
            </div>
        </div>

        @if($alerts->isEmpty())
            <x-empty-state icon="◌" title="Aucune alerte ne correspond" message="Ajustez vos filtres ou créez une nouvelle alerte.">
                <x-ui.button :href="route('back.alerts.create')">Créer une alerte</x-ui.button>
            </x-empty-state>
        @else
            <form data-bulk-form data-bulk-url="{{ route('back.alerts.bulk') }}" data-csrf="{{ csrf_token() }}">
                <section class="aq-panel aq-panel-pad aq-scroll-x">
                    @if($grouped)
                        {{-- Vue groupée par clé IA --}}
                        @foreach($groups as $key => $items)
                            @php($group = $items->first())
                            <details class="aq-group" @if($loop->first) open @endif style="border-radius:0;border-left:0;border-right:0;margin-bottom:0">
                                <summary>
                                    <span>{{ $group->type }}</span>
                                    <span class="aq-group__count">{{ $items->count() }}</span>
                                    <span class="aq-group__meta">{{ $group->zone?->name ?? 'Zone non définie' }} · doublons regroupés par l'IA</span>
                                </summary>
                                <div class="aq-group__body">
                                    @foreach($items as $alert)
                                        <div class="aq-group__row">
                                            <label style="display:flex;align-items:center;gap:10px;flex:1 1 260px">
                                                <input type="checkbox" class="aq-check" name="ids[]" value="{{ $alert->id }}">
                                                <span>{{ \Illuminate\Support\Str::limit($alert->message, 90) }}</span>
                                            </label>
                                            <x-severity-badge :severity="$alert->severity" />
                                            <span class="aq-inline-note">{{ $alert->created_at?->format('d/m/Y H:i') }}</span>
                                            <a href="{{ route('back.alerts.show', $alert) }}">Voir</a>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    @else
                        <table class="aq-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th scope="col"><input type="checkbox" class="aq-check" data-bulk-all aria-label="Tout sélectionner"></th>
                                    <th scope="col">Alerte</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">Zone</th>
                                    <th scope="col">Gravité</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col">Date</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($alerts as $alert)
                                    <tr>
                                        <td><input type="checkbox" class="aq-check" name="ids[]" value="{{ $alert->id }}" aria-label="Sélectionner l'alerte {{ $alert->type }}"></td>
                                        <td>
                                            <span class="aq-cell-primary">{{ $alert->type }}</span>
                                            @if($alert->ai_severity)
                                                <span class="aq-cell-sub">IA : {{ $alert->ai_severity_label }}</span>
                                            @endif
                                        </td>
                                        <td>{{ \Illuminate\Support\Str::limit($alert->message, 90) }}</td>
                                        <td>
                                            @if($alert->zone)
                                                <a href="{{ route('back.zones.show', $alert->zone) }}">{{ $alert->zone->name }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td><x-severity-badge :severity="$alert->severity" /></td>
                                        <td><span class="aq-status" data-status="{{ $alert->status() }}">{{ $alert->status_label }}</span></td>
                                        <td>{{ $alert->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <div class="aq-row-actions">
                                                <a href="{{ route('back.alerts.show', $alert) }}">Voir</a>
                                                <a href="{{ route('back.alerts.edit', $alert) }}">Modifier</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>

                {{-- Barre d'actions groupées --}}
                <div class="aq-bulkbar" data-bulk-bar data-visible="false" role="region" aria-label="Actions groupées">
                    <span class="aq-bulkbar__count" data-bulk-count>0 sélectionnée</span>
                    <div class="aq-bulkbar__actions">
                        <button type="button" class="button button-outline" data-bulk-action="read">Marquer comme lues</button>
                        <button type="button" class="button button-outline" data-bulk-action="unread">Marquer comme non lues</button>
                        <button type="button" class="button button-outline" data-bulk-action="acknowledged">Acquitter</button>
                        <button type="button" class="button button-outline" data-bulk-action="resolved">Résoudre</button>
                    </div>
                </div>
            </form>

            {{ $alerts->links() }}
        @endif
    </div>
@endsection
