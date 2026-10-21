@extends('layouts.front', ['title' => 'Nos opérations'])

@section('content')
<style>
    .operations-status-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .operations-status-card { min-height: 150px; padding: 22px; border: 1px solid #e5eeec; border-radius: 14px; background: #fff; box-shadow: 0 8px 24px #173c3c0a; }
    .operations-status-card h3 { margin: 10px 0 2px; color: #073f4a; font-size: 32px; line-height: 1.1; }
    .operations-status-card p { margin: 6px 0 0; color: #708388; font-size: 13px; }
    .operations-period-note { margin: 14px 0 0; color: #829397; font-size: 12px; }
    .operations-live-card { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 28px; overflow: hidden; border: 1px solid #ccebe5; border-radius: 16px; background: linear-gradient(120deg, #eaf9f6, #f7fcfb 72%); }
    .operations-live-copy { max-width: 620px; }
    .operations-live-copy h2 { margin: 8px 0; color: #073f4a; font-size: clamp(22px, 3vw, 30px); }
    .operations-live-copy p { margin: 0; color: #617a7e; font-size: 14px; }
    .operations-live-number { flex: 0 0 auto; color: #087f74; font-size: clamp(42px, 6vw, 64px); font-weight: 800; line-height: 1; }
    .operations-live-links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .operations-live-links a { display: inline-flex; align-items: center; min-height: 38px; padding: 0 13px; border: 1px solid #b9e3dc; border-radius: 8px; background: #fff; color: #087f74; font-size: 12px; font-weight: 700; }
    .operations-live-links a:hover { border-color: #0d9488; background: #f4fffc; }
    .operations-category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .operations-specialty-card { display: flex; align-items: center; gap: 14px; min-height: 104px; padding: 18px; border: 1px solid #e5eeec; border-radius: 12px; background: #fff; box-shadow: 0 4px 16px #173c3c08; }
    .operations-specialty-icon { display: grid; width: 42px; height: 42px; flex: 0 0 42px; place-items: center; border-radius: 11px; background: #e6f7f4; color: #0d9488; }
    .operations-specialty-icon svg { display: block; width: 21px; height: 21px; }
    .operations-specialty-card h3, .operations-category-card h3 { margin: 0 0 5px; color: #173c43; font-size: 14px; }
    .operations-specialty-card p, .operations-category-card p { margin: 0; color: #829397; font-size: 12px; line-height: 1.5; }
    .operations-category-card { overflow: hidden; border: 1px solid #e5eeec; border-radius: 12px; background: #fff; box-shadow: 0 4px 16px #173c3c08; }
    .operations-category-image { position: relative; height: 180px; overflow: hidden; background: linear-gradient(145deg, #f8fbfa, #eef5f3); }
    .operations-category-image img { position: absolute; inset: 14px; display: block; width: calc(100% - 28px); height: calc(100% - 28px); object-fit: contain; }
    .operations-category-copy { min-height: 82px; padding: 16px 18px 18px; background: #fff; }
    @media(max-width: 720px) { .operations-status-grid { grid-template-columns: 1fr; } .operations-live-card { align-items: flex-start; padding: 22px; } .operations-live-number { font-size: 44px; } }
    @media(max-width: 480px) { .operations-live-card { flex-direction: column-reverse; gap: 12px; } }
</style>

<section class="page-hero compact">
    <span class="eyebrow">TRANSPARENCE DU SERVICE</span>
    <h1>Nos opérations<br><em>sur le réseau.</em></h1>
    <p>Découvrez l’activité générale de nos équipes, leurs domaines d’expertise et les catégories de matériel mobilisées.</p>
    <x-ui.button :href="route('front.incidents.create')">Signaler un incident ↗</x-ui.button>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <span class="eyebrow">SUIVI GLOBAL</span>
            <h2>Activité des 30 derniers jours</h2>
        </div>
    </div>
    <div class="operations-status-grid">
        @foreach($operationStatuses as $status)
            <article class="operations-status-card" data-status="{{ $status['key'] }}">
                <span class="eyebrow">{{ $status['label'] }}</span>
                <h3>{{ $status['count'] }}</h3>
                <p>{{ $status['description'] }}</p>
            </article>
        @endforeach
    </div>
    <p class="operations-period-note">
        Nombre d’interventions créées du {{ $periodStart->format('d/m/Y') }} au {{ $periodEnd->format('d/m/Y') }}, classées selon leur statut actuel.
        Données actualisées le {{ $periodEnd->format('d/m/Y à H:i') }}.
    </p>
</section>

<section class="section">
    <div class="operations-live-card">
        <div class="operations-live-copy">
            <span class="eyebrow">SITUATION ACTUELLE</span>
            <h2>Interventions en cours maintenant</h2>
            <p>Nos équipes suivent actuellement {{ $activeInterventionsCount }} intervention{{ $activeInterventionsCount === 1 ? '' : 's' }} sur le réseau.</p>
            <div class="operations-live-links">
                <a href="{{ route('front.incidents.index') }}">Consulter les signalements ↗</a>
                <a href="{{ route('front.zones.index') }}">Voir les zones ↗</a>
            </div>
        </div>
        <div class="operations-live-number" aria-label="{{ $activeInterventionsCount }} interventions en cours">{{ $activeInterventionsCount }}</div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <span class="eyebrow">ÉQUIPE</span>
            <h2>Domaines d’expertise</h2>
        </div>
    </div>
    <div class="operations-category-grid">
        @forelse($specialties as $specialty)
            <article class="operations-specialty-card">
                <span class="operations-specialty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.7 2.94-.08-.02a1.7 1.7 0 0 0-1.72.74l-.04.07h-3.4l-.04-.07a1.7 1.7 0 0 0-1.72-.74l-.08.02-1.7-2.94.06-.06A1.7 1.7 0 0 0 9.72 15l-.08-.04v-3.4l.08-.04a1.7 1.7 0 0 0-.34-1.88l-.06-.06 1.7-2.94.08.02a1.7 1.7 0 0 0 1.72-.74l.04-.07h3.4l.04.07a1.7 1.7 0 0 0 1.72.74l.08-.02 1.7 2.94-.06.06a1.7 1.7 0 0 0-.34 1.88l.08.04v3.4z" transform="translate(-2 -2)"/></svg>
                </span>
                <div><h3>{{ $specialty }}</h3></div>
            </article>
        @empty
            <p class="panel" style="padding:24px">Les domaines d’expertise seront affichés lorsque des profils techniciens approuvés seront renseignés.</p>
        @endforelse
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <span class="eyebrow">MOYENS D’INTERVENTION</span>
            <h2>Catégories d’équipements</h2>
        </div>
    </div>
    <div class="operations-category-grid">
        @forelse($equipmentTypes as $type)
            <article class="operations-category-card">
                @if($type['image'])
                    <div class="operations-category-image">
                        <img src="{{ asset($type['image']) }}" alt="{{ $type['name'] }}" loading="lazy">
                    </div>
                @endif
                <div class="operations-category-copy">
                    <h3>{{ $type['name'] }}</h3>
                </div>
            </article>
        @empty
            <p class="panel" style="padding:24px">Les catégories d’équipements seront affichées lorsque du matériel sera enregistré dans l’inventaire commun.</p>
        @endforelse
    </div>
</section>
@endsection
