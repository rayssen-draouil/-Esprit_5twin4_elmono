@extends('layouts.back')

@section('content')
<div class="content-heading"><div><span class="eyebrow">SURVEILLANCE</span><h1>Incidents</h1><p>Vérifiez les signalements puis suivez les interventions.</p></div></div>

@if(session('success'))<div class="panel" style="margin-bottom:1.5rem">{{ session('success') }}</div>@endif

@if($signalements->isNotEmpty())
<section class="panel table-wrap" style="margin-bottom:2rem">
    <h2 style="padding:1.25rem 1.25rem 0">Signalements à vérifier</h2>
    <table><thead><tr><th>Signalement</th><th>Type</th><th>Localisation</th><th>Photo</th><th>Analyse IA</th><th>Créer l'incident</th></tr></thead><tbody>
    @foreach($signalements as $signalement)
        <tr>
            <td><strong>#{{ $signalement->id }}</strong><span>{{ $signalement->description }}</span></td>
            <td>{{ $signalement->type }}</td>
            <td>{{ $signalement->location }}</td>
            <td>@if($signalement->photo_path)<img class="incident-photo-thumb" src="{{ asset('storage/'.$signalement->photo_path) }}" alt="Photo du signalement #{{ $signalement->id }}">@else Aucune photo @endif</td>
            <td>
                @if($signalement->ai_analysis)
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <span class="badge" style="background: #e6f6f3; color: var(--aqua); font-size: 11px;">
                            Catégorie : {{ $signalement->ai_analysis['suggested_category_label'] ?? $signalement->type }}
                        </span>
                        <button class="button button-small button-outline" type="button" onclick="openReportAnalysisModal({{ json_encode($signalement) }})">
                            ✨ Voir Diagnostic IA
                        </button>
                    </div>
                @else
                    <button class="button button-small" type="button" style="background: var(--deep); color: #fff;" onclick="triggerReportAnalysis({{ $signalement->id }}, this)">
                        ⚡ Analyser avec l'IA
                    </button>
                @endif
            </td>
            <td><form method="POST" action="{{ route('back.signalements.confirm', $signalement) }}">@csrf
                <input name="description" value="{{ $signalement->description }}" aria-label="Description de l'incident">
                <select name="severity"><option value="low">Faible</option><option value="medium" selected>Moyenne</option><option value="high">Élevée</option><option value="critical">Critique</option></select>
                <button class="button button-primary button-small" type="submit">Confirmer</button>
            </form>
            @if(auth()->user()->role === 'admin')
                <div class="admin-actions">
                    <form method="POST" action="{{ route('admin.signalements.destroy', $signalement) }}" onsubmit="return confirm('Supprimer ce signalement ?')">@csrf @method('DELETE')<button class="button button-danger button-small" type="submit">Supprimer</button></form>
                </div>
            @endif</td>
        </tr>
    @endforeach
    </tbody></table>
</section>
@endif

