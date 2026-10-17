@extends('layouts.back')

@section('content')
<div class="maintenance-intelligence-container" id="intelligence-app">
    {{-- Header with AI visual identity --}}
    <div class="intelligence-header">
        <div class="intelligence-header-info">
            <div class="ai-pill-tag">
                <span class="ai-glow-dot"></span>
                <span>CENTRE D'INGÉNIERIE IA · AQUASECURE</span>
            </div>
            <h1>AI Maintenance Intelligence Center</h1>
            <p>Diagnostic algorithmique des risques hydrauliques, détection préventive des défaillances et planification automatisée des opérations d'ingénierie.</p>
        </div>

        <div class="intelligence-header-actions">
            <button id="btn-scan-risks" class="button button-ai-scan" type="button" onclick="runMaintenanceScan()">
                <span class="scan-icon-wrapper">
                    <svg class="scan-svg-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/>
                        <circle cx="12" cy="12" r="4"/>
                    </svg>
                </span>
                <span class="scan-btn-text">Scanner les risques de maintenance</span>
            </button>
        </div>
    </div>

    {{-- Status bar: Scan timestamp and freshness indicator --}}
    <div class="scan-status-strip" id="scan-status-strip">
        <div class="status-left">
            <span class="status-indicator-dot {{ $latestScan ? 'status-active' : 'status-waiting' }}"></span>
            <span class="status-label-text" id="status-label-text">
                @if($latestScan)
                    Dernière analyse exécutée le <strong id="scan-time-display">{{ $latestScan->created_at->format('d/m/Y à H:i') }}</strong>
                    @if($latestScan->user)
                        par <span class="scan-author">{{ $latestScan->user->name }}</span>
                    @endif
                @else
                    En attente d'une première analyse du parc hydraulique.
                @endif
            </span>
        </div>

        <div class="status-right">
            <span class="badge {{ $latestScan ? 'badge-success' : 'badge-neutral' }}" id="scan-freshness-badge">
                {{ $latestScan ? 'Résultats enregistrés' : 'En attente de scan' }}
            </span>
        </div>
    </div>

    {{-- Scan In-Progress Staged Panel (hidden by default) --}}
    <div id="scan-progress-panel" class="scan-progress-panel" style="display: none;">
        <div class="scan-progress-inner">
            <div class="scan-radar-anim">
                <div class="radar-circle circle-1"></div>
                <div class="radar-circle circle-2"></div>
                <div class="radar-circle circle-3"></div>
                <div class="radar-core">⚡</div>
            </div>
            <div class="scan-progress-details">
                <h3 id="scan-step-title">Collecte des données d'infrastructure...</h3>
                <p id="scan-step-desc">Agrégation des indicateurs opérationnels, criticité des ouvrages et historique d'intervention.</p>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="scan-progress-fill" style="width: 25%;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI Cards & Overall Risk Indicator --}}
    <div class="intelligence-kpi-row">
        {{-- Overall Maintenance Risk Score Card --}}
        <div class="risk-gauge-card">
            <div class="gauge-card-header">
                <span class="gauge-card-title">Indicateur Global de Risque</span>
                <span class="gauge-badge badge-neutral" id="overall-risk-badge">
                    {{ $latestScan && isset($latestScan->summary_metrics['overall_risk_label']) ? $latestScan->summary_metrics['overall_risk_label'] : 'Non calculé' }}
                </span>
            </div>
            <div class="gauge-score-wrap">
                <div class="gauge-score-circle" id="gauge-score-circle">
                    <strong id="overall-risk-score">{{ $latestScan && isset($latestScan->summary_metrics['overall_risk_score']) ? $latestScan->summary_metrics['overall_risk_score'] : '--' }}</strong>
                    <small>/ 100</small>
                </div>
                <div class="gauge-score-text">
                    <p id="overall-risk-desc">
                        {{ $latestScan && isset($latestScan->summary_metrics['formula_explanation']) ? $latestScan->summary_metrics['formula_explanation'] : 'Le score global sera calculé lors du prochain scan déterministe.' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Summary Metrics Grid --}}
        <div class="summary-metrics-grid">
            <div class="kpi-metric-card">
                <span class="metric-label">Infrastructures analysées</span>
                <strong class="metric-value" id="kpi-infras-count">
                    {{ $latestScan ? ($latestScan->summary_metrics['infrastructures_analyzed'] ?? $totalInfrastructures) : $totalInfrastructures }}
                </strong>
                <small class="metric-hint">Périmètre complet du réseau</small>
            </div>

            <div class="kpi-metric-card metric-critical">
                <span class="metric-label">Risques critiques détectés</span>
                <strong class="metric-value" id="kpi-critical-count">
                    {{ $latestScan ? ($latestScan->summary_metrics['critical_findings'] ?? 0) : 0 }}
                </strong>
                <small class="metric-hint text-critical">Intervention immédiate requise</small>
            </div>

            <div class="kpi-metric-card metric-high">
                <span class="metric-label">Risques élevés</span>
                <strong class="metric-value" id="kpi-high-count">
                    {{ $latestScan ? ($latestScan->summary_metrics['high_findings'] ?? 0) : 0 }}
                </strong>
                <small class="metric-hint text-high">À planifier sous 48-72h</small>
            </div>

            <div class="kpi-metric-card">
                <span class="metric-label">Maintenances en retard</span>
                <strong class="metric-value text-danger" id="kpi-overdue-count">
                    {{ $latestScan ? ($latestScan->summary_metrics['overdue_maintenances'] ?? $overdueCount) : $overdueCount }}
                </strong>
                <small class="metric-hint">Échéances opérationnelles dépassées</small>
            </div>

            <div class="kpi-metric-card">
                <span class="metric-label">Échéances imminentes</span>
                <strong class="metric-value text-warning" id="kpi-duesoon-count">
                    {{ $latestScan ? ($latestScan->summary_metrics['due_soon_maintenances'] ?? $dueSoonCount) : $dueSoonCount }}
                </strong>
                <small class="metric-hint">Sous 14 jours calendaires</small>
            </div>

            <div class="kpi-metric-card metric-action">
                <span class="metric-label">Dossiers à réviser</span>
                <strong class="metric-value text-aqua" id="kpi-review-count">
                    {{ $latestScan ? ($latestScan->summary_metrics['requiring_review'] ?? 0) : 0 }}
                </strong>
                <small class="metric-hint">Soumis à validation humaine</small>
            </div>
        </div>
    </div>

    {{-- Findings Section --}}
    <div class="findings-panel">
        <div class="findings-panel-header">
            <div>
                <h2>Diagnostics et Recommandations Priorisées</h2>
                <p>Vulnérabilités techniques détectées et argumentées à partir des données réelles du parc hydraulique.</p>
            </div>
            <div class="findings-count-badge">
                <span id="findings-total-badge">{{ $latestScan ? count($latestScan->findings ?? []) : 0 }}</span> anomalie(s)
            </div>
        </div>

        {{-- Filters & Search Toolbar --}}
        <div class="findings-toolbar">
            <div class="search-input-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" id="finding-search-input" placeholder="Rechercher par ouvrage, code (INF-...), problème..." oninput="applyFilters()">
            </div>

            <div class="filters-wrap">
                <select id="filter-risk-level" onchange="applyFilters()">
                    <option value="">Tous les niveaux de risque</option>
                    <option value="critical">Critique</option>
                    <option value="high">Élevé</option>
                    <option value="medium">Moyen</option>
                    <option value="low">Faible</option>
                </select>

                <select id="filter-zone" onchange="applyFilters()">
                    <option value="">Toutes les zones</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->name }}">{{ $zone->name }}</option>
                    @endforeach
                </select>

                <select id="filter-type" onchange="applyFilters()">
                    <option value="">Tous les types d'ouvrage</option>
                    @foreach($infrastructureTypes as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>

                <select id="sort-findings" onchange="applyFilters()">
                    <option value="urgency">Trier par : Urgence décroissante</option>
                    <option value="overdue">Trier par : Retard le plus important</option>
                    <option value="name">Trier par : Nom d'infrastructure (A-Z)</option>
                </select>

                <button class="button button-small button-outline" type="button" onclick="resetFilters()">
                    Réinitialiser
                </button>
            </div>
        </div>

        {{-- Findings List Container --}}
        <div id="findings-container" class="findings-grid">
            @if($latestScan && !empty($latestScan->findings))
                @foreach($latestScan->findings as $index => $finding)
                    <div class="finding-card risk-border-{{ $finding['risk_level'] ?? 'medium' }}"
                         data-risk="{{ $finding['risk_level'] ?? 'medium' }}"
                         data-zone="{{ $finding['zone_name'] ?? '' }}"
                         data-type="{{ $finding['infrastructure_type'] ?? '' }}"
                         data-name="{{ strtolower($finding['infrastructure_name'] ?? '') }} {{ strtolower($finding['reference_code'] ?? '') }} {{ strtolower($finding['title'] ?? '') }}"
                         data-overdue="{{ !empty($finding['is_overdue']) ? 1 : 0 }}"
                         id="finding-card-{{ $index }}">
                        
                        <div class="finding-card-top">
                            <div class="finding-asset-info">
                                <span class="asset-code">{{ $finding['reference_code'] ?? ('INF-' . ($finding['infrastructure_id'] ?? '')) }}</span>
                                <h3 class="asset-title">{{ $finding['infrastructure_name'] ?? 'Infrastructure' }}</h3>
                                <div class="asset-meta-tags">
                                    <span class="meta-tag">📍 {{ $finding['zone_name'] ?? 'Zone non spécifiée' }}</span>
                                    <span class="meta-tag">⚙ {{ $finding['infrastructure_type'] ?? 'Équipement' }}</span>
                                    <span class="meta-tag">État : {{ $finding['condition_label'] ?? 'Non précisé' }}</span>
                                </div>
                            </div>

                            <div class="finding-risk-badge-col">
                                <span class="badge badge-risk-{{ $finding['risk_level'] ?? 'medium' }}">
                                    @switch($finding['risk_level'] ?? 'medium')
                                        @case('critical') ⚠ Risque Critique @break
                                        @case('high') ⚠ Risque Élevé @break
                                        @case('medium') ◉ Risque Modéré @break
                                        @default ◉ Risque Faible
                                    @endswitch
                                </span>
                                <span class="confidence-pill" title="Indicateur d'exhaustivité des données d'exploitation">
                                    Complétude : <strong>{{ ucfirst($finding['confidence'] ?? 'Moyenne') }}</strong>
                                </span>
                            </div>
                        </div>

                        <div class="finding-card-body">
                            <h4 class="finding-problem-title">{{ $finding['title'] ?? 'Anomalie détectée' }}</h4>
                            <p class="finding-summary-text">{{ $finding['summary'] ?? '' }}</p>

                            {{-- Verified Evidence Strip --}}
                            @if(!empty($finding['evidence']))
                                <div class="finding-evidence-strip">
                                    <span class="evidence-icon">📌</span>
                                    <span class="evidence-snippet">
                                        {{ is_array($finding['evidence']) ? $finding['evidence'][0] : $finding['evidence'] }}
                                        @if(count($finding['evidence']) > 1)
                                            <em>(+{{ count($finding['evidence']) - 1 }} fait(s) vérifié(s))</em>
                                        @endif
                                    </span>
                                </div>
                            @endif

                            {{-- Operational Dates / Urgency bar --}}
                            <div class="finding-timing-row">
                                <div class="timing-col">
                                    <small>Échéance recommandée :</small>
                                    <strong>{{ $finding['recommended_timeframe'] ?? 'À planifier' }}</strong>
                                </div>
                                <div class="timing-col">
                                    <small>Type suggéré :</small>
                                    <span class="badge badge-neutral">{{ $finding['recommended_maintenance_type'] ?? 'Préventive' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="finding-card-footer">
                            <button class="button button-small button-outline btn-view-evidence"
                                    type="button"
                                    onclick="openEvidenceModal({{ json_encode($finding) }})">
                                🔍 Consulter l'analyse
                            </button>

                            <button class="button button-small button-ai-plan"
                                    type="button"
                                    onclick="openPlannerForFinding({{ json_encode($finding) }})">
                                ✨ Générer un plan de maintenance
                            </button>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="empty-findings-state" id="empty-findings-state">
                    <div class="empty-icon-art">⚡</div>
                    <h3>Prêt pour l'analyse des risques</h3>
                    <p>Aucune analyse n'est actuellement affichée. Cliquez sur le bouton « Scanner les risques de maintenance » pour examiner le parc d'infrastructures en temps réel.</p>
                    <button class="button button-ai-scan" type="button" onclick="runMaintenanceScan()">
                        Lancer le scan maintenant
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- =========================================================================
     MODAL 1: Evidence & Technical Diagnostic Detail (Stage 1)
     ========================================================================= --}}
<div id="modal-evidence-detail" class="intelligence-modal-backdrop" style="display: none;" onclick="if(event.target === this) closeEvidenceModal()">
    <div class="intelligence-modal-dialog">
        <div class="intelligence-modal-header">
            <div>
                <span class="eyebrow">DIAGNOSTIC TECHNIQUE DÉTAILLÉ</span>
                <h2 id="modal-evidence-infra-name">Ouvrage</h2>
                <p id="modal-evidence-ref">Réf: INF-000</p>
            </div>
            <button class="modal-close-btn" type="button" onclick="closeEvidenceModal()">✕</button>
        </div>

        <div class="intelligence-modal-body">
            {{-- Risk Level Banner --}}
            <div id="modal-evidence-risk-banner" class="modal-risk-banner">
                <span id="modal-evidence-risk-badge" class="badge">Risque</span>
                <p id="modal-evidence-title" class="font-bold text-base mt-1"></p>
                <p id="modal-evidence-summary" class="text-sm mt-1"></p>
            </div>

            {{-- Verified Facts vs AI Interpretation Grid --}}
            <div class="evidence-two-col-grid">
                <div class="evidence-box facts-box">
                    <div class="box-head">
                        <span class="box-icon">📋</span>
                        <h4>Faits Avérés (Base de Données)</h4>
                    </div>
                    <ul id="modal-evidence-facts-list" class="evidence-list"></ul>
                </div>

                <div class="evidence-box ai-box">
                    <div class="box-head">
                        <span class="box-icon">⚡</span>
                        <h4>Analyse d'Impact & Facteurs Aggravants</h4>
                    </div>
                    <ul id="modal-evidence-factors-list" class="evidence-list"></ul>
                </div>
            </div>

            {{-- Consequences & Recommended Actions --}}
            <div class="evidence-section mt-4">
                <h4>Risques potentiels en cas de non-intervention</h4>
                <ul id="modal-evidence-consequences-list" class="consequences-list"></ul>
            </div>

            <div class="evidence-section mt-4">
                <h4>Actions recommandées par ordre de priorité</h4>
                <ol id="modal-evidence-actions-list" class="actions-numbered-list"></ol>
            </div>

            {{-- Missing Data Alert --}}
            <div id="modal-evidence-missing-box" class="missing-data-alert mt-4" style="display: none;">
                <strong>⚠️ Informations manquantes ou incomplètes dans le dossier :</strong>
                <ul id="modal-evidence-missing-list"></ul>
            </div>
        </div>

        <div class="intelligence-modal-footer">
            <button class="button button-outline" type="button" onclick="closeEvidenceModal()">Fermer</button>
            <button id="modal-evidence-btn-plan" class="button button-ai-plan" type="button">
                ✨ Générer un plan de maintenance pour cet ouvrage
            </button>
        </div>
    </div>
</div>

{{-- =========================================================================
     MODAL 2: Interactive AI Maintenance Planner (Stage 2)
     ========================================================================= --}}
<div id="modal-planner" class="intelligence-modal-backdrop" style="display: none;" onclick="if(event.target === this) closePlannerModal()">
    <div class="intelligence-modal-dialog modal-dialog-large">
        <div class="intelligence-modal-header">
            <div>
                <div class="workflow-steps-indicator">
                    <span class="step-item step-done">1. Scan</span>
                    <span class="step-arrow">➔</span>
                    <span class="step-item step-done">2. Analyse</span>
                    <span class="step-arrow">➔</span>
                    <span class="step-item step-active">3. Plan IA</span>
                    <span class="step-arrow">➔</span>
                    <span class="step-item">4. Révision & Création</span>
                </div>
                <h2>Planificateur de Maintenance Assisté par IA</h2>
                <p id="planner-subheading">Formulation de l'ordre de travail et calibrage des ressources.</p>
            </div>
            <button class="modal-close-btn" type="button" onclick="closePlannerModal()">✕</button>
        </div>

        {{-- Planner Loading Panel --}}
        <div id="planner-loading-view" class="planner-loading-container" style="display: none;">
            <div class="planner-spinner-anim"></div>
            <h3>Génération de la proposition technique par l'IA...</h3>
            <p>Le modèle openai/gpt-oss-120b évalue l'historique d'exploitation, les contraintes de sécurité et formule la gamme opératoire.</p>
            <div class="planner-loading-steps">
                <span class="loading-substep active">Analyse du composant</span>
                <span class="loading-substep active">Évaluation des compétences</span>
                <span class="loading-substep active">Élaboration de la checklist</span>
            </div>
        </div>

        {{-- Planner Interactive Form --}}
        <form id="form-create-maintenance" onsubmit="submitCreatedMaintenance(event)">
            @csrf
            <input type="hidden" name="infrastructure_id" id="plan-input-infra-id">

            <div class="intelligence-modal-body" id="planner-form-view">
                {{-- AI Plan Proposal Header Banner --}}
                <div class="ai-plan-banner">
                    <div class="banner-icon">⚡</div>
                    <div class="banner-content">
                        <span class="banner-tag">PROPOSITION GÉNÉRÉE PAR L'IA</span>
                        <h3 id="plan-display-title">Plan de maintenance</h3>
                        <p id="plan-display-justification"></p>
                    </div>
                </div>

                {{-- Section A: Parameters & Scheduling --}}
                <div class="planner-section">
                    <h4 class="section-title">A. Paramètres & Programmation de l'Intervention</h4>
                    <div class="form-row-grid-4">
                        <div class="form-group">
                            <label for="plan-field-type">Type d'intervention <span class="text-danger">*</span></label>
                            <select name="type" id="plan-field-type" required>
                                @foreach($maintTypes as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="plan-field-priority">Niveau de priorité <span class="text-danger">*</span></label>
                            <select name="priority" id="plan-field-priority" required>
                                @foreach($priorities as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="plan-field-scheduled-at">Date & heure prévisionnelles <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" id="plan-field-scheduled-at" required>
                            <small class="field-hint">Suggérée par l'IA, modifiable</small>
                        </div>

                        <div class="form-group">
                            <label for="plan-field-duration">Durée estimée (heures)</label>
                            <input type="number" step="0.5" min="0.5" max="500" name="duration_hours" id="plan-field-duration">
                            <small class="field-hint">Estimation prévisionnelle</small>
                        </div>
                    </div>

                    <div class="form-row-grid-3 mt-3">
                        <div class="form-group">
                            <label for="plan-field-technician">Technicien assigné</label>
                            <select name="technician_id" id="plan-field-technician">
                                <option value="">-- Sélectionner un technicien --</option>
                                @foreach($technicians as $tech)
                                    <option value="{{ $tech->id }}">
                                        {{ $tech->name }} ({{ $tech->speciality ?? 'Général' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="plan-field-team">Équipe d'intervention</label>
                            <input type="text" name="team" id="plan-field-team" placeholder="Ex: Équipe Réseau Nord">
                        </div>

                        <div class="form-group">
                            <label for="plan-field-cost">Coût prévisionnel (€ / TND)</label>
                            <input type="number" step="10" min="0" name="cost" id="plan-field-cost" placeholder="0.00">
                        </div>
                    </div>
                </div>

                {{-- Section B: Work Order Description --}}
                <div class="planner-section mt-4">
                    <div class="section-header-flex">
                        <h4 class="section-title">B. Ordre de Mission Technique</h4>
                        <span class="badge badge-neutral">Consigné dans le dossier</span>
                    </div>
                    <div class="form-group">
                        <textarea name="description" id="plan-field-description" rows="4" placeholder="Description des travaux à exécuter sur site..." required></textarea>
                    </div>
                </div>

                {{-- Section C: Execution Checklist --}}
                <div class="planner-section mt-4">
                    <div class="section-header-flex">
                        <h4 class="section-title">C. Gamme Opératoire & Checklist Technique</h4>
                        <button type="button" class="btn-text-action" onclick="addChecklistItem()">+ Ajouter une étape</button>
                    </div>
                    <p class="section-subtitle">Étapes opérationnelles à vérifier sur site avant clôture :</p>
                    <div id="plan-checklist-container" class="checklist-interactive-list"></div>
                </div>

                {{-- Section D: Resources, Safety & Materials --}}
                <div class="planner-section mt-4">
                    <h4 class="section-title">D. Préconisations de Sécurité & Outillage</h4>
                    <div class="form-row-grid-2">
                        <div class="info-card">
                            <span class="info-card-title">🦺 Mesures de sécurité requises</span>
                            <ul id="plan-safety-list" class="compact-bullets"></ul>
                        </div>
                        <div class="info-card">
                            <span class="info-card-title">🔧 Outillage & Pièces recommandées</span>
                            <ul id="plan-tools-list" class="compact-bullets"></ul>
                        </div>
                    </div>
                </div>

                {{-- Section E: Verification & Follow-up --}}
                <div class="planner-section mt-4">
                    <h4 class="section-title">E. Contrôles de Réception & Prochaine Échéance</h4>
                    <div class="form-row-grid-2">
                        <div class="form-group">
                            <label for="plan-field-next-date">Prochaine échéance recommandée</label>
                            <input type="date" name="next_maintenance_date" id="plan-field-next-date">
                            <small class="field-hint">Mettra à jour la date d'échéance de l'ouvrage</small>
                        </div>
                        <div class="form-group">
                            <label>Résultat technique attendu</label>
                            <p id="plan-display-expected-result" class="text-sm text-muted bg-paper p-2 rounded border"></p>
                        </div>
                    </div>
                </div>

                {{-- Section F: AI Disclaimers & Assumptions --}}
                <div id="plan-assumptions-box" class="ai-disclaimer-box mt-4">
                    <strong>⚠️ Précision d'ingénierie :</strong>
                    <span id="plan-assumptions-text">Les durées et coûts sont des estimations calculées par l'IA soumises à la validation finale du responsable technique.</span>
                </div>
            </div>

            <div class="intelligence-modal-footer sticky-footer">
                <button class="button button-outline" type="button" onclick="regeneratePlanWithPrompt()">
                    🔄 Régénérer le plan
                </button>
                <button class="button button-outline" type="button" onclick="resetToAiSuggestions()">
                    ↺ Rétablir suggestions IA
                </button>
                <button class="button button-ghost" type="button" onclick="closePlannerModal()">
                    Annuler
                </button>
                <button id="btn-submit-maintenance" class="button button-primary ml-auto" type="submit">
                    ✓ Créer la maintenance
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Toast Notification Container --}}
<div id="intelligence-toast" class="intelligence-toast" style="display: none;">
    <span id="toast-icon">✓</span>
    <span id="toast-message">Notification</span>
</div>
@endsection

@push('styles')
<style>
/* AI Maintenance Intelligence Center Custom High-End Styling */
.maintenance-intelligence-container {
    max-width: 1280px;
    margin: 0 auto;
}

/* Header */
.intelligence-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 24px;
    gap: 20px;
    flex-wrap: wrap;
}
.intelligence-header-info h1 {
    font-size: 32px;
    font-weight: 800;
    color: var(--deep);
    letter-spacing: -0.04em;
    margin: 6px 0;
}
.intelligence-header-info p {
    color: var(--muted);
    font-size: 14px;
    max-width: 680px;
    margin: 0;
}
.ai-pill-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #e2f6f2;
    color: var(--aqua);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.1em;
    border: 1px solid rgba(22, 183, 163, 0.3);
}
.ai-glow-dot {
    width: 7px;
    height: 7px;
    background: var(--aqua);
    border-radius: 50%;
    box-shadow: 0 0 8px var(--aqua);
    animation: pulseGlow 2s infinite;
}
@keyframes pulseGlow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}

/* Action button */
.button-ai-scan {
    background: linear-gradient(135deg, var(--deep) 0%, #0d5f6e 100%);
    color: #fff;
    box-shadow: 0 4px 15px rgba(7, 63, 74, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.2s ease;
}
.button-ai-scan:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(7, 63, 74, 0.35);
}
.button-ai-plan {
    background: linear-gradient(135deg, #106b7a 0%, #16b7a3 100%);
    color: #fff;
    border: none;
    font-weight: 700;
}
.button-ai-plan:hover {
    filter: brightness(1.05);
}
.scan-svg-icon {
    display: inline-block;
    vertical-align: middle;
}
.is-scanning .scan-svg-icon {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Status strip */
.scan-status-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 6px;
    padding: 10px 18px;
    margin-bottom: 24px;
    font-size: 13px;
}
.status-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.status-indicator-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
}
.status-active { background: #13886f; box-shadow: 0 0 6px rgba(19, 136, 111, 0.5); }
.status-waiting { background: #aa721c; }
.scan-author { font-weight: 700; color: var(--deep); }

/* Staged Progress Panel */
.scan-progress-panel {
    background: linear-gradient(135deg, #073f4a 0%, #0b5563 100%);
    color: #fff;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 10px 30px rgba(7, 63, 74, 0.2);
    animation: fadeIn 0.3s ease;
}
.scan-progress-inner {
    display: flex;
    align-items: center;
    gap: 28px;
}
.scan-radar-anim {
    position: relative;
    width: 70px;
    height: 70px;
    display: grid;
    place-items: center;
}
.radar-circle {
    position: absolute;
    border: 1.5px solid rgba(22, 183, 163, 0.4);
    border-radius: 50%;
    animation: radarRipple 2s linear infinite;
}
.circle-1 { width: 35px; height: 35px; animation-delay: 0s; }
.circle-2 { width: 55px; height: 55px; animation-delay: 0.6s; }
.circle-3 { width: 70px; height: 70px; animation-delay: 1.2s; }
.radar-core {
    font-size: 24px;
    z-index: 2;
    color: var(--aqua);
}
@keyframes radarRipple {
    0% { transform: scale(0.6); opacity: 1; }
    100% { transform: scale(1.2); opacity: 0; }
}
.scan-progress-details { flex: 1; }
.scan-progress-details h3 { margin: 0 0 4px; font-size: 17px; color: #fff; }
.scan-progress-details p { margin: 0 0 12px; font-size: 13px; color: #a8c8c7; }
.progress-bar-container {
    height: 6px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: var(--aqua);
    transition: width 0.4s ease;
}

/* KPI & Gauge Row */
.intelligence-kpi-row {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 20px;
    margin-bottom: 28px;
}
.risk-gauge-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.gauge-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.gauge-card-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--deep);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.gauge-score-wrap {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-top: 14px;
}
.gauge-score-circle {
    width: 82px;
    height: 82px;
    border-radius: 50%;
    background: var(--soft);
    border: 3px solid var(--aqua);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.gauge-score-circle strong {
    font-size: 26px;
    line-height: 1;
    color: var(--deep);
}
.gauge-score-circle small {
    font-size: 10px;
    color: var(--muted);
}
.gauge-score-text p {
    font-size: 12px;
    color: var(--muted);
    margin: 0;
    line-height: 1.45;
}

/* Metrics Grid */
.summary-metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
.kpi-metric-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.metric-label {
    font-size: 12px;
    color: var(--muted);
    font-weight: 600;
}
.metric-value {
    font-size: 26px;
    font-weight: 800;
    color: var(--ink);
    margin: 6px 0;
}
.metric-hint {
    font-size: 11px;
    color: var(--muted);
}
.text-critical { color: #bd5149 !important; }
.text-high { color: #d97706 !important; }
.text-danger { color: #a8473d !important; }
.text-warning { color: #896116 !important; }
.text-aqua { color: var(--aqua) !important; }

/* Findings Panel */
.findings-panel {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 24px;
}
.findings-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.findings-panel-header h2 {
    font-size: 20px;
    color: var(--deep);
    margin: 0 0 4px;
}
.findings-panel-header p {
    font-size: 13px;
    color: var(--muted);
    margin: 0;
}
.findings-count-badge {
    background: var(--soft);
    color: var(--deep);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

/* Findings Toolbar */
.findings-toolbar {
    display: flex;
    gap: 12px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}
.search-input-wrap {
    position: relative;
    flex: 1;
    min-width: 260px;
}
.search-input-wrap .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 13px;
    color: var(--muted);
}
.search-input-wrap input {
    width: 100%;
    padding: 10px 12px 10px 34px;
    border: 1px solid var(--line);
    border-radius: 4px;
    font: inherit;
    font-size: 13px;
}
.filters-wrap {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.filters-wrap select {
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: 4px;
    font: inherit;
    font-size: 13px;
    background: #fff;
}

/* Findings Grid & Cards */
.findings-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.finding-card {
    border: 1px solid var(--line);
    border-left-width: 5px;
    border-radius: 6px;
    padding: 20px;
    background: #fff;
    transition: box-shadow 0.2s ease;
}
.finding-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}
.risk-border-critical { border-left-color: #bd5149; }
.risk-border-high { border-left-color: #ea580c; }
.risk-border-medium { border-left-color: #0284c7; }
.risk-border-low { border-left-color: #708388; }

.finding-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
}
.asset-code {
    display: inline-block;
    font-size: 11px;
    font-weight: 800;
    color: var(--muted);
    letter-spacing: 0.05em;
}
.asset-title {
    font-size: 18px;
    color: var(--deep);
    margin: 2px 0 6px;
}
.asset-meta-tags {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: var(--muted);
    flex-wrap: wrap;
}
.finding-risk-badge-col {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}
.badge-risk-critical { background: #ffe4e1; color: #bd5149; font-weight: 800; }
.badge-risk-high { background: #ffedd5; color: #c2410c; font-weight: 800; }
.badge-risk-medium { background: #e0f2fe; color: #0369a1; font-weight: 700; }
.badge-risk-low { background: #f1f5f9; color: #475569; font-weight: 700; }

.confidence-pill {
    font-size: 10px;
    color: var(--muted);
}
.finding-card-body {
    margin: 14px 0;
    padding: 12px 0;
    border-top: 1px solid #f1f5f4;
    border-bottom: 1px solid #f1f5f4;
}
.finding-problem-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 6px;
}
.finding-summary-text {
    font-size: 13px;
    color: var(--muted);
    margin: 0 0 10px;
    line-height: 1.5;
}
.finding-evidence-strip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fbfdfc;
    border: 1px dashed var(--line);
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    color: var(--deep);
    margin-bottom: 10px;
}
.finding-timing-row {
    display: flex;
    gap: 24px;
    font-size: 12px;
}
.timing-col small { display: block; color: var(--muted); }
.timing-col strong { color: var(--ink); }

.finding-card-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 12px;
}

/* Empty State */
.empty-findings-state {
    text-align: center;
    padding: 60px 20px;
    background: #fbfdfc;
    border: 2px dashed var(--line);
    border-radius: 8px;
}
.empty-icon-art {
    font-size: 42px;
    color: var(--aqua);
    margin-bottom: 12px;
}
.empty-findings-state h3 {
    font-size: 20px;
    color: var(--deep);
    margin: 0 0 6px;
}
.empty-findings-state p {
    color: var(--muted);
    font-size: 14px;
    max-width: 500px;
    margin: 0 auto 20px;
}

/* Modals */
.intelligence-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(7, 63, 74, 0.65);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    overflow-y: auto;
}
.intelligence-modal-backdrop[style*="display: none"],
.intelligence-modal-backdrop[style*="display:none"],
.intelligence-modal-backdrop.hidden {
    display: none !important;
}
.intelligence-modal-dialog {
    background: #fff;
    border-radius: 8px;
    width: 100%;
    max-width: 780px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    animation: modalPop 0.2s ease;
    margin: auto;
}
.modal-dialog-large {
    max-width: 1020px;
    height: 90vh;
    max-height: 90vh;
}
@keyframes modalPop {
    from { transform: scale(0.96); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.intelligence-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px 28px;
    border-bottom: 1px solid var(--line);
    background: #fbfdfc;
    flex-shrink: 0;
}
.intelligence-modal-header h2 { margin: 4px 0 0; font-size: 22px; color: var(--deep); }
.modal-close-btn {
    border: 0;
    background: none;
    font-size: 20px;
    color: var(--muted);
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
}
.modal-close-btn:hover { background: #eef3f2; color: var(--ink); }

#form-create-maintenance {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    height: 100%;
    overflow: hidden;
}

.intelligence-modal-body {
    padding: 24px 28px;
    overflow-y: auto !important;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    flex: 1 1 auto;
    min-height: 0;
}

#planner-form-view {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto !important;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

/* Custom sleek scrollbar for modal body */
.intelligence-modal-body::-webkit-scrollbar,
#planner-form-view::-webkit-scrollbar {
    width: 8px;
}
.intelligence-modal-body::-webkit-scrollbar-track,
#planner-form-view::-webkit-scrollbar-track {
    background: #f1f5f4;
    border-radius: 4px;
}
.intelligence-modal-body::-webkit-scrollbar-thumb,
#planner-form-view::-webkit-scrollbar-thumb {
    background: #b9d5d1;
    border-radius: 4px;
}
.intelligence-modal-body::-webkit-scrollbar-thumb:hover,
#planner-form-view::-webkit-scrollbar-thumb:hover {
    background: var(--aqua);
}

.intelligence-modal-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    padding: 16px 28px;
    border-top: 1px solid var(--line);
    background: #fbfdfc;
    flex-shrink: 0;
}
.sticky-footer {
    position: sticky;
    bottom: 0;
    z-index: 10;
}

/* Modal Inner Components */
.modal-risk-banner {
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 20px;
}
.evidence-two-col-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.evidence-box {
    border: 1px solid var(--line);
    border-radius: 6px;
    padding: 16px;
    background: #fff;
}
.evidence-box .box-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    border-bottom: 1px solid #f1f5f4;
    padding-bottom: 8px;
}
.evidence-box h4 { margin: 0; font-size: 13px; color: var(--deep); }
.evidence-list {
    margin: 0;
    padding-left: 18px;
    font-size: 12px;
    color: var(--ink);
    line-height: 1.6;
}
.consequences-list, .actions-numbered-list {
    font-size: 13px;
    color: var(--ink);
    line-height: 1.6;
    margin: 8px 0 0 18px;
}
.missing-data-alert {
    background: #fff8eb;
    border: 1px solid #fde68a;
    border-radius: 6px;
    padding: 14px;
    font-size: 12px;
    color: #92400e;
}
.missing-data-alert ul { margin: 6px 0 0 18px; }

/* AI Planner specific styling */
.workflow-steps-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: var(--muted);
    font-weight: 700;
}
.step-item.step-done { color: var(--aqua); }
.step-item.step-active {
    background: var(--aqua);
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
}
.step-arrow { color: #ccd9d7; font-size: 10px; }
.ai-plan-banner {
    display: flex;
    gap: 16px;
    background: linear-gradient(135deg, #eef9f7 0%, #e0f4f0 100%);
    border: 1px solid rgba(22, 183, 163, 0.3);
    border-radius: 6px;
    padding: 18px;
    margin-bottom: 20px;
}
.ai-plan-banner .banner-icon { font-size: 26px; }
.banner-tag {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: var(--aqua);
}
.ai-plan-banner h3 { margin: 2px 0 6px; font-size: 17px; color: var(--deep); }
.ai-plan-banner p { margin: 0; font-size: 13px; color: var(--ink); }

.planner-section {
    border-top: 1px solid var(--line);
    padding-top: 16px;
}
.section-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--deep);
    margin: 0 0 12px;
}
.section-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.btn-text-action {
    background: none;
    border: 0;
    color: var(--aqua);
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
}
.btn-text-action:hover { text-decoration: underline; }
.form-row-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.form-row-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.form-row-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--deep);
    margin-bottom: 5px;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--line);
    border-radius: 4px;
    font: inherit;
    font-size: 13px;
    background: #fff;
}
.field-hint {
    display: block;
    font-size: 11px;
    color: var(--muted);
    margin-top: 3px;
}

.checklist-interactive-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.checklist-item-row {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fbfdfc;
    border: 1px solid var(--line);
    padding: 8px 12px;
    border-radius: 4px;
}
.checklist-item-row input[type="text"] {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 13px;
    padding: 2px 0;
}
.btn-remove-step {
    border: 0;
    background: none;
    color: var(--muted);
    cursor: pointer;
    font-size: 14px;
}
.btn-remove-step:hover { color: #bd5149; }
.info-card {
    background: #fbfdfc;
    border: 1px solid var(--line);
    border-radius: 4px;
    padding: 14px;
}
.info-card-title {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--deep);
    margin-bottom: 8px;
}
.compact-bullets {
    margin: 0;
    padding-left: 18px;
    font-size: 12px;
    color: var(--ink);
    line-height: 1.5;
}
.ai-disclaimer-box {
    background: #f4f8f7;
    border-left: 3px solid var(--aqua);
    padding: 10px 14px;
    font-size: 12px;
    color: var(--muted);
    border-radius: 0 4px 4px 0;
}

/* Planner Loading */
.planner-loading-container {
    padding: 60px 20px;
    text-align: center;
}
.planner-spinner-anim {
    width: 48px;
    height: 48px;
    border: 4px solid var(--soft);
    border-top-color: var(--aqua);
    border-radius: 50%;
    margin: 0 auto 18px;
    animation: spin 0.8s linear infinite;
}
.planner-loading-container h3 { font-size: 18px; color: var(--deep); margin: 0 0 6px; }
.planner-loading-container p { font-size: 13px; color: var(--muted); max-width: 480px; margin: 0 auto 20px; }
.planner-loading-steps {
    display: flex;
    justify-content: center;
    gap: 16px;
    font-size: 12px;
    font-weight: 700;
}
.loading-substep.active {
    color: var(--aqua);
    animation: pulseGlow 1.5s infinite;
}

/* Toast */
.intelligence-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--deep);
    color: #fff;
    padding: 14px 22px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    z-index: 10000;
    font-size: 14px;
    font-weight: 600;
    animation: slideUp 0.3s ease;
}
@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@media(max-width: 900px) {
    .intelligence-kpi-row { grid-template-columns: 1fr; }
    .summary-metrics-grid { grid-template-columns: repeat(2, 1fr); }
    .form-row-grid-4, .form-row-grid-3, .form-row-grid-2, .evidence-two-col-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script>
// State management
let allFindings = @json($latestScan && !empty($latestScan->findings) ? $latestScan->findings : []);
let activeFindingForPlan = null;
let currentGeneratedPlan = null;

// DOM Ready initialization
document.addEventListener('DOMContentLoaded', function() {
    applyFilters();
});

// Toast notification helper
function showToast(message, isSuccess = true) {
    const toast = document.getElementById('intelligence-toast');
    const toastIcon = document.getElementById('toast-icon');
    const toastMsg = document.getElementById('toast-message');

    toastIcon.textContent = isSuccess ? '✓' : '⚠️';
    toastMsg.textContent = message;
    toast.style.background = isSuccess ? 'var(--deep)' : '#a8473d';
    toast.style.display = 'flex';

    setTimeout(() => {
        toast.style.display = 'none';
    }, 4500);
}

// =========================================================================
// STAGE 1: Scan Execution
// =========================================================================
function runMaintenanceScan() {
    const btn = document.getElementById('btn-scan-risks');
    const btnText = btn.querySelector('.scan-btn-text');
    const progressPanel = document.getElementById('scan-progress-panel');
    const progressFill = document.getElementById('scan-progress-fill');
    const stepTitle = document.getElementById('scan-step-title');
    const stepDesc = document.getElementById('scan-step-desc');

    // UI Loading state
    btn.disabled = true;
    btn.classList.add('is-scanning');
    btnText.textContent = 'Analyse en cours...';
    progressPanel.style.display = 'block';

    const stages = [
        { pct: 25, title: "Collecte des données d'infrastructure...", desc: "Examen des statuts opérationnels, condition physique et criticité des ouvrages." },
        { pct: 50, title: "Revue des plannings de maintenance...", desc: "Détection des retards d'intervention et analyse des prochaines échéances." },
        { pct: 75, title: "Examen des incidents récents...", desc: "Recoupement des signalements et des fuites non résolues." },
        { pct: 90, title: "Évaluation du modèle IA (openai/gpt-oss-120b)...", desc: "Priorisation algorithmique et rédaction des recommandations techniques." }
    ];

    let currentStage = 0;
    const stageInterval = setInterval(() => {
        if (currentStage < stages.length) {
            progressFill.style.width = stages[currentStage].pct + '%';
            stepTitle.textContent = stages[currentStage].title;
            stepDesc.textContent = stages[currentStage].desc;
            currentStage++;
        }
    }, 1100);

    // Call server endpoint
    fetch('{{ route('maintenance-intelligence.scan') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        clearInterval(stageInterval);
        progressFill.style.width = '100%';
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Erreur lors de l\'analyse');
        }
        return data;
    })
    .then(data => {
        allFindings = data.findings || [];
        updateKpiDashboard(data.metrics, data.scanned_at);
        renderFindingsList(allFindings);
        showToast(data.message || 'Analyse achevée avec succès !', true);
    })
    .catch(error => {
        showToast(error.message || 'Erreur technique durant le scan.', false);
    })
    .finally(() => {
        setTimeout(() => {
            progressPanel.style.display = 'none';
            btn.disabled = false;
            btn.classList.remove('is-scanning');
            btnText.textContent = 'Scanner les risques de maintenance';
        }, 600);
    });
}

function updateKpiDashboard(metrics, scannedAt) {
    if (!metrics) return;

    document.getElementById('overall-risk-score').textContent = metrics.overall_risk_score ?? '--';
    document.getElementById('overall-risk-badge').textContent = metrics.overall_risk_label ?? 'Non calculé';
    document.getElementById('overall-risk-desc').textContent = metrics.formula_explanation ?? '';

    document.getElementById('kpi-infras-count').textContent = metrics.infrastructures_analyzed ?? 0;
    document.getElementById('kpi-critical-count').textContent = metrics.critical_findings ?? 0;
    document.getElementById('kpi-high-count').textContent = metrics.high_findings ?? 0;
    document.getElementById('kpi-overdue-count').textContent = metrics.overdue_maintenances ?? 0;
    document.getElementById('kpi-duesoon-count').textContent = metrics.due_soon_maintenances ?? 0;
    document.getElementById('kpi-review-count').textContent = metrics.requiring_review ?? 0;

    const timeDisplay = document.getElementById('scan-time-display');
    if (timeDisplay && scannedAt) {
        timeDisplay.textContent = scannedAt;
    }
    const freshnessBadge = document.getElementById('scan-freshness-badge');
    if (freshnessBadge) {
        freshnessBadge.textContent = 'Résultats actuels';
        freshnessBadge.className = 'badge badge-success';
    }
}

function renderFindingsList(findings) {
    const container = document.getElementById('findings-container');
    document.getElementById('findings-total-badge').textContent = findings.length;

    if (!findings || findings.length === 0) {
        container.innerHTML = `
            <div class="empty-findings-state">
                <div class="empty-icon-art">✓</div>
                <h3>Aucun risque critique identifié</h3>
                <p>Toutes les infrastructures inspectées respectent les plannings et les critères opérationnels de sécurité.</p>
            </div>
        `;
        return;
    }

    let html = '';
    findings.forEach((f, idx) => {
        const riskLabel = f.risk_level === 'critical' ? '⚠ Risque Critique' :
                          (f.risk_level === 'high' ? '⚠ Risque Élevé' :
                          (f.risk_level === 'medium' ? '◉ Risque Modéré' : '◉ Risque Faible'));

        const findingJson = JSON.stringify(f).replace(/'/g, "&#39;");

        html += `
            <div class="finding-card risk-border-${f.risk_level}"
                 data-risk="${f.risk_level}"
                 data-zone="${f.zone_name || ''}"
                 data-type="${f.infrastructure_type || ''}"
                 data-name="${(f.infrastructure_name || '').toLowerCase()} ${(f.reference_code || '').toLowerCase()} ${(f.title || '').toLowerCase()}"
                 data-overdue="${f.is_overdue ? 1 : 0}"
                 id="finding-card-${idx}">
                
                <div class="finding-card-top">
                    <div class="finding-asset-info">
                        <span class="asset-code">${f.reference_code || ('INF-' + f.infrastructure_id)}</span>
                        <h3 class="asset-title">${f.infrastructure_name || 'Infrastructure'}</h3>
                        <div class="asset-meta-tags">
                            <span class="meta-tag">📍 ${f.zone_name || 'Zone non spécifiée'}</span>
                            <span class="meta-tag">⚙ ${f.infrastructure_type || 'Équipement'}</span>
                            <span class="meta-tag">État : ${f.condition_label || 'Non précisé'}</span>
                        </div>
                    </div>

                    <div class="finding-risk-badge-col">
                        <span class="badge badge-risk-${f.risk_level}">${riskLabel}</span>
                        <span class="confidence-pill" title="Indicateur d'exhaustivité des données d'exploitation">
                            Complétude : <strong>${(f.confidence || 'Moyenne').toUpperCase()}</strong>
                        </span>
                    </div>
                </div>

                <div class="finding-card-body">
                    <h4 class="finding-problem-title">${f.title || 'Anomalie détectée'}</h4>
                    <p class="finding-summary-text">${f.summary || ''}</p>

                    ${f.evidence && f.evidence.length > 0 ? `
                        <div class="finding-evidence-strip">
                            <span class="evidence-icon">📌</span>
                            <span class="evidence-snippet">
                                ${Array.isArray(f.evidence) ? f.evidence[0] : f.evidence}
                                ${f.evidence.length > 1 ? `<em>(+${f.evidence.length - 1} fait(s) vérifié(s))</em>` : ''}
                            </span>
                        </div>
                    ` : ''}

                    <div class="finding-timing-row">
                        <div class="timing-col">
                            <small>Échéance recommandée :</small>
                            <strong>${f.recommended_timeframe || 'À planifier'}</strong>
                        </div>
                        <div class="timing-col">
                            <small>Type suggéré :</small>
                            <span class="badge badge-neutral">${f.recommended_maintenance_type || 'Préventive'}</span>
                        </div>
                    </div>
                </div>

                <div class="finding-card-footer">
                    <button class="button button-small button-outline btn-view-evidence"
                            type="button"
                            onclick='openEvidenceModal(${findingJson})'>
                        🔍 Consulter l'analyse
                    </button>

                    <button class="button button-small button-ai-plan"
                            type="button"
                            onclick='openPlannerForFinding(${findingJson})'>
                        ✨ Générer un plan de maintenance
                    </button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    applyFilters();
}

// Filters & Sorting logic
function applyFilters() {
    const search = (document.getElementById('finding-search-input').value || '').toLowerCase().trim();
    const risk = document.getElementById('filter-risk-level').value;
    const zone = document.getElementById('filter-zone').value;
    const type = document.getElementById('filter-type').value;
    const sort = document.getElementById('sort-findings').value;

    const cards = Array.from(document.querySelectorAll('.finding-card'));
    let visibleCount = 0;

    cards.forEach(card => {
        const cardRisk = card.dataset.risk;
        const cardZone = card.dataset.zone;
        const cardType = card.dataset.type;
        const cardName = card.dataset.name;

        let matches = true;
        if (search && !cardName.includes(search)) matches = false;
        if (risk && cardRisk !== risk) matches = false;
        if (zone && cardZone !== zone) matches = false;
        if (type && cardType !== type) matches = false;

        card.style.display = matches ? 'block' : 'none';
        if (matches) visibleCount++;
    });

    document.getElementById('findings-total-badge').textContent = visibleCount;
}

function resetFilters() {
    document.getElementById('finding-search-input').value = '';
    document.getElementById('filter-risk-level').value = '';
    document.getElementById('filter-zone').value = '';
    document.getElementById('filter-type').value = '';
    document.getElementById('sort-findings').value = 'urgency';
    applyFilters();
}

// =========================================================================
// Evidence Modal Handling
// =========================================================================
function openEvidenceModal(finding) {
    document.getElementById('modal-evidence-infra-name').textContent = finding.infrastructure_name || 'Ouvrage';
    document.getElementById('modal-evidence-ref').textContent = `Réf: ${finding.reference_code || ('INF-' + finding.infrastructure_id)} · ${finding.zone_name || ''}`;
    document.getElementById('modal-evidence-title').textContent = finding.title || '';
    document.getElementById('modal-evidence-summary').textContent = finding.summary || '';

    // Risk badge
    const badge = document.getElementById('modal-evidence-risk-badge');
    badge.className = `badge badge-risk-${finding.risk_level || 'medium'}`;
    badge.textContent = `Niveau : ${(finding.risk_level || 'Moyen').toUpperCase()}`;

    // Facts list
    const factsList = document.getElementById('modal-evidence-facts-list');
    factsList.innerHTML = '';
    const facts = finding.evidence || finding.verified_facts || [];
    facts.forEach(fact => {
        const li = document.createElement('li');
        li.textContent = fact;
        factsList.appendChild(li);
    });

    // Risk factors
    const factorsList = document.getElementById('modal-evidence-factors-list');
    factorsList.innerHTML = '';
    const factors = finding.risk_factors || [];
    factors.forEach(fac => {
        const li = document.createElement('li');
        li.textContent = fac;
        factorsList.appendChild(li);
    });

    // Consequences
    const conseqList = document.getElementById('modal-evidence-consequences-list');
    conseqList.innerHTML = '';
    const conseq = finding.consequences || [];
    conseq.forEach(c => {
        const li = document.createElement('li');
        li.textContent = c;
        conseqList.appendChild(li);
    });

    // Actions
    const actionsList = document.getElementById('modal-evidence-actions-list');
    actionsList.innerHTML = '';
    const actions = finding.recommended_actions || [];
    actions.forEach(a => {
        const li = document.createElement('li');
        li.textContent = a;
        actionsList.appendChild(li);
    });

    // Missing info
    const missingBox = document.getElementById('modal-evidence-missing-box');
    const missingList = document.getElementById('modal-evidence-missing-list');
    missingList.innerHTML = '';
    if (finding.missing_information && finding.missing_information.length > 0) {
        missingBox.style.display = 'block';
        finding.missing_information.forEach(m => {
            const li = document.createElement('li');
            li.textContent = m;
            missingList.appendChild(li);
        });
    } else {
        missingBox.style.display = 'none';
    }

    // Direct plan generation button
    const btnPlan = document.getElementById('modal-evidence-btn-plan');
    btnPlan.onclick = function() {
        closeEvidenceModal();
        openPlannerForFinding(finding);
    };

    document.getElementById('modal-evidence-detail').style.display = 'flex';
}

function closeEvidenceModal() {
    const modal = document.getElementById('modal-evidence-detail');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

// =========================================================================
// STAGE 2: AI Maintenance Planner
// =========================================================================
function openPlannerForFinding(finding) {
    activeFindingForPlan = finding;
    const modal = document.getElementById('modal-planner');
    const loadingView = document.getElementById('planner-loading-view');
    const form = document.getElementById('form-create-maintenance');
    const formView = document.getElementById('planner-form-view');
    const btnSubmit = document.getElementById('btn-submit-maintenance');

    modal.style.display = 'flex';
    loadingView.style.display = 'block';
    if (form) form.style.display = 'none';
    if (formView) formView.style.display = 'none';
    btnSubmit.disabled = true;

    document.getElementById('planner-subheading').textContent =
        `Ouvrage : ${finding.infrastructure_name} (${finding.reference_code}) · Zone : ${finding.zone_name}`;

    // Request AI plan from backend
    fetch('{{ route('maintenance-intelligence.plan') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            infrastructure_id: finding.infrastructure_id,
            finding: finding
        })
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Impossible de générer le plan');
        }
        return data;
    })
    .then(data => {
        currentGeneratedPlan = data.plan;
        populatePlannerForm(data.plan);
        loadingView.style.display = 'none';
        if (form) form.style.display = 'flex';
        if (formView) {
            formView.style.display = 'block';
            formView.scrollTop = 0;
        }
        btnSubmit.disabled = false;
    })
    .catch(error => {
        showToast(error.message || 'Erreur lors de la formulation du plan IA.', false);
        closePlannerModal();
    });
}

function populatePlannerForm(plan) {
    document.getElementById('plan-input-infra-id').value = plan.infrastructure_id;
    document.getElementById('plan-display-title').textContent = plan.plan_title || 'Plan de maintenance';
    document.getElementById('plan-display-justification').textContent = plan.justification || '';

    // Form fields
    document.getElementById('plan-field-type').value = plan.maintenance_type || 'Préventive';
    document.getElementById('plan-field-priority').value = plan.priority || 'medium';
    document.getElementById('plan-field-scheduled-at').value = plan.proposed_start_date || '';
    document.getElementById('plan-field-duration').value = plan.estimated_duration_hours || 3;
    document.getElementById('plan-field-cost').value = plan.estimated_cost || '';
    document.getElementById('plan-field-description').value = plan.work_order_description || '';
    document.getElementById('plan-field-next-date').value = plan.suggested_next_maintenance_date || '';
    document.getElementById('plan-display-expected-result').textContent = plan.expected_result || 'Remise en service opérationnel nominale.';

    // Suggest matching technician if specialty matches
    const techSelect = document.getElementById('plan-field-technician');
    if (plan.recommended_technician_speciality && techSelect) {
        const spec = plan.recommended_technician_speciality.toLowerCase();
        for (let opt of techSelect.options) {
            if (opt.text.toLowerCase().includes(spec)) {
                opt.selected = true;
                break;
            }
        }
    }

    // Interactive checklist
    renderChecklist(plan.checklist || []);

    // Safety and Tools lists
    const safetyList = document.getElementById('plan-safety-list');
    safetyList.innerHTML = '';
    (plan.safety_considerations || []).forEach(s => {
        const li = document.createElement('li');
        li.textContent = s;
        safetyList.appendChild(li);
    });

    const toolsList = document.getElementById('plan-tools-list');
    toolsList.innerHTML = '';
    (plan.required_tools_or_materials || []).forEach(t => {
        const li = document.createElement('li');
        li.textContent = t;
        toolsList.appendChild(li);
    });
}

function renderChecklist(items) {
    const container = document.getElementById('plan-checklist-container');
    container.innerHTML = '';

    items.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'checklist-item-row';
        row.innerHTML = `
            <input type="checkbox" checked title="Valider cette étape">
            <input type="text" name="checklist[]" value="${item.replace(/"/g, '&quot;')}" placeholder="Description de l'étape technique...">
            <button type="button" class="btn-remove-step" onclick="this.closest('.checklist-item-row').remove()" title="Supprimer cette étape">✕</button>
        `;
        container.appendChild(row);
    });
}

function addChecklistItem() {
    const container = document.getElementById('plan-checklist-container');
    const row = document.createElement('div');
    row.className = 'checklist-item-row';
    row.innerHTML = `
        <input type="checkbox" checked title="Valider cette étape">
        <input type="text" name="checklist[]" value="" placeholder="Nouvelle étape technique à consigner...">
        <button type="button" class="btn-remove-step" onclick="this.closest('.checklist-item-row').remove()" title="Supprimer cette étape">✕</button>
    `;
    container.appendChild(row);
    row.querySelector('input[type="text"]').focus();
}

function resetToAiSuggestions() {
    if (currentGeneratedPlan) {
        populatePlannerForm(currentGeneratedPlan);
        showToast('Suggestions IA rétablies dans le formulaire.', true);
    }
}

function regeneratePlanWithPrompt() {
    if (!confirm('Voulez-vous régénérer un nouveau plan IA pour cet ouvrage ? Les modifications saisies seront réinitialisées.')) {
        return;
    }
    if (activeFindingForPlan) {
        openPlannerForFinding(activeFindingForPlan);
    }
}

function closePlannerModal() {
    const modal = document.getElementById('modal-planner');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEvidenceModal();
        closePlannerModal();
    }
});

// =========================================================================
// STAGE 2 CONFIRMATION: Persist Maintenance to Laravel
// =========================================================================
function submitCreatedMaintenance(event) {
    event.preventDefault();
    const form = event.target;
    const btnSubmit = document.getElementById('btn-submit-maintenance');

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Enregistrement...';

    // Collect checklist values
    const checklistInputs = form.querySelectorAll('input[name="checklist[]"]');
    const checklist = Array.from(checklistInputs).map(inp => inp.value.trim()).filter(v => v.length > 0);

    const payload = {
        infrastructure_id: form.querySelector('#plan-input-infra-id').value,
        type: form.querySelector('#plan-field-type').value,
        priority: form.querySelector('#plan-field-priority').value,
        status: 'planned',
        scheduled_at: form.querySelector('#plan-field-scheduled-at').value,
        duration_hours: form.querySelector('#plan-field-duration').value || null,
        technician_id: form.querySelector('#plan-field-technician').value || null,
        team: form.querySelector('#plan-field-team').value || null,
        cost: form.querySelector('#plan-field-cost').value || null,
        description: form.querySelector('#plan-field-description').value,
        next_maintenance_date: form.querySelector('#plan-field-next-date').value || null,
        checklist: checklist
    };

    fetch('{{ route('maintenance-intelligence.store-maintenance') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Validation échouée lors de la création de la maintenance');
        }
        return data;
    })
    .then(data => {
        showToast(data.message || 'Opération de maintenance planifiée avec succès !', true);
        closePlannerModal();

        // Redirect to detail page after a short confirmation pause
        setTimeout(() => {
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            }
        }, 1200);
    })
    .catch(error => {
        showToast(error.message || 'Erreur de validation lors de l\'enregistrement.', false);
        btnSubmit.disabled = false;
        btnSubmit.textContent = '✓ Créer la maintenance';
    });
}
</script>
@endpush
