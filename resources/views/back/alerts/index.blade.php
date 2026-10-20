@extends('layouts.back')

@section('content')
@php($severityBadge = ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'])
<div class="content-heading">
    <div><span class="eyebrow">SURVEILLANCE</span><h1>Alertes</h1><p>Suivez les alertes générées par les zones et les incidents.</p></div>
    <x-ui.button :href="route('back.alerts.create')">+ Créer une alerte</x-ui.button>
</div>

@if(session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif
@if(session('error'))<x-alert type="danger">{{ session('error') }}</x-alert>@endif

<form method="GET" class="toolbar">
    <input class="search" name="q" value="{{ request('q') }}" placeholder="⌕  Rechercher un type ou un message...">
    <select name="severity" onchange="this.form.submit()">
        <option value="">Toutes les gravités</option>
        @foreach(\App\Models\Alert::SEVERITIES as $value => $label)
            <option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="zone" onchange="this.form.submit()">
        <option value="">Toutes les zones</option>
        @foreach($zones as $zone)
            <option value="{{ $zone->id }}" @selected((string) request('zone') === (string) $zone->id)>{{ $zone->name }}</option>
        @endforeach
    </select>
</form>

<section class="panel table-wrap">
    <table>
        <thead><tr><th>Alerte</th><th>Message</th><th>Zone</th><th>Gravité</th><th>État</th><th>Date</th><th></th></tr></thead>
        <tbody>
        @forelse($alerts as $alert)
            <tr>
                <td><strong>{{ $alert->type }}</strong></td>
                <td>{{ \Illuminate\Support\Str::limit($alert->message, 70) }}</td>
                <td>
                    @if($alert->zone)
                        <a href="{{ route('back.zones.show', $alert->zone) }}">{{ $alert->zone->name }}</a>
                    @else
                        —
                    @endif
                </td>
                <td><x-ui.badge :variant="$severityBadge[$alert->severity] ?? 'info'">{{ $alert->severity_label }}</x-ui.badge></td>
                <td>{{ $alert->read_at ? 'Lu' : 'Non lu' }}</td>
                <td>{{ $alert->created_at?->format('d/m/Y H:i') }}</td>
                <td style="white-space:nowrap">
                    <a href="{{ route('back.alerts.show', $alert) }}">Voir</a> ·
                    <a href="{{ route('back.alerts.edit', $alert) }}">Modifier</a> ·
                    <form method="POST" action="{{ route('back.alerts.destroy', $alert) }}" style="display:inline"
                          onsubmit="return confirm('Supprimer cette alerte ?')">
                        @csrf @method('DELETE')
                        <button type="submit" style="border:0;background:none;color:#bd5149;cursor:pointer;font:inherit">Supprimer</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">Aucune alerte trouvée.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