<section class="panel table-wrap"><table><thead><tr><th>Incident</th><th>Site</th><th>Priorité</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead><tbody>
@foreach($incidents as $item)<tr><td><strong>{{ $item['id'] }}</strong><span>{{ $item['title'] }}</span></td><td>{{ $item['site'] }}</td><td><x-ui.badge :variant="strtolower($item['priority'])">{{ $item['priority'] }}</x-ui.badge></td><td><x-ui.badge :variant="strtolower(str_replace(' ', '-', $item['status']))">{{ $item['status'] }}</x-ui.badge></td><td>{{ $item['date'] }}</td><td>
@if(auth()->user()->role === 'admin')
<button class="button button-secondary button-small" onclick="openEditModal({{ $item['raw_id'] }})">Modifier</button>
<form class="admin-actions" method="POST" action="{{ route('admin.incidents.destroy', $item['raw_id']) }}" onsubmit="return confirm('Supprimer cet incident ?')">@csrf @method('DELETE')<button class="button button-danger button-small" type="submit">Supprimer</button></form>
@endif
</td></tr>
@if(auth()->user()->role === 'admin')
<div id="edit-modal-{{ $item['raw_id'] }}" class="edit-modal">
    <div class="edit-modal-content">
        <h3>Modifier l'incident {{ $item['id'] }}</h3>
        <form method="POST" action="{{ route('admin.incidents.update', $item['raw_id']) }}">
            @csrf @method('PATCH')
            <div class="form-group">
                <label for="type-{{ $item['raw_id'] }}">Type</label>
                <input id="type-{{ $item['raw_id'] }}" name="type" value="{{ $item['type'] }}" required>
            </div>
            <div class="form-group">
                <label for="description-{{ $item['raw_id'] }}">Description</label>
                <textarea id="description-{{ $item['raw_id'] }}" name="description" required>{{ $item['title'] }}</textarea>
            </div>
            <div class="form-group">
                <label for="location-{{ $item['raw_id'] }}">Localisation</label>
                <input id="location-{{ $item['raw_id'] }}" name="location" value="{{ $item['site'] }}">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="severity-{{ $item['raw_id'] }}">Gravité</label>
                    <select id="severity-{{ $item['raw_id'] }}" name="severity" required>
                        <option value="low" @selected($item['severity'] === 'low')>Faible</option>
                        <option value="medium" @selected($item['severity'] === 'medium')>Moyenne</option>
                        <option value="high" @selected($item['severity'] === 'high')>Élevée</option>
                        <option value="critical" @selected($item['severity'] === 'critical')>Critique</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status-{{ $item['raw_id'] }}">Statut</label>
                    <select id="status-{{ $item['raw_id'] }}" name="status" required>
                        <option value="reported" @selected($item['status_raw'] === 'reported')">Ouvert</option>
                        <option value="in_progress" @selected($item['status_raw'] === 'in_progress')">En cours</option>
                        <option value="resolved" @selected($item['status_raw'] === 'resolved')">Résolu</option>
                        <option value="closed" @selected($item['status_raw'] === 'closed')">Fermé</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="button button-secondary" onclick="closeEditModal({{ $item['raw_id'] }})">Annuler</button>
                <button type="submit" class="button button-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach
</tbody></table></section>

@if(auth()->user()->role === 'admin')
<section class="panel table-wrap" style="margin-top:2rem"><h2 style="padding:1.25rem 1.25rem 0">Gestion des signalements</h2><table><thead><tr><th>Référence</th><th>Modification</th><th>Incident lié</th><th></th></tr></thead><tbody>
@foreach($allSignalements as $signalement)<tr><td>#{{ $signalement->id }}</td><td><form method="POST" action="{{ route('admin.signalements.update', $signalement) }}">@csrf @method('PATCH')
    <input name="type" value="{{ $signalement->type }}" aria-label="Type">
    <input name="description" value="{{ $signalement->description }}" aria-label="Description">
    <input name="location" value="{{ $signalement->location }}" aria-label="Localisation">
    <input name="priority" value="{{ $signalement->priority }}" aria-label="Priorité">
    <select name="status"><option value="new" @selected($signalement->status === 'new')>Nouveau</option><option value="in_progress" @selected($signalement->status === 'in_progress')>En cours</option><option value="resolved" @selected($signalement->status === 'resolved')>Résolu</option></select>
    <button class="button button-primary button-small" type="submit">Enregistrer</button>
</form></td><td>{{ $signalement->incident ? 'INC-'.str_pad($signalement->incident->id, 4, '0', STR_PAD_LEFT) : 'Aucun' }}</td><td><form class="admin-actions" method="POST" action="{{ route('admin.signalements.destroy', $signalement) }}" onsubmit="return confirm('Supprimer ce signalement ?')">@csrf @method('DELETE')<button class="button button-danger button-small" type="submit">Supprimer</button></form></td></tr>@endforeach
</tbody></table></section>
@endif

