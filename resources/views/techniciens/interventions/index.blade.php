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

<section class="panel table-wrap" style="margin-top: 32px;">
    <div class="panel-heading">
        <div>
            <span class="eyebrow">INCIDENTS</span>
            <h2>Mes Interventions</h2>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Incident</th>
                <th>Équipe</th>
                <th>Planification</th>
                <th>Statut</th>
                <th>Réponse / équipements</th>
            </tr>
        </thead>
        <tbody>
            @forelse($assignedInterventions as $intervention)
                <tr>
                    <td>
                        @if($intervention->incident)
                            <a href="{{ route('front.incidents.show', $intervention->incident) }}">
                                #{{ $intervention->incident->id }} · {{ $intervention->incident->type }}
                            </a>
                            <small style="color: var(--muted); display: block;">
                                {{ $intervention->incident->location ?: 'Lieu non précisé' }}
                            </small>
                            @if($intervention->work_plan || $intervention->safety_precautions)
                                <details style="margin-top:8px">
                                    <summary>Plan et consignes</summary>
                                    <small style="display:block;margin-top:4px">Priorité : {{ ['low' => 'Basse', 'medium' => 'Moyenne', 'high' => 'Haute', 'critical' => 'Critique'][$intervention->priority] ?? $intervention->priority }} · Durée prévue : {{ $intervention->scheduled_duration_minutes }} min</small>
                                    @if($intervention->work_plan)<small style="display:block;margin-top:4px;white-space:pre-line"><strong>Plan :</strong> {{ $intervention->work_plan }}</small>@endif
                                    @if($intervention->safety_precautions)<small style="display:block;margin-top:4px"><strong>Sécurité :</strong> {{ $intervention->safety_precautions }}</small>@endif
                                </details>
                            @endif
                        @else
                            Incident supprimé
                        @endif
                    </td>
                    <td>{{ $intervention->team }}</td>
                    <td>{{ $intervention->scheduled_at?->format('d/m/Y H:i') ?? 'Non définie' }}</td>
                    <td>
                        <span class="badge badge-info">
                            {{ ['planned' => 'Planifiée', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée'][$intervention->status] ?? $intervention->status }}
                        </span>
                    </td>
                    <td>
                        @if($intervention->technician_response === 'pending')
                            <form method="POST" action="{{ route('technicien.interventions.respond', $intervention) }}">
                                @csrf
                                <fieldset style="border: 0; margin: 0 0 8px; padding: 0;">
                                    <legend>Équipements nécessaires</legend>
                                    <button
                                        type="button"
                                        class="button button-outline button-small recommend-equipment"
                                        data-url="{{ route('technicien.interventions.recommend-equipements', $intervention) }}"
                                    >✨ Suggérer les équipements avec l’IA</button>
                                    <div class="equipment-recommendations" hidden style="margin:8px 0;padding:10px;background:#f7fdfb;border-left:3px solid var(--aqua)">
                                        <small class="recommendation-notice"></small>
                                        <ul class="recommendation-list"></ul>
                                    </div>
                                    @forelse($equipements as $equipement)
                                        @php($reservation = $equipement->interventions->first())
                                        <label style="display: flex; align-items: center; gap: 8px; margin: 6px 0;">
                                            <input
                                                type="checkbox"
                                                name="equipements[]"
                                                value="{{ $equipement->id }}"
                                                @disabled(!in_array($equipement->id, $availableEquipementIds, true))
                                                data-equipment-name="{{ $equipement->name }}"
                                                @checked(collect(old('equipements', $intervention->equipements->pluck('id')->all()))->contains($equipement->id))
                                            >
                                            <span>
                                                {{ $equipement->name }} <small>({{ $equipement->type }})</small>
                                                @unless(in_array($equipement->id, $availableEquipementIds, true))
                                                    <small>—
                                                        @if($reservation)
                                                            déjà réservé par {{ $reservation->technician?->name ?? 'un technicien' }} pour l’intervention #{{ $reservation->id }} ({{ $reservation->scheduled_at?->format('d/m/Y H:i') ?? 'date inconnue' }}) jusqu’à sa clôture
                                                        @else
                                                            Indisponible selon son statut actuel
                                                        @endif
                                                    </small>
                                                @endunless
                                            </span>
                                        </label>
                                    @empty
                                        <small>Aucun équipement n’est disponible dans l’inventaire commun.</small>
                                    @endforelse
                                </fieldset>
                                @error('equipements')
                                    <small class="field-error">{{ $message }}</small>
                                @enderror
                                @error('equipements.*')
                                    <small class="field-error">{{ $message }}</small>
                                @enderror
                                <button class="button button-small" type="submit" name="response" value="confirmed" @disabled($equipements->isEmpty())>Confirmer</button>
                                <button class="button button-danger button-small" type="submit" name="response" value="refused">Refuser</button>
                            </form>
                        @else
                            <strong>{{ $intervention->technician_response === 'confirmed' ? 'Confirmée' : 'Refusée' }}</strong>
                            <small style="display: block;">{{ $intervention->technician_response_at?->format('d/m/Y H:i') }}</small>
                            @if($intervention->equipements->isNotEmpty())
                                <small style="display: block; margin-top: 6px;">
                                    {{ $intervention->equipements->pluck('name')->join(', ') }}
                                </small>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 32px 20px; color: var(--muted);">
                        Aucune intervention liée à un incident ne vous est attribuée.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>

<div style="margin-top: 20px;">
    {{ $assignedInterventions->links() }}
</div>
@push('scripts')
<script>
    document.querySelectorAll('.recommend-equipment').forEach((button) => {
        button.addEventListener('click', async () => {
            const form = button.closest('form');
            const panel = form.querySelector('.equipment-recommendations');
            const notice = panel.querySelector('.recommendation-notice');
            const list = panel.querySelector('.recommendation-list');
            const csrf = form.querySelector('input[name="_token"]')?.value;
            button.disabled = true;
            button.textContent = 'Analyse des équipements disponibles…';

            try {
                const response = await fetch(button.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Impossible de suggérer des équipements.');
                }

                notice.textContent = result.notice;
                list.replaceChildren();
                result.recommendations.forEach((recommendation) => {
                    const item = document.createElement('li');
                    item.append(document.createTextNode(`${recommendation.name} (${recommendation.type}) — ${recommendation.reason} `));
                    const selectButton = document.createElement('button');
                    selectButton.type = 'button';
                    selectButton.className = 'button button-small';
                    selectButton.textContent = 'Sélectionner';
                    selectButton.addEventListener('click', () => {
                        const checkbox = form.querySelector(`input[name="equipements[]"][value="${recommendation.id}"]`);
                        if (checkbox && !checkbox.disabled) {
                            checkbox.checked = true;
                            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                    item.append(selectButton);
                    list.append(item);
                });
                if (result.recommendations.length === 0) {
                    const item = document.createElement('li');
                    item.textContent = result.notice || 'Aucun équipement suffisamment pertinent trouvé';
                    list.append(item);
                }
                panel.hidden = false;
            } catch (exception) {
                notice.textContent = exception.message || 'Une erreur est survenue.';
                list.replaceChildren();
                panel.hidden = false;
            } finally {
                button.disabled = false;
                button.textContent = '✨ Suggérer les équipements avec l’IA';
            }
        });
    });
</script>
@endpush
@endsection
