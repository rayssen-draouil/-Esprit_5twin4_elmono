<div class="panel form-panel">
    <div class="form-grid">
        {{-- Section 1: Association & Type --}}
        <div class="form-full" style="border-bottom: 1px solid var(--line); padding-bottom: 8px; margin-bottom: 4px;">
            <span class="eyebrow">1. INFRASTRUCTURE & CARACTÉRISTIQUES</span>
            <h2 style="font-size: 18px; margin: 4px 0 0;">Cible de l'intervention</h2>
        </div>

        <label class="form-full">
            Infrastructure concernée <span style="color: var(--coral);">*</span>
            <select name="infrastructure_id"
                    id="select_infrastructure_id"
                    class="{{ $errors->has('infrastructure_id') ? 'is-invalid' : '' }}"
                    data-label="Infrastructure"
                    data-rules="required">
                <option value="">Sélectionnez l'ouvrage cible</option>
                @foreach($infrastructures as $infra)
                    <option value="{{ $infra->id }}"
                            @selected(old('infrastructure_id', $maintenance->infrastructure_id ?? ($selectedInfrastructureId ?? '')) == $infra->id)>
                        {{ $infra->name }} ({{ $infra->reference_code }})
                    </option>
                @endforeach
            </select>
            <div class="validation-feedback" data-field="infrastructure_id">
                @error('infrastructure_id')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label>
            Code référence de l'opération
            <input type="text"
                   name="reference_code"
                   id="input_maintenance_reference_code"
                   class="{{ $errors->has('reference_code') ? 'is-invalid' : '' }}"
                   value="{{ old('reference_code', $maintenance->reference_code ?? '') }}"
                   placeholder="Ex: MNT-2026-001 (auto si vide)"
                   data-label="Code référence"
                   data-rules="reference_code">
            <span class="form-hint">Exemple : MNT-2026-001 (généré automatiquement si vide)</span>
            <div class="validation-feedback" data-field="reference_code">
                @error('reference_code')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label>
            Type de maintenance <span style="color: var(--coral);">*</span>
            <select name="type"
                    id="select_maintenance_type"
                    class="{{ $errors->has('type') ? 'is-invalid' : '' }}"
                    data-label="Type de maintenance"
                    data-rules="required">
                @foreach($types as $t)
                    <option value="{{ $t }}" @selected(old('type', $maintenance->type ?? 'Préventive') === $t)>
                        {{ $t }}
                    </option>
                @endforeach
            </select>
            <div class="validation-feedback" data-field="type">
                @error('type')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label>
            Priorité opérationnelle <span style="color: var(--coral);">*</span>
            <select name="priority"
                    id="select_maintenance_priority"
                    class="{{ $errors->has('priority') ? 'is-invalid' : '' }}"
                    data-label="Priorité opérationnelle"
                    data-rules="required">
                @foreach($priorities as $pval => $plabel)
                    <option value="{{ $pval }}" @selected(old('priority', $maintenance->priority ?? 'medium') === $pval)>
                        {{ $plabel }}
                    </option>
                @endforeach
            </select>
            <div class="validation-feedback" data-field="priority">
                @error('priority')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label>
            Statut actuel <span style="color: var(--coral);">*</span>
            <select name="status"
                    id="select_status"
                    class="{{ $errors->has('status') ? 'is-invalid' : '' }}"
                    data-label="Statut actuel"
                    data-rules="required">
                @foreach($statuses as $sval => $slabel)
                    <option value="{{ $sval }}" @selected(old('status', $maintenance->status ?? 'planned') === $sval)>
                        {{ $slabel }}
                    </option>
                @endforeach
            </select>
            <span class="form-hint">Si « Terminée », le compte-rendu technique est obligatoire.</span>
            <div class="validation-feedback" data-field="status">
                @error('status')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        {{-- Section 2: Affectation --}}
        <div class="form-full" style="border-bottom: 1px solid var(--line); padding-bottom: 8px; margin: 16px 0 4px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 8px;">
            <div>
                <span class="eyebrow">2. RESSOURCES HUMAINES</span>
                <h2 style="font-size: 18px; margin: 4px 0 0;">Affectation technique</h2>
            </div>
            @if(empty($maintenance?->technician_assignment_locked))
                <button type="button" class="button button-small" id="btn-recommend-tech" onclick="openTechRecommendationModal()" style="background: linear-gradient(135deg, var(--deep) 0%, #1e3a47 100%); border: 1px solid var(--aqua); color: #fff; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(22, 183, 163, 0.2);">
                    <span style="color: var(--aqua);">✨</span> Recommander un technicien avec l'IA
                </button>
            @endif
        </div>

        <label>
            Technicien responsable
            @if(!empty($maintenance?->technician_assignment_locked))
                <select id="select_technician_id" disabled style="background: #f1f5f9; cursor: not-allowed; color: #475569;" class="{{ $errors->has('technician_id') ? 'is-invalid' : '' }}">
                    @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}" @selected($maintenance->technician_id == $tech->id)>
                            {{ $tech->name }} @if($tech->speciality) ({{ $tech->speciality }}) @endif
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="technician_id" value="{{ $maintenance->technician_id }}">
                <div style="margin-top: 6px; padding: 8px 12px; background: #def5ed; border: 1px solid #13886f; border-radius: 4px; font-size: 12px; color: #13886f; display: flex; align-items: center; gap: 8px;">
                    <span>🔒</span>
                    <span><strong>Affectation Verrouillée :</strong> {{ $maintenance->technician?->name }} a accepté cette intervention. La réattribution est bloquée.</span>
                </div>
            @else
                <select name="technician_id" id="select_technician_id" class="{{ $errors->has('technician_id') ? 'is-invalid' : '' }}">
                    <option value="">Aucun technicien assigné</option>
                    @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}" @selected(old('technician_id', $maintenance->technician_id ?? '') == $tech->id)>
                            {{ $tech->name }} @if($tech->speciality) ({{ $tech->speciality }}) @endif
                        </option>
                    @endforeach
                </select>
            @endif
            <div class="validation-feedback" data-field="technician_id">
                @error('technician_id')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
            <div id="ai-tech-selected-badge" style="display: none; margin-top: 6px; padding: 6px 10px; background: #eef9f7; border: 1px solid rgba(22,183,163,0.3); border-radius: 4px; font-size: 11px; color: var(--deep); align-items: center; gap: 6px;">
                <span style="color: var(--aqua);">✨</span> <span>Sélectionné via l'IA : <strong id="ai-tech-selected-name"></strong> (<span id="ai-tech-selected-reason"></span>)</span>
            </div>
        </label>

        <label>
            Équipe ou Entreprise prestataire
            <input type="text"
                   name="team"
                   id="input_team"
                   class="{{ $errors->has('team') ? 'is-invalid' : '' }}"
                   value="{{ old('team', $maintenance->team ?? '') }}"
                   placeholder="Ex: Équipe Électromécanique SONEDE Tunis"
                   data-label="Équipe ou Entreprise prestataire"
                   data-rules="letters_only">
            <span class="form-hint">Doit contenir uniquement des lettres, espaces ou tirets (aucun chiffre).</span>
            <div class="validation-feedback" data-field="team">
                @error('team')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        {{-- Section 3: Calendrier --}}
        <div class="form-full" style="border-bottom: 1px solid var(--line); padding-bottom: 8px; margin: 16px 0 4px;">
            <span class="eyebrow">3. PLANNING & HORODATAGE</span>
            <h2 style="font-size: 18px; margin: 4px 0 0;">Dates d'exécution & chronologie</h2>
        </div>

        <label>
            Date et heure planifiées <span style="color: var(--coral);">*</span>
            <input type="datetime-local"
                   id="input_scheduled_at"
                   name="scheduled_at"
                   class="{{ $errors->has('scheduled_at') ? 'is-invalid' : '' }}"
                   value="{{ old('scheduled_at', isset($maintenance->scheduled_at) ? $maintenance->scheduled_at->format('Y-m-d\TH:i') : now()->addDays(3)->format('Y-m-d\T09:00')) }}"
                   data-label="Date et heure planifiées"
                   data-rules="required">
            <span class="form-hint">📅 Date prévisionnelle (dans le futur si statut « Planifiée »)</span>
            <div class="validation-feedback" data-field="scheduled_at">
                @error('scheduled_at')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label>
            Date de début réel sur site
            <input type="datetime-local"
                   id="input_started_at"
                   name="started_at"
                   max="{{ date('Y-m-d\TH:i') }}"
                   class="{{ $errors->has('started_at') ? 'is-invalid' : '' }}"
                   value="{{ old('started_at', isset($maintenance->started_at) ? $maintenance->started_at->format('Y-m-d\TH:i') : '') }}"
                   data-label="Date de début réel">
            <span class="form-hint">⏱ Début réel (ne peut pas être dans le futur)</span>
            <div class="validation-feedback" data-field="started_at">
                @error('started_at')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label>
            Date d'achèvement effectif
            <input type="datetime-local"
                   id="input_completed_at"
                   name="completed_at"
                   max="{{ date('Y-m-d\TH:i') }}"
                   class="{{ $errors->has('completed_at') ? 'is-invalid' : '' }}"
                   value="{{ old('completed_at', isset($maintenance->completed_at) ? $maintenance->completed_at->format('Y-m-d\TH:i') : '') }}"
                   data-label="Date d'achèvement">
            <span class="form-hint">🏁 Clôture effective (postérieure au début d'intervention)</span>
            <div class="validation-feedback" data-field="completed_at">
                @error('completed_at')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label>
            Prochaine échéance recommandée suite aux travaux
            <input type="date"
                   id="input_next_date"
                   name="next_maintenance_date"
                   min="{{ date('Y-m-d') }}"
                   class="{{ $errors->has('next_maintenance_date') ? 'is-invalid' : '' }}"
                   value="{{ old('next_maintenance_date', isset($maintenance->next_maintenance_date) ? $maintenance->next_maintenance_date->format('Y-m-d') : '') }}"
                   data-label="Prochaine échéance">
            <span class="form-hint">🔄 Doit être planifiée dans le futur</span>
            <div class="validation-feedback" data-field="next_maintenance_date">
                @error('next_maintenance_date')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        {{-- Section 4: Budget & Rapport --}}
        <div class="form-full" style="border-bottom: 1px solid var(--line); padding-bottom: 8px; margin: 16px 0 4px;">
            <span class="eyebrow">4. ASPECTS BUDGÉTAIRES & TRAVAUX</span>
            <h2 style="font-size: 18px; margin: 4px 0 0;">Consommation & compte-rendu</h2>
        </div>

        <label>
            Coût estimé ou facturé (DT ou €)
            <input type="number"
                   step="0.01"
                   min="0"
                   name="cost"
                   id="input_maintenance_cost"
                   class="{{ $errors->has('cost') ? 'is-invalid' : '' }}"
                   value="{{ old('cost', $maintenance->cost ?? '') }}"
                   placeholder="Ex: 1450.00"
                   data-label="Coût financier"
                   data-rules="positive_number">
            <span class="form-hint">Montant numérique positif ou nul (ex: 2800.50)</span>
            <div class="validation-feedback" data-field="cost">
                @error('cost')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label>
            Durée d'intervention (heures)
            <input type="number"
                   step="0.1"
                   min="0.1"
                   max="500"
                   name="duration_hours"
                   id="input_maintenance_duration"
                   class="{{ $errors->has('duration_hours') ? 'is-invalid' : '' }}"
                   value="{{ old('duration_hours', $maintenance->duration_hours ?? '') }}"
                   placeholder="Ex: 3.5"
                   data-label="Durée d'intervention"
                   data-rules="number_between:0.1:500">
            <span class="form-hint">Durée cumulée (ex: 4.5 pour 4 heures et 30 minutes)</span>
            <div class="validation-feedback" data-field="duration_hours">
                @error('duration_hours')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label class="form-full">
            Description des opérations prévues
            <textarea name="description"
                      id="input_maintenance_description"
                      class="{{ $errors->has('description') ? 'is-invalid' : '' }}"
                      rows="3"
                      data-label="Description"
                      data-rules="max:3000"
                      placeholder="Détaillez les points de contrôle, pièces à vérifier ou motifs d'intervention...">{{ old('description', $maintenance->description ?? '') }}</textarea>
            <div class="validation-feedback" data-field="description">
                @error('description')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>

        <label class="form-full">
            Compte-rendu technique & constatations finalisées
            <textarea name="result"
                      id="input_result"
                      class="{{ $errors->has('result') ? 'is-invalid' : '' }}"
                      rows="3"
                      data-label="Compte-rendu technique"
                      data-rules="max:3000"
                      placeholder="Renseignez le bilan des opérations, pièces remplacées, anomalies résolues...">{{ old('result', $maintenance->result ?? '') }}</textarea>
            <span class="form-hint" id="result_hint">Obligatoire si le statut est « Terminée ».</span>
            <div class="validation-feedback" data-field="result">
                @error('result')
                    <span class="field-error-badge" role="alert">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </span>
                @enderror
            </div>
        </label>
    </div>

    <div class="form-actions">
        <a class="button button-outline" href="{{ isset($maintenance) ? route('maintenances.show', $maintenance) : route('maintenances.index') }}">
            Annuler
        </a>
        <button class="button" type="submit" id="btn_submit_maintenance">
            {{ isset($maintenance) ? 'Enregistrer les modifications' : 'Créer l’opération de maintenance' }}
        </button>
    </div>
</div>

{{-- Interactive maintenance coherence helper --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.getElementById('select_status');
    const resultInput = document.getElementById('input_result');
    const resultHint = document.getElementById('result_hint');
    const startedInput = document.getElementById('input_started_at');
    const completedInput = document.getElementById('input_completed_at');
    const scheduledInput = document.getElementById('input_scheduled_at');
    const nextDateInput = document.getElementById('input_next_date');

    function checkStatus() {
        if (statusSelect && statusSelect.value === 'completed') {
            if (resultHint) {
                resultHint.style.color = 'var(--coral)';
                resultHint.style.fontWeight = '700';
                resultHint.textContent = '⚠ Attention : le compte-rendu technique est obligatoire pour enregistrer une maintenance terminée.';
            }
            if (resultInput) {
                resultInput.dataset.rules = 'required|max:3000';
            }
        } else {
            if (resultHint) {
                resultHint.style.color = 'var(--muted)';
                resultHint.style.fontWeight = 'normal';
                resultHint.textContent = 'Obligatoire si le statut est « Terminée ».';
            }
            if (resultInput) {
                resultInput.dataset.rules = 'max:3000';
            }
        }
    }

    function syncTimestamps() {
        if (startedInput && startedInput.value && completedInput) {
            completedInput.min = startedInput.value;
        }
        if (scheduledInput && scheduledInput.value && nextDateInput) {
            const schedDate = scheduledInput.value.split('T')[0];
            if (schedDate) {
                nextDateInput.min = schedDate;
            }
        }
    }

    if (statusSelect) statusSelect.addEventListener('change', checkStatus);
    if (startedInput) startedInput.addEventListener('change', syncTimestamps);
    if (scheduledInput) scheduledInput.addEventListener('change', syncTimestamps);

    checkStatus();
    syncTimestamps();
});

// AI Technician Matchmaker Modal Logic
function openTechRecommendationModal() {
    const modal = document.getElementById('modal-tech-recommendation');
    if (!modal) return;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Show loading state, hide results & empty state
    document.getElementById('tech-rec-loading').style.display = 'block';
    document.getElementById('tech-rec-results').style.display = 'none';
    document.getElementById('tech-rec-empty').style.display = 'none';
    document.getElementById('tech-rec-error').style.display = 'none';

    // Gather form context
    const type = document.getElementById('select_type')?.value || 'Préventive';
    const priority = document.getElementById('select_priority')?.value || 'medium';
    const infraId = document.getElementById('select_infrastructure_id')?.value || '';
    const desc = document.getElementById('textarea_description')?.value || '';

    // Update context preview in modal
    document.getElementById('tech-rec-ctx-type').textContent = type;
    document.getElementById('tech-rec-ctx-priority').textContent = priority.toUpperCase();
    
    const infraSelect = document.getElementById('select_infrastructure_id');
    const infraText = infraSelect && infraSelect.selectedIndex > 0 ? infraSelect.options[infraSelect.selectedIndex].text : 'Non spécifiée';
    document.getElementById('tech-rec-ctx-infra').textContent = infraText;

    fetch("{{ route('maintenances.recommend-technicians') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            @if(isset($maintenance))
            maintenance_id: {{ $maintenance->id }},
            @endif
            type: type,
            priority: priority,
            infrastructure_id: infraId ? parseInt(infraId) : null,
            description: desc,
        })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('tech-rec-loading').style.display = 'none';

        if (!data.success) {
            document.getElementById('tech-rec-error').style.display = 'block';
            document.getElementById('tech-rec-error-msg').textContent = data.message || "Une erreur est survenue lors de l'analyse.";
            return;
        }

        const recs = data.recommendations;
        const rankings = recs.candidate_rankings || [];

        if (rankings.length === 0) {
            document.getElementById('tech-rec-empty').style.display = 'block';
            document.getElementById('tech-rec-empty-reason').textContent = recs.no_suitable_candidate_reason || "Aucun technicien éligible n'a été trouvé pour cette intervention.";
            return;
        }

        renderCandidateRankings(rankings, recs.is_ai_generated);
        document.getElementById('tech-rec-results').style.display = 'block';
    })
    .catch(err => {
        document.getElementById('tech-rec-loading').style.display = 'none';
        document.getElementById('tech-rec-error').style.display = 'block';
        document.getElementById('tech-rec-error-msg').textContent = "Erreur de connexion au service d'analyse IA.";
    });
}

