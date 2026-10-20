@extends('layouts.back')

@section('content')
@php($severityBadge = ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'])
<div class="content-heading">
    <div>
        <span class="eyebrow">ALERTE</span>
        <h1>{{ $alert->type }}</h1>
        <p>
            <x-ui.badge :variant="$severityBadge[$alert->severity] ?? 'info'">Gravité {{ $alert->severity_label }}</x-ui.badge>
            {{ $alert->read_at ? '· Lu le '.$alert->read_at->format('d/m/Y H:i') : '· Non lue' }}
        </p>
    </div>
    <div class="actions">
        <x-ui.button :href="route('back.alerts.edit', $alert)">Modifier</x-ui.button>
        <x-ui.button :href="route('back.alerts.index')" variant="outline">Retour</x-ui.button>
    </div>
</div>

@if(session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif

<section class="panel" style="margin-bottom:20px">
    <div class="panel-heading"><h2>Message</h2></div>
    <p>{{ $alert->message }}</p>
</section>

<div class="dashboard-grid" style="grid-template-columns:1fr 1fr">
    <section class="panel">
        <div class="panel-heading"><h2>Zone</h2></div>
        @if($alert->zone)
            <p><a href="{{ route('back.zones.show', $alert->zone) }}"><strong>{{ $alert->zone->name }}</strong></a> · {{ $alert->zone->address }}</p>
            <p>Risque : {{ $alert->zone->risk_label }}</p>
        @else
            <p>Aucune zone associée.</p>
        @endif
    </section>

    <section class="panel">
        <div class="panel-heading"><h2>Incident lié</h2></div>
        @if($alert->incident)
            <p><strong>{{ $alert->incident->type }}</strong> · {{ $alert->incident->status }}</p>
            <p>{{ $alert->incident->description }}</p>
        @else
            <p>Aucun incident associé.</p>
        @endif
    </section>
</div>

<section class="panel" style="margin-top:20px">
    <div class="activity-list">
        <form method="POST" action="{{ route('back.alerts.destroy', $alert) }}"
              onsubmit="return confirm('Supprimer cette alerte ?')">
            @csrf @method('DELETE')
            <x-ui.button type="submit" variant="outline">Supprimer l'alerte</x-ui.button>
        </form>
    </div>
</section>
@endsection
