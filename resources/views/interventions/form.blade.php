@csrf
@php
    $scheduleCheckUrl = isset($intervention)
        ? route('interventions.check-schedule', $intervention)
        : route('interventions.check-schedule');
@endphp
@include('partials.validation-summary', ['fieldLabels' => [
    'incident_id' => 'Incident',
    'technician_id' => 'Technicien',
    'team' => 'Équipe',
    'scheduled_at' => 'Date prévue',
    'scheduled_duration_minutes' => 'Durée prévue',
    'priority' => 'Priorité',
    'status' => 'Statut',
    'started_at' => 'Date de début',
    'completed_at' => 'Date de fin',
    'result' => 'Résultat / compte rendu',
    'work_plan' => 'Plan de travail',
    'safety_precautions' => 'Précautions de sécurité',
]])
@if($incidents->isEmpty())
    <p class="flash-message flash-warning">Aucun incident n’est disponible pour planifier une intervention. Confirmez d’abord un signalement dans <a href="{{ route('back.incidents') }}">la gestion des incidents</a>.</p>
@endif
<div class="form-grid">
    <label class="form-full">Incident
        <select id="incident_id" name="incident_id" class="{{ $errors->has('incident_id') ? 'is-invalid' : '' }}" data-label="Incident">
            <option value="">Sélectionner un incident</option>
            @foreach($incidents as $incidentOption)
                <option value="{{ $incidentOption->id }}" @selected((string) old('incident_id', $intervention->incident_id ?? '') === (string) $incidentOption->id)>#{{ $incidentOption->id }} · {{ $incidentOption->type }} · {{ $incidentOption->location ?: 'Lieu non précisé' }}</option>
            @endforeach
        </select>
        <div class="validation-feedback" data-field="incident_id">@error('incident_id') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Technicien
        <select id="technician_id" name="technician_id"  class="{{ $errors->has('technician_id') ? 'is-invalid' : '' }}" data-label="Technicien" >
            <option value="">{{ isset($intervention) ? 'Non affecté' : 'Sélectionner un technicien' }}</option>
            @foreach($technicians as $technicianOption)
                <option value="{{ $technicianOption->id }}" @selected((string) old('technician_id', $intervention->technician_id ?? '') === (string) $technicianOption->id)>{{ $technicianOption->name }} · {{ $technicianOption->speciality ?: 'Sans spécialité' }}</option>
            @endforeach
        </select>
        <div class="validation-feedback" data-field="technician_id">@error('technician_id') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Équipe
        <input id="team" name="team" value="{{ old('team', $intervention->team ?? '') }}" class="{{ $errors->has('team') ? 'is-invalid' : '' }}" data-label="Équipe">
        <div class="validation-feedback" data-field="team">@error('team') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Date prévue
        <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', isset($intervention?->scheduled_at) ? $intervention->scheduled_at?->format('Y-m-d\TH:i') : '') }}" class="{{ $errors->has('scheduled_at') ? 'is-invalid' : '' }}" data-label="Date prévue">
        <div class="validation-feedback" data-field="scheduled_at">@error('scheduled_at') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <div id="schedule-conflict-warning" class="flash-message flash-warning" role="status" hidden style="align-self: end;">
        <strong id="schedule-conflict-message">Conflit de planning</strong>
        <ul id="schedule-conflict-list"></ul>
    </div>
    <label>Durée prévue (minutes)
        <input id="scheduled_duration_minutes" type="number" name="scheduled_duration_minutes" value="{{ old('scheduled_duration_minutes', $intervention->scheduled_duration_minutes ?? 60) }}" class="{{ $errors->has('scheduled_duration_minutes') ? 'is-invalid' : '' }}" data-label="Durée prévue">
        <span class="form-hint">De 15 minutes à 8 heures. Utilisée pour détecter les conflits de planning.</span>
        <div class="validation-feedback" data-field="scheduled_duration_minutes">@error('scheduled_duration_minutes') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Priorité
        <select id="priority" name="priority" class="{{ $errors->has('priority') ? 'is-invalid' : '' }}" data-label="Priorité">
            @foreach(['low' => 'Basse', 'medium' => 'Moyenne', 'high' => 'Haute', 'critical' => 'Critique'] as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $intervention->priority ?? 'medium') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="validation-feedback" data-field="priority">@error('priority') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Statut
        <select id="status" name="status" class="{{ $errors->has('status') ? 'is-invalid' : '' }}" data-label="Statut">
            @foreach(['planned' => 'Planifiée', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $intervention->status ?? 'planned') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="validation-feedback" data-field="status">@error('status') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Date de début
        <input id="started_at" type="datetime-local" name="started_at" value="{{ old('started_at', isset($intervention?->started_at) ? $intervention->started_at?->format('Y-m-d\TH:i') : '') }}" class="{{ $errors->has('started_at') ? 'is-invalid' : '' }}" data-label="Date de début">
        <div class="validation-feedback" data-field="started_at">@error('started_at') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Date de fin
        <input id="completed_at" type="datetime-local" name="completed_at" value="{{ old('completed_at', isset($intervention?->completed_at) ? $intervention->completed_at?->format('Y-m-d\TH:i') : '') }}" class="{{ $errors->has('completed_at') ? 'is-invalid' : '' }}" data-label="Date de fin">
        <div class="validation-feedback" data-field="completed_at">@error('completed_at') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label class="form-full">Résultat / compte rendu
        <textarea id="result" name="result" rows="4" class="{{ $errors->has('result') ? 'is-invalid' : '' }}" data-label="Résultat / compte rendu">{{ old('result', $intervention->result ?? '') }}</textarea>
        <div class="validation-feedback" data-field="result">@error('result') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label class="form-full">Plan de travail
        <textarea id="work_plan" name="work_plan" rows="4" class="{{ $errors->has('work_plan') ? 'is-invalid' : '' }}" data-label="Plan de travail">{{ old('work_plan', $intervention->work_plan ?? '') }}</textarea>
        <div class="validation-feedback" data-field="work_plan">@error('work_plan') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label class="form-full">Précautions de sécurité
        <textarea id="safety_precautions" name="safety_precautions" rows="3" class="{{ $errors->has('safety_precautions') ? 'is-invalid' : '' }}" data-label="Précautions de sécurité">{{ old('safety_precautions', $intervention->safety_precautions ?? '') }}</textarea>
        <div class="validation-feedback" data-field="safety_precautions">@error('safety_precautions') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
</div>
<div class="form-actions"><a class="button button-ghost" href="{{ route('interventions.index') }}">Annuler</a><button class="button" type="submit">{{ $submitLabel }}</button></div>
@push('scripts')
<script>
    (() => {
        const technician = document.getElementById('technician_id');
        const scheduledAt = document.getElementById('scheduled_at');
        const duration = document.getElementById('scheduled_duration_minutes');
        const warning = document.getElementById('schedule-conflict-warning');
        const message = document.getElementById('schedule-conflict-message');
        const list = document.getElementById('schedule-conflict-list');
        const csrf = document.querySelector('input[name="_token"]')?.value;
        const routeUrl = @json($scheduleCheckUrl);
        let requestNumber = 0;

        if (!technician || !scheduledAt || !duration || !warning || !message || !list) return;

        const check = async () => {
            const currentRequest = ++requestNumber;
            if (!technician.value) {
                warning.hidden = true;
                return;
            }

            const payload = { technician_id: technician.value };
            if (scheduledAt.value && duration.value) {
                payload.scheduled_at = scheduledAt.value;
                payload.scheduled_duration_minutes = duration.value;
            }
            try {
                const response = await fetch(routeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();
                if (currentRequest !== requestNumber) return;
                if (!response.ok) throw new Error(result.message || 'Impossible de vérifier le planning.');

                message.textContent = result.message || 'Conflit de planning';
                list.replaceChildren();
                result.conflicts.forEach((conflict) => {
                    const item = document.createElement('li');
                    item.textContent = `Intervention #${conflict.id} · ${conflict.team} · ${conflict.scheduled_at}`;
                    list.append(item);
                });
                warning.hidden = result.conflicts.length === 0;
            } catch (exception) {
                if (currentRequest !== requestNumber) return;
                list.replaceChildren();
                const item = document.createElement('li');
                item.textContent = exception.message || 'La vérification du planning a échoué.';
                list.append(item);
                warning.hidden = false;
            }
        };

        [technician, scheduledAt, duration].forEach((field) => {
            field.addEventListener('change', check);
            field.addEventListener('input', check);
        });
        check();
    })();
</script>
@endpush