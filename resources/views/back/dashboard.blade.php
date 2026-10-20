@extends('layouts.back')

@section('content')
<div class="content-heading">
    <div><span class="eyebrow">DIMANCHE 04 OCTOBRE 2026 · 09:42</span><h1>Bonjour Claire <span>✦</span></h1><p>Voici l'état de votre réseau aujourd'hui.</p></div>
    <x-ui.button>Exporter le rapport ↓</x-ui.button>
</div>

<div class="kpi-grid">
    @foreach($kpis as $kpi)
        <div class="kpi"><span>{{ $kpi['label'] }}</span><strong>{{ $kpi['value'] }}</strong><small class="positive">↗ {{ $kpi['trend'] }} <i>vs. mois dernier</i></small></div>
    @endforeach
</div>
<div class="kpi-grid project-kpis">
    <div class="kpi"><span>Total projets</span><strong>{{ $projectStats['total'] }}</strong></div>
    <div class="kpi"><span>Projets en cours</span><strong>{{ $projectStats['in_progress'] }}</strong></div>
    <div class="kpi"><span>Projets terminés</span><strong>{{ $projectStats['completed'] }}</strong></div>
    <div class="kpi"><span>Total financements</span><strong>{{ number_format($projectStats['funding'], 0, ',', ' ') }} €</strong></div>
    <div class="kpi"><span>Reste à financer</span><strong>{{ number_format($projectStats['remaining'], 0, ',', ' ') }} €</strong></div>
</div>

<div class="dashboard-grid">
    <section class="panel chart-panel">
        <div class="panel-heading"><div><span class="eyebrow">MONITORING</span><h2>Activité du réseau</h2><p>Incidents signalés sur les 30 derniers jours</p></div><select><option>30 derniers jours</option><option>7 derniers jours</option></select></div>
        <div class="fake-chart"><div class="chart-line"></div><div class="chart-labels"><span>05 sept.</span><span>12 sept.</span><span>19 sept.</span><span>26 sept.</span><span>04 oct.</span></div></div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><span class="eyebrow">FLUX EN DIRECT</span><h2>Activité récente</h2></div><a href="{{ route('back.incidents') }}">Tout voir ↗</a></div>
        <ul class="activity-list">@foreach($activity as $item)<li><span class="activity-dot"></span><div><strong>{{ $item }}</strong><small>Il y a {{ $loop->iteration * 2 }} h</small></div></li>@endforeach</ul>
    </section>
</div>

<div class="dashboard-grid dashboard-grid-bottom">
    <section class="panel alert-panel">
        <div class="panel-heading"><div><span class="eyebrow">ACTION REQUISE</span><h2>Alertes prioritaires</h2></div><span class="live-indicator"><i></i> 3 ouvertes</span></div>
        <div class="alert-row"><span class="incident-mark">!</span><div><strong>Pression anormale · Secteur Nord</strong><small>Détectée il y a 18 min</small></div><b>Urgent</b></div>
        <div class="alert-row"><span class="incident-mark warning">↗</span><div><strong>Capteur A-204 hors ligne</strong><small>Dernière donnée il y a 42 min</small></div><b class="soft">À vérifier</b></div>
    </section>
    <section class="panel budget-panel">
        <div class="panel-heading"><div><span class="eyebrow">PORTEFEUILLE 2026</span><h2>Budget projets</h2></div><a href="{{ route('back.funding') }}">Détails ↗</a></div>
        <div class="budget-total"><strong>2,48 M€</strong><span>sur 3,10 M€ engagés</span></div><div class="progress"><i style="width:80%"></i></div><div class="budget-meta"><span>80% consommé</span><span>620 k€ disponibles</span></div>
    </section>
</div>
@endsection
