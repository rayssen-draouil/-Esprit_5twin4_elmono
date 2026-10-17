@extends('layouts.back', ['title' => 'Mes interventions'])

@section('content')
<div class="content-heading">
    <div>
        <span class="eyebrow">ESPACE OPÉRATIONNEL TECHNICIEN</span>
        <h1>Mes interventions</h1>
        <p>Consultez vos ordres de mission assignés, exécutez vos interventions et soumettez vos comptes-rendus techniques.</p>
    </div>
</div>

@if(session('success'))
    <div style="background: #def5ed; border-left: 4px solid #13886f; color: #13886f; padding: 14px 20px; border-radius: 6px; margin-bottom: 20px; font-weight: 600;">
        ✓ {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div style="background: #ffe4e1; border-left: 4px solid #bd5149; color: #bd5149; padding: 14px 20px; border-radius: 6px; margin-bottom: 20px; font-weight: 600;">
        ⚠ {{ session('error') }}
    </div>
@endif

{{-- Filter Tabs --}}
<div class="filter-tabs" style="margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 8px;">
    <a href="{{ route('technicien.interventions.index', ['status' => 'all']) }}"
       style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; border: 1px solid var(--line); {{ $filter === 'all' ? 'background: #073f4a; color: #fff; border-color: #073f4a;' : 'background: #fff; color: var(--muted);' }}">
        Toutes ({{ $stats['all'] }})
    </a>

    <a href="{{ route('technicien.interventions.index', ['status' => 'in_progress']) }}"
       style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; border: 1px solid var(--line); {{ $filter === 'in_progress' ? 'background: #073f4a; color: #fff; border-color: #073f4a;' : 'background: #fff; color: var(--muted);' }}">
        ⚡ En cours ({{ $stats['in_progress'] }})
    </a>

    <a href="{{ route('technicien.interventions.index', ['status' => 'awaiting_validation']) }}"
       style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; border: 1px solid var(--line); {{ $filter === 'awaiting_validation' ? 'background: #073f4a; color: #fff; border-color: #073f4a;' : 'background: #fff; color: var(--muted);' }}">
        ⏳ En attente de validation ({{ $stats['awaiting_validation'] }})
    </a>

    <a href="{{ route('technicien.interventions.index', ['status' => 'planned']) }}"
       style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; border: 1px solid var(--line); {{ $filter === 'planned' ? 'background: #073f4a; color: #fff; border-color: #073f4a;' : 'background: #fff; color: var(--muted);' }}">
        📅 Planifiées ({{ $stats['planned'] }})
    </a>

    <a href="{{ route('technicien.interventions.index', ['status' => 'completed']) }}"
       style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; border: 1px solid var(--line); {{ $filter === 'completed' ? 'background: #073f4a; color: #fff; border-color: #073f4a;' : 'background: #fff; color: var(--muted);' }}">
        ✓ Terminées & Validées ({{ $stats['completed'] }})
    </a>
</div>

{{-- Interventions List --}}
<div class="panel table-wrap">
    <table>
        <thead>
            <tr>
                <th>Référence / Opération</th>
                <th>Infrastructure</th>
                <th>Priorité</th>
                <th>Planification</th>
                <th>Statut</th>
                <th>Verrouillage</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($maintenances as $item)
                <tr>
                    <td>
                        <a href="{{ route('technicien.interventions.show', $item) }}" style="font-weight: 700; color: #073f4a;">
                            {{ $item->reference_code ?? ('MNT-#' . $item->id) }}
                        </a>
                        <small style="color: var(--muted); display: block;">{{ $item->type }}</small>
                    </td>

                    <td>
                        <strong>{{ $item->infrastructure?->name ?? 'Non définie' }}</strong>
                        <small style="color: var(--muted); display: block;">{{ $item->infrastructure?->zone?->name ?? 'Zone' }}</small>
                    </td>

                    <td>
                        <span class="badge {{ $item->priority_badge_class }}">
                            {{ $item->priority_label }}
                        </span>
                    </td>

                    <td>
                        <span>{{ $item->scheduled_at ? $item->scheduled_at->format('d/m/Y H:i') : 'Non définie' }}</span>
                        <small style="color: var(--muted);">{{ $item->duration_hours ? $item->duration_hours . 'h estimées' : '—' }}</small>
                    </td>

                    <td>
                        <span class="badge {{ $item->status_badge_class }}">
                            {{ $item->display_status_label }}
                        </span>
                    </td>

                    <td>
                        @if($item->technician_assignment_locked)
                            <span style="font-size: 11px; color: #13886f; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                🔒 Affecté & Verrouillé
                            </span>
                        @else
                            <span style="font-size: 11px; color: #aa721c; font-weight: 600;">
                                ⌛ Non verrouillé
                            </span>
                        @endif
                    </td>

                    <td class="table-actions">
                        <a href="{{ route('technicien.interventions.show', $item) }}" class="button button-small" style="background: #073f4a;">
                            Ouvrir l'intervention →
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                        Aucune intervention ne correspond à ce filtre actuellement.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    {{ $maintenances->links() }}
</div>
@endsection
