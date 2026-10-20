@extends('layouts.back')

@section('content')
<style>
    .intervention-page { max-width: 1440px; margin: 0 auto; }
    .intervention-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 26px; }
    .intervention-heading .eyebrow { display: inline-block; margin-bottom: 8px; color: #0d9488; font-size: 10px; font-weight: 800; letter-spacing: .14em; }
    .intervention-heading h1 { margin: 0; color: #073f4a; font-size: clamp(26px, 3vw, 34px); letter-spacing: -.045em; }
    .intervention-heading p { margin: 8px 0 0; color: #708388; font-size: 13px; }
    .intervention-add { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; box-shadow: 0 8px 18px #073f4a20; }
    .intervention-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
    .intervention-stat { display: flex; align-items: center; gap: 13px; min-height: 88px; padding: 17px 19px; background: #fff; border: 1px solid #e5eeec; border-radius: 12px; box-shadow: 0 3px 12px #153a3a08; }
    .intervention-stat-icon { display: grid; width: 46px; height: 46px; flex: 0 0 46px; place-items: center; border-radius: 12px; background: #e6f7f4; color: #0d9488; }
    .intervention-stat-icon svg { width: 24px; height: 24px; display: block; }
    .intervention-stat:nth-child(2) .intervention-stat-icon { background: #eaf7ef; color: #21835b; }
    .intervention-stat:nth-child(3) .intervention-stat-icon { background: #fff5df; color: #b7791f; }
    .intervention-stat:nth-child(4) .intervention-stat-icon { background: #f1edff; color: #7657c8; }
    /* Ciblage limité au bloc texte pour ne pas écraser le style de l'icône */
    .intervention-stat div strong, .intervention-stat div > span { display: block; }
    .intervention-stat strong { color: #073f4a; font-size: 22px; line-height: 1.1; }
    .intervention-stat div > span { margin-top: 4px; color: #708388; font-size: 11px; }
    .intervention-table-card { overflow: hidden; padding: 0; border: 1px solid #e4ecea; border-radius: 12px; box-shadow: 0 8px 24px #173c3c0a; }
    .intervention-table-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; border-bottom: 1px solid #e9efee; background: linear-gradient(115deg, #fff, #f8fcfb); }
    .intervention-table-top h2 { margin: 0; color: #073f4a; font-size: 15px; }
    .intervention-table-top p { margin: 3px 0 0; color: #829397; font-size: 11px; }
    .intervention-filters { display: flex; align-items: center; gap: 8px; padding: 14px 22px; border-bottom: 1px solid #edf1f0; background: #fff; }
    .intervention-filters select { min-width: 180px; height: 38px; padding: 0 30px 0 11px; border: 1px solid #dfe9e7; border-radius: 7px; background: #fff; color: #53696e; font: inherit; font-size: 11px; }
    .intervention-filters select:focus { border-color: #16b7a3; outline: none; box-shadow: 0 0 0 3px #16b7a326; }
    .intervention-filters button, .intervention-filter-reset { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 0 12px; border: 1px solid #0d6970; border-radius: 7px; background: #0d6970; color: #fff; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
    .intervention-filters button:hover { background: #084f56; }
    .intervention-filter-reset { border-color: #dfe9e7; background: #fff; color: #557176; }
    .intervention-filter-reset:hover { border-color: #9bd8cf; background: #effaf8; color: #087f74; }
    .intervention-table-wrap { overflow-x: auto; }
    .intervention-table { width: 100%; min-width: 960px; border-collapse: separate; border-spacing: 0; }
    .intervention-table th { padding: 13px 15px; background: #f7faf9; color: #829397; font-size: 9px; font-weight: 800; letter-spacing: .09em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .intervention-table td { padding: 13px 15px; border-top: 1px solid #edf1f0; color: #64777c; font-size: 11px; vertical-align: middle; }
    .intervention-table tbody tr { transition: background .15s ease; }
    .intervention-table tbody tr:hover { background: #f8fcfb; }
    .intervention-primary-link { color: #173c43; font-size: 12px; font-weight: 800; line-height: 1.45; }
    .intervention-primary-link:hover, .intervention-technician a:hover { color: #0d9488; }
    .intervention-secondary { display: block; margin-top: 3px; color: #829397; font-size: 10px; }
    .intervention-team { color: #53696e; font-weight: 700; }
    .intervention-technician a { color: #0d6970; font-weight: 700; }
    .intervention-schedule { color: #53696e; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .intervention-response { color: #708388; }
    .intervention-status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 9px; border-radius: 999px; font-size: 10px; font-weight: 800; white-space: nowrap; }
    .intervention-status:before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ''; }
    .intervention-status-planned { background: #effaf8; color: #087f74; }
    .intervention-status-in_progress { background: #fff4dd; color: #a66b12; }
    .intervention-status-completed { background: #e9f8ef; color: #21835b; }
    .intervention-status-cancelled { background: #fdecec; color: #ba4545; }
    .intervention-actions { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
    .intervention-actions a, .intervention-actions button { display: inline-flex; align-items: center; min-height: 30px; padding: 0 8px; border: 1px solid #e3ecea; border-radius: 6px; background: #fff; color: #557176; font: inherit; font-size: 10px; font-weight: 700; cursor: pointer; }
    .intervention-actions a:hover { border-color: #9bd8cf; background: #effaf8; color: #087f74; }
    .intervention-actions button:hover { border-color: #f2c7c2; background: #fff4f2; color: #b5473e; }
    .intervention-empty { padding: 54px 20px !important; color: #829397 !important; text-align: center; }
    .intervention-empty strong { display: block; margin-bottom: 5px; color: #173c43; font-size: 14px; }
    .intervention-pagination { margin-top: 18px; }
    @media(max-width: 900px) { .intervention-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media(max-width: 760px) { .intervention-heading { align-items: flex-start; flex-direction: column; } .intervention-add { width: 100%; justify-content: center; } .intervention-table-top { align-items: flex-start; flex-direction: column; } .intervention-filters { align-items: stretch; flex-direction: column; } .intervention-filters select, .intervention-filters button, .intervention-filter-reset { width: 100%; } }
    @media(max-width: 600px) { .intervention-stats { gap: 9px; } .intervention-stat { min-height: 76px; gap: 9px; padding: 12px; } .intervention-stat-icon { width: 38px; height: 38px; flex-basis: 38px; } .intervention-stat-icon svg { width: 20px; height: 20px; } .intervention-stat strong { font-size: 19px; } }
</style>

<div class="intervention-page">
    <div class="intervention-heading">
        <div>
            <span class="eyebrow">GESTION DES OPÉRATIONS</span>
            <h1>Interventions</h1>
        </div>
        <a class="button intervention-add" href="{{ route('interventions.create') }}"><span aria-hidden="true">＋</span> Nouvelle intervention</a>
    </div>

    <div class="intervention-stats" aria-label="Résumé des interventions">
        {{-- Interventions au total --}}
        <div class="intervention-stat">
            <span class="intervention-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    <line x1="9" y1="12" x2="15" y2="12"/>
                    <line x1="9" y1="16" x2="15" y2="16"/>
                </svg>
            </span>
            <div><strong>{{ $interventionStats['total'] }}</strong><span>Interventions au total</span></div>
        </div>

        {{-- Planifiées --}}
        <div class="intervention-stat">
            <span class="intervention-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </span>
            <div><strong>{{ $interventionStats['planned'] }}</strong><span>Planifiées</span></div>
        </div>

        {{-- En cours --}}
        <div class="intervention-stat">
            <span class="intervention-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polygon points="10 8 16 12 10 16 10 8"/>
                </svg>
            </span>
            <div><strong>{{ $interventionStats['in_progress'] }}</strong><span>En cours</span></div>
        </div>

        {{-- Terminées --}}
        <div class="intervention-stat">
            <span class="intervention-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </span>
            <div><strong>{{ $interventionStats['completed'] }}</strong><span>Terminées</span></div>
        </div>
    </div>

    <section class="panel intervention-table-card">
        <div class="intervention-table-top">
            <div>
                <h2>Suivi des interventions</h2>
            </div>
        </div>
        <form class="intervention-filters" method="GET" action="{{ route('interventions.index') }}" aria-label="Filtrer les interventions">
            <select name="status" aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ['planned' => 'Planifiée', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée'][$status] }}</option>
                @endforeach
            </select>
            <select name="technician_id" aria-label="Filtrer par technicien">
                <option value="">Tous les techniciens</option>
                @foreach($technicians as $technicianOption)
                    <option value="{{ $technicianOption->id }}" @selected((string) request('technician_id') === (string) $technicianOption->id)>{{ $technicianOption->name }}</option>
                @endforeach
            </select>
            <button type="submit">Filtrer</button>
            @if(request()->filled('status') || request()->filled('technician_id'))
                <a class="intervention-filter-reset" href="{{ route('interventions.index') }}">Effacer</a>
            @endif
        </form>
        <div class="intervention-table-wrap">
            <table class="intervention-table">
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Équipe</th>
                        <th>Technicien</th>
                        <th>Planification</th>
                        <th>Statut</th>
                        <th>Réponse</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($interventions as $intervention)
                        <tr>
                            <td>
                                <a class="intervention-primary-link" href="{{ route('interventions.show', $intervention) }}">Intervention #{{ $intervention->id }}</a>
                                <span class="intervention-secondary">Incident #{{ $intervention->incident_id }} · {{ $intervention->incident?->type ?? 'Incident supprimé' }}</span>
                            </td>
                            <td class="intervention-team">{{ $intervention->team }}</td>
                            <td class="intervention-technician">
                                @if($intervention->technician)
                                    <a href="{{ route('techniciens.show', $intervention->technician) }}">{{ $intervention->technician->name }}</a>
                                @else
                                    <span>Non affecté</span>
                                @endif
                            </td>
                            <td class="intervention-schedule">{{ $intervention->scheduled_at?->format('d/m/Y à H:i') ?? '—' }}</td>
                            <td><span class="intervention-status intervention-status-{{ $intervention->status }}">{{ ['planned' => 'Planifiée', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée'][$intervention->status] ?? $intervention->status }}</span></td>
                            <td class="intervention-response">{{ ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'refused' => 'Refusée'][$intervention->technician_response] ?? '—' }}</td>
                            <td>
                                <div class="intervention-actions">
                                    <a href="{{ route('interventions.show', $intervention) }}">Voir</a>
                                    <a href="{{ route('interventions.edit', $intervention) }}">Modifier</a>
                                    <form method="POST" action="{{ route('interventions.destroy', $intervention) }}" onsubmit="return confirm('Supprimer cette intervention ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="intervention-empty" colspan="7"><strong>Aucune intervention pour le moment</strong>Les interventions apparaîtront ici dès leur planification.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="intervention-pagination">{{ $interventions->links() }}</div>
</div>
@endsection