@push('styles')
<style>
.edit-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.edit-modal-content {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.edit-modal-content h3 {
    margin: 0 0 1.5rem 0;
    color: var(--deep);
    font-size: 1.25rem;
}

.edit-modal-content .form-group {
    margin-bottom: 1rem;
}

.edit-modal-content label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--ink);
}

.edit-modal-content input,
.edit-modal-content select,
.edit-modal-content textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--line);
    border-radius: 8px;
    font-size: 0.875rem;
    font-family: inherit;
    box-sizing: border-box;
}

.edit-modal-content input:focus,
.edit-modal-content select:focus,
.edit-modal-content textarea:focus {
    outline: none;
    border-color: var(--aqua);
    box-shadow: 0 0 0 3px rgba(22, 183, 163, 0.1);
}

.edit-modal-content .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.edit-modal-content .modal-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--line);
}

.edit-modal-content .modal-actions button {
    flex: 1;
}

.edit-modal-content textarea {
    resize: vertical;
    min-height: 80px;
}
</style>
@endpush

@push('scripts')
<script>
function openEditModal(incidentId) {
    const modal = document.getElementById('edit-modal-' + incidentId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeEditModal(incidentId) {
    const modal = document.getElementById('edit-modal-' + incidentId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('edit-modal')) {
        const incidentId = e.target.id.replace('edit-modal-', '');
        closeEditModal(incidentId);
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.edit-modal, .intelligence-modal-backdrop');
        modals.forEach(modal => {
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
            }
        });
        document.body.style.overflow = '';
    }
});

// =========================================================================
// Feature 2: Smart Citizen Report Analyzer
// =========================================================================
let currentActiveReport = null;

function triggerReportAnalysis(reportId, btn) {
    const origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Analyse en cours...';

    fetch('/admin/signalements/' + reportId + '/ai-analyze', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Erreur lors de l\'analyse');
        return data;
    })
    .then(data => {
        window.location.reload();
    })
    .catch(err => {
        alert(err.message || 'Erreur technique lors de l\'analyse du signalement.');
        btn.disabled = false;
        btn.textContent = origText;
    });
}

function openReportAnalysisModal(signalement) {
    currentActiveReport = signalement;
    const a = signalement.ai_analysis;
    if (!a) return;

    document.getElementById('ai-report-modal-title').textContent = 'Signalement #' + signalement.id;
    document.getElementById('ai-report-modal-subtitle').textContent = 'Signalé le ' + (signalement.reported_at ? new Date(signalement.reported_at).toLocaleString('fr-FR') : 'Date inconnue');
    document.getElementById('ai-report-orig-type').textContent = signalement.type || 'Non spécifié';
    document.getElementById('ai-report-orig-desc').textContent = signalement.description || '';
    document.getElementById('ai-report-orig-loc').textContent = signalement.location || 'Non renseignée';

    document.getElementById('ai-report-badge-category').textContent = 'Catégorie : ' + (a.suggested_category_label || a.suggested_category || 'Autre');
    const badgeSev = document.getElementById('ai-report-badge-severity');
    const sev = (a.suggested_severity || 'medium').toLowerCase();
    badgeSev.textContent = 'Gravité : ' + sev.toUpperCase();
    badgeSev.className = 'badge badge-' + (sev === 'critical' || sev === 'high' ? 'danger' : (sev === 'medium' ? 'warning' : 'neutral'));

    document.getElementById('ai-report-summary').textContent = a.summary || '';
    document.getElementById('ai-report-action').textContent = a.recommended_next_action || 'Vérification sur site.';
    document.getElementById('ai-report-geo').textContent = a.location_details || 'Aucune précision géographique complémentaire.';
    document.getElementById('ai-report-duplicate-reason').textContent = a.duplicate_reason || 'Aucun doublon détecté.';

    // Populate edit dropdowns
    const editType = document.getElementById('edit-signalement-type');
    if (editType) editType.value = ucfirst(a.suggested_category || signalement.type || 'Autre');
    const editPri = document.getElementById('edit-signalement-priority');
    if (editPri) editPri.value = a.suggested_priority || signalement.priority || 'medium';

    document.getElementById('modal-ai-report-analysis').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function closeReportAnalysisModal() {
    document.getElementById('modal-ai-report-analysis').style.display = 'none';
    document.body.style.overflow = '';
}

function acceptAiReportSuggestions() {
    if (!currentActiveReport) return;
    const btn = document.getElementById('btn-accept-report-suggestions');
    btn.disabled = true;
    btn.textContent = 'Validation...';

    fetch('/admin/signalements/' + currentActiveReport.id + '/ai-accept', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Erreur lors de la validation');
        return data;
    })
    .then(data => {
        window.location.reload();
    })
    .catch(err => {
        alert(err.message || 'Erreur lors de la validation des suggestions.');
        btn.disabled = false;
        btn.textContent = '✓ Valider les suggestions IA';
    });
}

