@extends('layouts.back')

@section('content')
@php($riskBadge = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger'])
<div class="content-heading">
    <div>
        <span class="eyebrow">ZONE</span>
        <h1>{{ $zone->name }}</h1>
        <p>{{ $zone->address }} · <x-ui.badge :variant="$riskBadge[$zone->risk_level] ?? 'info'">Risque {{ $zone->risk_label }}</x-ui.badge></p>
    </div>
    <div class="actions">
        <x-ui.button :href="route('back.zones.edit', $zone)">Modifier</x-ui.button>
        <x-ui.button :href="route('back.zones.index')" variant="outline">Retour</x-ui.button>
    </div>
</div>

@if(session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif

@if($zone->risk_level === 'high')
    <x-alert type="danger">⚠ Niveau de risque élevé dans cette zone.</x-alert>
@endif

<section class="panel" style="margin-bottom:20px">
    <div class="panel-heading"><h2>Description</h2></div>
    <p>{{ $zone->description ?: 'Aucune description.' }}</p>
</section>

<section class="panel" style="margin-bottom:20px">
        <div class="panel-heading"><h2>Alertes ({{ $zone->alerts->count() }})</h2>
            <x-ui.button :href="route('back.alerts.create', ['zone_id' => $zone->id])" variant="outline">+ Ajouter une alerte</x-ui.button>
        </div>
        <ul class="activity-list">
            @forelse($zone->alerts as $alert)
                <li><span class="activity-dot"></span><span><strong><a href="{{ route('back.alerts.show', $alert) }}">{{ $alert->type }}</a> · {{ $alert->severity_label }}</strong><small>{{ $alert->message }} — {{ $alert->created_at->diffForHumans() }}</small></span></li>
        @empty
            <li>Aucune alerte.</li>
        @endforelse
    </ul>
</section>

<div class="dashboard-grid" style="grid-template-columns:1fr 1fr">
    <section class="panel table-wrap">
        <div class="panel-heading" style="padding:25px 25px 0"><h2>Infrastructures ({{ $zone->infrastructures->count() }})</h2></div>
        <table><thead><tr><th>Nom</th><th>Type</th><th>Statut</th></tr></thead><tbody>
            @forelse($zone->infrastructures as $infra)
                <tr><td><strong>{{ $infra->name }}</strong></td><td>{{ $infra->type }}</td><td>{{ $infra->status }}</td></tr>
            @empty
                <tr><td colspan="3">Aucune infrastructure.</td></tr>
            @endforelse
        </tbody></table>
    </section>

    <section class="panel table-wrap">
        <div class="panel-heading" style="padding:25px 25px 0"><h2>Incidents ({{ $zone->incidents->count() }})</h2></div>
        <table><thead><tr><th>Type</th><th>Description</th><th>Statut</th><th>Date</th></tr></thead><tbody>
            @forelse($zone->incidents as $incident)
                <tr><td><strong>{{ $incident->type }}</strong></td><td>{{ $incident->description }}</td><td>{{ $incident->status }}</td><td>{{ $incident->reported_at?->format('d/m/Y H:i') }}</td></tr>
            @empty
                <tr><td colspan="4">Aucun incident.</td></tr>
            @endforelse
        </tbody></table>
    </section>
</div>
@endsection
