@extends('layouts.back')

@section('content')
<div class="content-heading"><div><span class="eyebrow">SURVEILLANCE</span><h1>Incidents</h1><p>Vérifiez les signalements puis suivez les interventions.</p></div></div>

@if(session('success'))<div class="panel" style="margin-bottom:1.5rem">{{ session('success') }}</div>@endif

@if($signalements->isNotEmpty())
<section class="panel table-wrap" style="margin-bottom:2rem">
    <h2 style="padding:1.25rem 1.25rem 0">Signalements à vérifier</h2>
    <table><thead><tr><th>Signalement</th><th>Type</th><th>Localisation</th><th>Photo</th><th>Créer l'incident</th></tr></thead><tbody>
    @foreach($signalements as $signalement)
        <tr>
            <td><strong>#{{ $signalement->id }}</strong><span>{{ $signalement->description }}</span></td>
            <td>{{ $signalement->type }}</td>
            <td>{{ $signalement->location }}</td>
            <td>@if($signalement->photo_path)<img class="incident-photo-thumb" src="{{ asset('storage/'.$signalement->photo_path) }}" alt="Photo du signalement #{{ $signalement->id }}">@else Aucune photo @endif</td>
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
        const modals = document.querySelectorAll('.edit-modal');
        modals.forEach(modal => {
            if (modal.style.display === 'flex') {
                const incidentId = modal.id.replace('edit-modal-', '');
                closeEditModal(incidentId);
            }
        });
    }
});
</script>
@endpush
@endsection