function closeTechRecommendationModal() {
    const modal = document.getElementById('modal-tech-recommendation');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function renderCandidateRankings(rankings, isAi) {
    const container = document.getElementById('tech-rec-cards-container');
    container.innerHTML = '';

    const aiBadge = document.getElementById('tech-rec-ai-badge');
    if (aiBadge) {
        aiBadge.textContent = isAi ? '✨ Analyse Groq AI (gpt-oss-120b)' : '⚡ Analyse Déterministe AquaSecure';
    }

    rankings.forEach((cand, idx) => {
        const isTop = (cand.rank === 1);
        const card = document.createElement('div');
        card.style.cssText = isTop
            ? 'border: 2px solid var(--aqua); background: linear-gradient(180deg, #f4fbf9 0%, #ffffff 100%); border-radius: 8px; padding: 18px; margin-bottom: 14px; box-shadow: 0 4px 14px rgba(22, 183, 163, 0.15);'
            : 'border: 1px solid var(--line); background: #ffffff; border-radius: 8px; padding: 16px; margin-bottom: 14px;';

        const matchClass = cand.specialty_match === 'Excellente' ? 'background: #d1fae5; color: #065f46;' : (cand.specialty_match === 'Bonne' ? 'background: #e0f2fe; color: #0369a1;' : 'background: #fef3c7; color: #92400e;');

        const advantagesHtml = (cand.advantages || []).map(adv => `<li style="margin-bottom: 3px;">✓ ${escapeHtml(adv)}</li>`).join('');
        const limitationsHtml = (cand.limitations || []).map(lim => `<li style="margin-bottom: 3px; color: #b45309;">⚠ ${escapeHtml(lim)}</li>`).join('');

        card.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; font-weight: 800; font-size: 14px; ${isTop ? 'background: var(--aqua); color: #fff;' : 'background: #f1f5f9; color: var(--ink);'}">
                        #${cand.rank}
                    </span>
                    <div>
                        <strong style="font-size: 15px; color: var(--deep); display: block;">${escapeHtml(cand.name)}</strong>
                        <span style="font-size: 12px; color: var(--muted);">${escapeHtml(cand.speciality)}</span>
                    </div>
                </div>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; ${matchClass}">
                        ${escapeHtml(cand.specialty_match)}
                    </span>
                    <span style="font-size: 11px; padding: 3px 8px; border-radius: 4px; background: #f1f5f9; color: var(--ink);">
                        💼 ${escapeHtml(cand.workload_summary)}
                    </span>
                </div>
            </div>

            <p style="font-size: 13px; color: var(--ink); margin: 0 0 10px; line-height: 1.45; background: ${isTop ? '#ffffff' : '#fbfdfc'}; padding: 10px; border-radius: 6px; border: 1px solid var(--line);">
                <strong>Avis technique :</strong> ${escapeHtml(cand.reasoning)}
            </p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px; margin-bottom: 12px;">
                <div>
                    <span style="font-weight: 700; color: #065f46; font-size: 11px; text-transform: uppercase;">Points forts</span>
                    <ul style="list-style: none; padding: 0; margin: 4px 0 0; color: #047857;">${advantagesHtml || '<li>Aucun point fort particulier</li>'}</ul>
                </div>
                <div>
                    <span style="font-weight: 700; color: #92400e; font-size: 11px; text-transform: uppercase;">Vigilance</span>
                    <ul style="list-style: none; padding: 0; margin: 4px 0 0;">${limitationsHtml || '<li style="color: var(--muted);">Aucune restriction détectée</li>'}</ul>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; align-items: center; border-top: 1px dashed var(--line); padding-top: 10px;">
                <button type="button" class="button button-small" onclick="selectRecommendedTechnician(${cand.technician_id}, '${escapeHtml(cand.name).replace(/'/g, "\\'")}', '${escapeHtml(cand.specialty_match).replace(/'/g, "\\'")}')" style="${isTop ? 'background: var(--aqua);' : 'background: var(--deep);'} color: #fff;">
                    ✓ Affecter ce technicien
                </button>
            </div>
        `;
        container.appendChild(card);
    });
}

function selectRecommendedTechnician(techId, techName, matchLabel) {
    const select = document.getElementById('select_technician_id');
    if (select) {
        select.value = techId;
        select.dispatchEvent(new Event('change', { bubbles: true }));

        const badge = document.getElementById('ai-tech-selected-badge');
        const nameSpan = document.getElementById('ai-tech-selected-name');
        const reasonSpan = document.getElementById('ai-tech-selected-reason');

        if (badge && nameSpan && reasonSpan) {
            nameSpan.textContent = techName;
            reasonSpan.textContent = `Compatibilité ${matchLabel}`;
            badge.style.display = 'inline-flex';
        }
    }

    closeTechRecommendationModal();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

{{-- Modal: AI Technician Matchmaker --}}
<div id="modal-tech-recommendation" class="intelligence-modal-backdrop" style="display: none;" onclick="if(event.target === this) closeTechRecommendationModal()">
    <div class="intelligence-modal-dialog" style="max-width: 760px; max-height: 88vh; background: #fff; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); display: flex; flex-direction: column; overflow: hidden; margin: auto;">
        {{-- Header --}}
        <div class="intelligence-modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: flex-start; background: #fbfdfc; flex-shrink: 0;">
            <div>
                <span class="eyebrow" style="color: var(--aqua); font-size: 11px; font-weight: 800; letter-spacing: 0.1em;">AI TECHNICIAN MATCHMAKER · INTELLIGENCE OPÉRATIONNELLE</span>
                <h2 style="margin: 4px 0 0; font-size: 20px; color: var(--deep);">Recommandation des Meilleurs Techniciens Éligibles</h2>
                <small style="color: var(--muted);">Classement factuel basé sur les spécialités, disponibilités réelles et charges de travail</small>
            </div>
            <button class="modal-close-btn" type="button" onclick="closeTechRecommendationModal()" style="border: 0; background: none; font-size: 20px; cursor: pointer; color: var(--muted);">✕</button>
        </div>

        {{-- Mission Context Pill Strip --}}
        <div style="background: #f1f8f7; border-bottom: 1px solid var(--line); padding: 10px 24px; display: flex; gap: 14px; font-size: 12px; color: var(--deep); flex-shrink: 0; flex-wrap: wrap;">
            <span>🔧 Mission : <strong id="tech-rec-ctx-type">—</strong></span>
            <span>⚡ Priorité : <strong id="tech-rec-ctx-priority">—</strong></span>
            <span>▦ Site : <strong id="tech-rec-ctx-infra">—</strong></span>
            <span id="tech-rec-ai-badge" style="margin-left: auto; font-weight: 700; color: var(--aqua);">✨ Analyse Groq AI</span>
        </div>

        {{-- Scrollable Body --}}
        <div class="intelligence-modal-body" style="padding: 20px 24px; overflow-y: auto; flex: 1 1 auto; min-height: 0;">
            {{-- Loading State --}}
            <div id="tech-rec-loading" style="display: none; text-align: center; padding: 40px 20px;">
                <div style="width: 48px; height: 48px; border: 4px solid #e2e8f0; border-top-color: var(--aqua); border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px;"></div>
                <h3 style="font-size: 16px; margin: 0 0 6px; color: var(--deep);">Recherche et classement des techniciens en cours…</h3>
                <p style="font-size: 13px; color: var(--muted); margin: 0;">Évaluation des contraintes d'éligibilité, calcul des correspondances techniques et pondération des charges actives.</p>
            </div>

            {{-- Error State --}}
            <div id="tech-rec-error" style="display: none; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                <strong style="color: #991b1b; display: block; margin-bottom: 4px;">Échec de la recommandation</strong>
                <p id="tech-rec-error-msg" style="color: #b91c1c; font-size: 13px; margin: 0;"></p>
            </div>

            {{-- Empty State --}}
            <div id="tech-rec-empty" style="display: none; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 20px; text-align: center;">
                <div style="font-size: 28px; margin-bottom: 8px;">👷‍♂️</div>
                <h4 style="font-size: 15px; color: #92400e; margin: 0 0 6px;">Aucun candidat éligible disponible</h4>
                <p id="tech-rec-empty-reason" style="font-size: 13px; color: #b45309; margin: 0;"></p>
            </div>

            {{-- Results Container --}}
            <div id="tech-rec-results" style="display: none;">
                <div id="tech-rec-cards-container"></div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="intelligence-modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; background: #fbfdfc; flex-shrink: 0;">
            <button type="button" class="button button-outline" onclick="closeTechRecommendationModal()">Fermer</button>
        </div>
    </div>
</div>

