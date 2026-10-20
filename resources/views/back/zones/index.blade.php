@extends('layouts.back')

@section('content')
@php($riskBadge = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger'])
<div class="content-heading">
    <div><span class="eyebrow">SURVEILLANCE</span><h1>Zones</h1><p>Gérez les zones surveillées et leur niveau de risque.</p></div>
    <x-ui.button :href="route('back.zones.create')">+ Ajouter une zone</x-ui.button>
</div>

@if(session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif
@if(session('error'))<x-alert type="danger">{{ session('error') }}</x-alert>@endif

<form method="GET" class="toolbar">
    <input class="search" name="q" value="{{ request('q') }}" placeholder="⌕  Rechercher une zone...">
    <select name="risk" onchange="this.form.submit()">
        <option value="">Tous les niveaux</option>
        @foreach(\App\Models\Zone::RISK_LEVELS as $value => $label)
            <option value="{{ $value }}" @selected(request('risk') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</form>

<section class="panel table-wrap">
    <table>
        <thead><tr><th>Zone</th><th>Adresse</th><th>Risque</th><th>Infrastructures</th><th>Incidents</th><th></th></tr></thead>
        <tbody>
        @forelse($zones as $zone)
            <tr>
                <td><strong>{{ $zone->name }}</strong></td>
                <td>{{ $zone->address }}</td>
                <td><x-ui.badge :variant="$riskBadge[$zone->risk_level] ?? 'info'">{{ $zone->risk_label }}</x-ui.badge></td>
                <td>{{ $zone->infrastructures_count }}</td>
                <td>{{ $zone->incidents_count }}</td>
                <td style="white-space:nowrap">
                    <a href="{{ route('back.zones.show', $zone) }}">Voir</a> ·
                    <a href="{{ route('back.zones.edit', $zone) }}">Modifier</a> ·
                    <form method="POST" action="{{ route('back.zones.destroy', $zone) }}" style="display:inline"
                          onsubmit="return confirm('Supprimer la zone « {{ $zone->name }} » ?')">
                        @csrf @method('DELETE')
                        <button type="submit" style="border:0;background:none;color:#bd5149;cursor:pointer;font:inherit">Supprimer</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">Aucune zone trouvée.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