function saveManualClassification() {
    if (!currentActiveReport) return;
    const type = document.getElementById('edit-signalement-type').value;
    const priority = document.getElementById('edit-signalement-priority').value;

    fetch('/admin/signalements/' + currentActiveReport.id + '/ai-edit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ type: type, priority: priority })
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Erreur lors de la modification');
        return data;
    })
    .then(data => {
        window.location.reload();
    })
    .catch(err => {
        alert(err.message || 'Erreur lors de l\'enregistrement.');
    });
}

function reanalyzeCurrentReport() {
    if (!currentActiveReport) return;
    const btn = document.getElementById('btn-reanalyze-report');
    btn.disabled = true;
    btn.textContent = 'Analyse...';

    triggerReportAnalysis(currentActiveReport.id, btn);
}
</script>
@endpush

{{-- Modal: Smart Citizen Report Analyzer --}}
<div id="modal-ai-report-analysis" class="intelligence-modal-backdrop" style="display: none;" onclick="if(event.target === this) closeReportAnalysisModal()">
    <div class="intelligence-modal-dialog" style="max-width: 720px; max-height: 88vh; background: #fff; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); display: flex; flex-direction: column; overflow: hidden; margin: auto;">
        <div class="intelligence-modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: flex-start; background: #fbfdfc; flex-shrink: 0;">
            <div>
                <span class="eyebrow" style="color: var(--aqua); font-size: 11px; font-weight: 800; letter-spacing: 0.1em;">ANALYSEUR INTELLIGENT DE SIGNALEMENT · IA</span>
                <h2 id="ai-report-modal-title" style="margin: 4px 0 0; font-size: 20px; color: var(--deep);">Signalement #--</h2>
                <small id="ai-report-modal-subtitle" style="color: var(--muted);"></small>
            </div>
            <button class="modal-close-btn" type="button" onclick="closeReportAnalysisModal()" style="border: 0; background: none; font-size: 20px; cursor: pointer;">✕</button>
        </div>

        <div class="intelligence-modal-body" style="padding: 20px 24px; overflow-y: auto; flex: 1;">
            {{-- Original Citizen Declaration --}}
            <div style="background: #fbfdfc; border: 1px solid var(--line); border-radius: 6px; padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <strong style="font-size: 11px; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;">
                        Texte original du citoyen (Inaltéré)
                    </strong>
                    <span class="badge badge-neutral" style="font-size: 10px;">Déclaré : <span id="ai-report-orig-type"></span></span>
                </div>
                <p id="ai-report-orig-desc" style="margin: 0; font-size: 13px; color: var(--ink); line-height: 1.5; font-style: italic;"></p>
                <div style="margin-top: 6px; font-size: 11px; color: var(--muted);">
                    📍 Localisation déclarée : <strong id="ai-report-orig-loc" style="color: var(--ink);"></strong>
                </div>
            </div>

            {{-- AI Analysis Overview Banner --}}
            <div style="background: linear-gradient(135deg, #eef9f7 0%, #e0f4f0 100%); border: 1px solid rgba(22, 183, 163, 0.3); border-radius: 6px; padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 11px; font-weight: 800; color: var(--aqua); letter-spacing: 0.05em;">SYNTHÈSE TECHNIQUE IA</span>
                    <div style="display: flex; gap: 8px;">
                        <span id="ai-report-badge-category" class="badge" style="background: #fff; color: var(--deep); font-weight: 700;"></span>
                        <span id="ai-report-badge-severity" class="badge"></span>
                    </div>
                </div>
                <p id="ai-report-summary" style="margin: 0; font-size: 13px; color: var(--ink); line-height: 1.5;"></p>
            </div>

            {{-- Recommendations & Location --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                <div style="background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 12px;">
                    <strong style="font-size: 11px; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 4px;">Action recommandée</strong>
                    <p id="ai-report-action" style="margin: 0; font-size: 12px; color: var(--ink); line-height: 1.4;"></p>
                </div>
                <div style="background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 12px;">
                    <strong style="font-size: 11px; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 4px;">Détails géographiques extraits</strong>
                    <p id="ai-report-geo" style="margin: 0; font-size: 12px; color: var(--ink); line-height: 1.4;"></p>
                </div>
            </div>

            {{-- Duplicate Detection Section --}}
            <div id="ai-report-duplicate-box" style="border: 1px solid #fed7aa; background: #fffaf5; border-radius: 6px; padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <span style="font-size: 16px;">🔍</span>
                    <strong style="font-size: 12px; color: #c2410c; text-transform: uppercase;">Détection de Doublons</strong>
                </div>
                <p id="ai-report-duplicate-reason" style="margin: 0; font-size: 12px; color: #9a3412; line-height: 1.4;"></p>
            </div>

            {{-- Edit Classification Form (collapsible) --}}
            <details id="ai-report-edit-details" style="border: 1px solid var(--line); border-radius: 6px; padding: 10px 14px; background: #fff;">
                <summary style="font-size: 12px; font-weight: 700; color: var(--deep); cursor: pointer;">
                    ⚙ Ajuster manuellement la classification
                </summary>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; display: block; margin-bottom: 4px;">Catégorie</label>
                        <select id="edit-signalement-type" style="width: 100%; padding: 6px; border: 1px solid var(--line); border-radius: 4px; font-size: 12px;">
                            <option value="Fuite">Fuite</option>
                            <option value="Coupure">Coupure</option>
                            <option value="Contamination">Contamination</option>
                            <option value="Panne">Panne</option>
                            <option value="Pression">Pression</option>
                            <option value="Qualité">Qualité</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 700; display: block; margin-bottom: 4px;">Gravité</label>
                        <select id="edit-signalement-priority" style="width: 100%; padding: 6px; border: 1px solid var(--line); border-radius: 4px; font-size: 12px;">
                            <option value="low">Faible</option>
                            <option value="medium">Moyenne</option>
                            <option value="high">Élevée</option>
                            <option value="critical">Critique</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 10px; text-align: right;">
                    <button type="button" class="button button-small" onclick="saveManualClassification()">Enregistrer la modification</button>
                </div>
            </details>
        </div>

        <div class="intelligence-modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 10px; background: #fbfdfc; flex-shrink: 0;">
            <button class="button button-outline" type="button" onclick="closeReportAnalysisModal()">Fermer</button>
            <button id="btn-reanalyze-report" class="button button-outline" type="button" onclick="reanalyzeCurrentReport()">🔄 Réanalyser</button>
            <button id="btn-accept-report-suggestions" class="button button-primary" type="button" onclick="acceptAiReportSuggestions()">
                ✓ Valider les suggestions IA
            </button>
        </div>
    </div>
</div>
@endsection
