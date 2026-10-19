@extends('layouts.back')

@section('title', $alert->type)

@push('scripts')
    @vite(['resources/js/zones-alerts.js'])
@endpush

@section('content')
    @php
        $status = $alert->status();
        $statusTone = ['open' => 'warning', 'acknowledged' => 'info', 'resolved' => 'success'][$status] ?? 'info';
    @endphp

    <div class="aq-page">
        <section class="aq-hero">
            <div class="aq-hero__top">
                <div>
                    <span class="eyebrow">ALERTE</span>
                    <h1 class="aq-hero__title">{{ $alert->type }}</h1>
                    <ul class="aq-hero__meta">
                        <li><x-severity-badge :severity="$alert->severity" prefix="Gravité " /></li>
                        @if($alert->ai_severity)
                            <li><x-severity-badge :severity="$alert->ai_severity" :ai="true" /></li>
                        @endif
                        <li><span class="aq-status" data-status="{{ $status }}">{{ $alert->status_label }}</span></li>
                        <li>{{ $alert->read_at ? 'Lue le '.$alert->read_at->format('d/m/Y H:i') : 'Non lue' }}</li>
                        <li>◷ {{ $alert->created_at?->format('d/m/Y H:i') }}</li>
                    </ul>
                </div>

                <div class="aq-actions">
                    <button type="button" class="button-ai-scan" data-alert-ai-btn onclick="analyzeAlertAi()">⚡ Analyser avec l'IA</button>
                    <x-ui.button :href="route('back.alerts.edit', $alert)">Modifier</x-ui.button>
                    <x-ui.button :href="route('back.alerts.index')" variant="outline">Retour</x-ui.button>
                </div>
            </div>

            <div class="aq-hero__grid">
                <div class="aq-hero__kpi"><span>Zone</span><strong>{{ $alert->zone?->name ?? '—' }}</strong></div>
                <div class="aq-hero__kpi"><span>Incident lié</span><strong>{{ $alert->incident?->type ?? '—' }}</strong></div>
                <div class="aq-hero__kpi"><span>Gravité IA</span><strong>{{ $alert->ai_severity ? $alert->ai_severity_label : '—' }}</strong></div>
                <div class="aq-hero__kpi"><span>Statut</span><strong>{{ $alert->status_label }}</strong></div>
            </div>
        </section>

        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif

        {{-- Traitement de l'alerte (workflow) --}}
        <section class="aq-panel aq-section-gap" style="padding:20px">
            <div class="aq-toolbar-row" style="margin-bottom:10px">
                <h2 style="margin:0;font-size:16px">Traitement</h2>
                <span class="aq-status" data-status="{{ $status }}">{{ $alert->status_label }}</span>
            </div>

            <div class="aq-actions" style="margin-bottom:16px">
                @if($status !== 'acknowledged' && $status !== 'resolved')
                    <form method="POST" action="{{ route('back.alerts.acknowledge', $alert) }}">
                        @csrf
                        <x-ui.button type="submit" variant="outline">Acquitter</x-ui.button>
                    </form>
                @endif

                @if($status !== 'resolved')
                    <form method="POST" action="{{ route('back.alerts.resolve', $alert) }}">
                        @csrf
                        <x-ui.button type="submit">Marquer comme résolue</x-ui.button>
                    </form>
                @endif

                @if($status !== 'open')
                    <form method="POST" action="{{ route('back.alerts.reopen', $alert) }}">
                        @csrf
                        <x-ui.button type="submit" variant="outline">Réouvrir</x-ui.button>
                    </form>
                @endif

                <form method="POST" action="{{ route('back.alerts.destroy', $alert) }}"
                      data-confirm="Supprimer définitivement cette alerte ?">
                    @csrf @method('DELETE')
                    <button type="submit" class="button button-outline" style="border-color:#f2c9c3;color:#a8473d">Supprimer</button>
                </form>
            </div>

            <ol class="aq-timeline">
                @foreach($timeline as $step)
                    <li class="aq-timeline__item" data-state="{{ $step['done'] ? 'done' : 'pending' }}">
                        <span class="aq-timeline__dot" aria-hidden="true"></span>
                        <span class="aq-timeline__label">{{ $step['label'] }}</span>
                        <span class="aq-timeline__time">{{ $step['at'] ? $step['at']->format('d/m/Y H:i') : 'En attente' }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="aq-cardgrid aq-section-gap">
            <section class="aq-panel" style="padding:20px">
                <h2 style="margin-top:0;font-size:16px">Message</h2>
                <p class="aq-inline-note" style="font-size:14px;color:var(--ink)">{{ $alert->message }}</p>
                @if($alert->incident)
                    <p class="aq-inline-note" style="margin-top:12px">
                        Incident lié : <strong>{{ $alert->incident->type }}</strong> · {{ $alert->incident->status }}<br>
                        {{ $alert->incident->description }}
                    </p>
                @endif
            </section>

            <section class="aq-panel" style="padding:20px">
                <h2 style="margin-top:0;font-size:16px">Zone concernée</h2>
                @if($alert->zone)
                    <p class="aq-inline-note" style="font-size:14px;color:var(--ink)">
                        <a href="{{ route('back.zones.show', $alert->zone) }}"><strong>{{ $alert->zone->name }}</strong></a><br>
                        {{ $alert->zone->address }}<br>
                        Risque : <x-risk-badge :level="$alert->zone->risk_level" />
                    </p>
                @else
                    <p class="aq-inline-note">Aucune zone associée.</p>
                @endif
            </section>
        </div>

        @if($related->isNotEmpty())
            <section class="aq-panel aq-panel-pad aq-section-gap aq-scroll-x">
                <h2 style="margin:0;padding:18px 18px 0;font-size:16px">Autres alertes de la zone</h2>
                <table class="aq-table" style="width:100%">
                    <thead><tr><th scope="col">Alerte</th><th scope="col">Gravité</th><th scope="col">Date</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach($related as $item)
                            <tr>
                                <td><span class="aq-cell-primary">{{ $item->type }}</span><span class="aq-cell-sub">{{ \Illuminate\Support\Str::limit($item->message, 80) }}</span></td>
                                <td><x-severity-badge :severity="$item->severity" /></td>
                                <td>{{ $item->created_at?->format('d/m/Y H:i') }}</td>
                                <td><div class="aq-row-actions"><a href="{{ route('back.alerts.show', $item) }}">Voir</a></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        {{-- Analyse IA (gravité, explication, cause probable, actions, doublons) --}}
        @include('back.alerts._ai_panel', ['alert' => $alert])
    </div>
@endsection
