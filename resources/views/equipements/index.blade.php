@extends('layouts.back')

@section('content')
<style>
    .equipment-page { max-width: 1440px; margin: 0 auto; }
    .equipment-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 26px; }
    .equipment-heading .eyebrow { display: inline-block; margin-bottom: 8px; color: #0d9488; font-size: 10px; font-weight: 800; letter-spacing: .14em; }
    .equipment-heading h1 { margin: 0; color: #073f4a; font-size: clamp(26px, 3vw, 34px); letter-spacing: -.045em; }
    .equipment-heading p { margin: 8px 0 0; color: #708388; font-size: 13px; }
    .equipment-add { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; box-shadow: 0 8px 18px #073f4a20; }
    .equipment-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
    .equipment-stat { display: flex; align-items: center; gap: 13px; min-height: 88px; padding: 17px 19px; background: #fff; border: 1px solid #e5eeec; border-radius: 12px; box-shadow: 0 3px 12px #153a3a08; }
    .equipment-stat-icon { display: grid; width: 46px; height: 46px; flex: 0 0 46px; place-items: center; border-radius: 12px; background: #e6f7f4; color: #0d9488; }
    .equipment-stat-icon svg { width: 24px; height: 24px; display: block; }
    .equipment-stat:nth-child(2) .equipment-stat-icon { background: #eaf7ef; color: #21835b; }
    .equipment-stat:nth-child(3) .equipment-stat-icon { background: #fff5df; color: #b7791f; }
    .equipment-stat:nth-child(4) .equipment-stat-icon { background: #f1edff; color: #7657c8; }
    /* Ciblage limité au bloc texte pour ne pas écraser le style de l'icône */
    .equipment-stat div strong, .equipment-stat div > span { display: block; }
    .equipment-stat strong { color: #073f4a; font-size: 22px; line-height: 1.1; }
    .equipment-stat div > span { margin-top: 4px; color: #708388; font-size: 11px; }
    .equipment-table-card { overflow: hidden; padding: 0; border: 1px solid #e4ecea; border-radius: 12px; box-shadow: 0 8px 24px #173c3c0a; }
    .equipment-table-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 22px; border-bottom: 1px solid #e9efee; background: linear-gradient(115deg, #fff, #f8fcfb); }
    .equipment-table-top h2 { margin: 0; color: #073f4a; font-size: 15px; }
    .equipment-table-top p { margin: 3px 0 0; color: #829397; font-size: 11px; }
    .equipment-search { display: flex; flex: 1; align-items: center; justify-content: flex-end; gap: 8px; margin-left: auto; }
    .equipment-search input { width: min(380px, 40vw); min-width: 180px; height: 38px; padding: 0 12px; border: 1px solid #dfe9e7; border-radius: 7px; outline: none; background: #fff; color: #173c43; font: inherit; font-size: 11px; }
    .equipment-search input:focus { border-color: #16b7a3; box-shadow: 0 0 0 3px #16b7a326; }
    .equipment-search button, .equipment-search-reset { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 0 12px; border: 1px solid #dfe9e7; border-radius: 7px; background: #fff; color: #557176; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
    .equipment-search button { border-color: #0d6970; background: #0d6970; color: #fff; }
    .equipment-search button:hover { background: #084f56; }
    .equipment-search-reset:hover { border-color: #9bd8cf; background: #effaf8; color: #087f74; }
    .equipment-table-wrap { overflow-x: auto; }
    .equipment-table { width: 100%; min-width: 940px; border-collapse: separate; border-spacing: 0; }
    .equipment-table th { padding: 13px 15px; background: #f7faf9; color: #829397; font-size: 9px; font-weight: 800; letter-spacing: .09em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .equipment-table td { padding: 13px 15px; border-top: 1px solid #edf1f0; color: #64777c; font-size: 11px; vertical-align: middle; }
    .equipment-table tbody tr { transition: background .15s ease; }
    .equipment-table tbody tr:hover { background: #f8fcfb; }
    .equipment-image { display: grid; width: 58px; height: 58px; place-items: center; overflow: hidden; border: 1px solid #e6eeec; border-radius: 9px; background: linear-gradient(145deg, #f8fbfa, #eef5f3); }
    .equipment-image img { width: 100%; height: 100%; object-fit: cover; }
    .equipment-image-placeholder { color: #afbfbd; font-size: 18px; }
    .equipment-name { color: #173c43; font-size: 12px; font-weight: 800; line-height: 1.45; }
    .equipment-name:hover { color: #0d9488; }
    .equipment-type { display: inline-block; padding: 4px 8px; border-radius: 6px; background: #f2f7f6; color: #60777b; font-size: 10px; }
    .equipment-serial { color: #829397; font-variant-numeric: tabular-nums; }
    .equipment-status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 9px; border-radius: 999px; font-size: 10px; font-weight: 800; white-space: nowrap; }
    .equipment-status:before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ''; }
    .equipment-status-available { background: #e9f8ef; color: #21835b; }
    .equipment-status-in_use { background: #fff4dd; color: #a66b12; }
    .equipment-status-maintenance { background: #fff0e8; color: #bc5c2c; }
    .equipment-status-out_of_service { background: #fdecec; color: #ba4545; }
    .equipment-reservation { min-width: 170px; max-width: 250px; color: #53696e; line-height: 1.5; }
    .equipment-reservation strong { color: #173c43; }
    .equipment-reservation a { color: #0d9488; font-weight: 700; }
    .equipment-reservation small { display: block; margin-top: 2px; color: #8a9b9e; font-size: 10px; }
    .equipment-actions { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
    .equipment-actions a, .equipment-actions button { display: inline-flex; align-items: center; min-height: 30px; padding: 0 8px; border: 1px solid #e3ecea; border-radius: 6px; background: #fff; color: #557176; font: inherit; font-size: 10px; font-weight: 700; cursor: pointer; }
    .equipment-actions a:hover { border-color: #9bd8cf; background: #effaf8; color: #087f74; }
    .equipment-actions button:hover { border-color: #f2c7c2; background: #fff4f2; color: #b5473e; }
    .equipment-empty { padding: 54px 20px !important; color: #829397 !important; text-align: center; }
    .equipment-empty strong { display: block; margin-bottom: 5px; color: #173c43; font-size: 14px; }
    .equipment-pagination { margin-top: 18px; }
    @media(max-width: 900px) { .equipment-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media(max-width: 600px) { .equipment-heading { align-items: flex-start; flex-direction: column; } .equipment-add { width: 100%; justify-content: center; } .equipment-stats { gap: 9px; } .equipment-stat { min-height: 76px; gap: 9px; padding: 12px; } .equipment-stat-icon { width: 38px; height: 38px; flex-basis: 38px; } .equipment-stat-icon svg { width: 20px; height: 20px; } .equipment-stat strong { font-size: 19px; } }
    @media(max-width: 760px) { .equipment-table-top { align-items: flex-start; flex-direction: column; } .equipment-search { width: 100%; } .equipment-search input { width: 100%; min-width: 0; flex: 1; } }
</style>

<div class="equipment-page">
    <div class="equipment-heading">
        <div>
            <span class="eyebrow">GESTION DU MATÉRIEL</span>
            <h1>Équipements</h1>
        </div>
        <a class="button equipment-add" href="{{ route('equipements.create') }}"><span aria-hidden="true">＋</span> Ajouter un équipement</a>
    </div>

    <div class="equipment-stats" aria-label="Résumé de l’inventaire">
        {{-- Équipements au total --}}
        <div class="equipment-stat">
            <span class="equipment-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
            </span>
            <div><strong>{{ $inventoryStats['total'] }}</strong><span>Équipements au total</span></div>
        </div>

        {{-- Disponibles --}}
        <div class="equipment-stat">
            <span class="equipment-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </span>
            <div><strong>{{ $inventoryStats['available'] }}</strong><span>Disponibles</span></div>
        </div>

        {{-- En service --}}
        <div class="equipment-stat">
            <span class="equipment-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
            </span>
            <div><strong>{{ $inventoryStats['in_use'] }}</strong><span>En service</span></div>
        </div>

        {{-- Maintenance / hors service --}}
        <div class="equipment-stat">
            <span class="equipment-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </span>
            <div><strong>{{ $inventoryStats['maintenance'] }}</strong><span>Maintenance / hors service</span></div>
        </div>
    </div>

    <section class="panel equipment-table-card">
        <div class="equipment-table-top">
            <div>
                <h2>Inventaire des équipements</h2>
            </div>
            <form class="equipment-search" method="GET" action="{{ route('equipements.index') }}" role="search">
                <input id="equipment-search" type="search" name="search" value="{{ $search }}" placeholder="Nom, type, numéro de série..." aria-label="Rechercher un équipement par nom, type, numéro de série ou statut">
                <button type="submit">Rechercher</button>
                @if($search !== '')
                    <a class="equipment-search-reset" href="{{ route('equipements.index') }}">Effacer</a>
                @endif
            </form>
        </div>
        <div class="equipment-table-wrap">
            <table class="equipment-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Équipement</th>
                        <th>Type</th>
                        <th>N° de série</th>
                        <th>Inventaire</th>
                        <th>Statut</th>
                        <th>Disponibilité planifiée</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($equipements as $equipement)
                        @php($reservation = $equipement->interventions->first())
                        <tr>
                            <td>
                                <div class="equipment-image">
                                    @if($equipement->image_path)
                                        <img src="{{ asset('storage/'.$equipement->image_path) }}" alt="Image de {{ $equipement->name }}" loading="lazy">
                                    @else
                                        <span class="equipment-image-placeholder" aria-label="Aucune image">▧</span>
                                    @endif
                                </div>
                            </td>
                            <td><a class="equipment-name" href="{{ route('equipements.show', $equipement) }}">{{ $equipement->name }}</a></td>
                            <td><span class="equipment-type">{{ $equipement->type }}</span></td>
                            <td class="equipment-serial">{{ $equipement->serial_number ?: '—' }}</td>
                            <td>Commun</td>
                            <td><span class="equipment-status equipment-status-{{ $equipement->status }}">{{ ['available' => 'Disponible', 'in_use' => 'En service', 'maintenance' => 'En maintenance', 'out_of_service' => 'Hors service'][$equipement->status] ?? $equipement->status }}</span></td>
                            <td class="equipment-reservation">
                                @if($reservation)
                                    Réservé par <strong>{{ $reservation->technician?->name ?? 'un technicien' }}</strong>
                                    · <a href="{{ route('interventions.show', $reservation) }}">Intervention #{{ $reservation->id }}</a>
                                    <small>{{ $reservation->scheduled_at?->format('d/m/Y à H:i') ?? 'Date non définie' }}</small>
                                @elseif($equipement->status === 'available')
                                    Disponible pour planification
                                @else
                                    Indisponible pour planification
                                @endif
                            </td>
                            <td>
                                <div class="equipment-actions">
                                    <a href="{{ route('equipements.show', $equipement) }}">Voir</a>
                                    <a href="{{ route('equipements.edit', $equipement) }}">Modifier</a>
                                    <form method="POST" action="{{ route('equipements.destroy', $equipement) }}" onsubmit="return confirm('Supprimer cet équipement ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="equipment-empty" colspan="8"><strong>Aucun équipement pour le moment</strong>Ajoutez un équipement pour commencer à gérer votre inventaire.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="equipment-pagination">{{ $equipements->links() }}</div>
</div>
@endsection
