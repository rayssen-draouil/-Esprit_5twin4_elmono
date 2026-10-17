@extends('layouts.back')

@section('content')
{{-- 1. Header with Metadata & Actions --}}
<div class="content-heading">
    <div>
        <span class="eyebrow">{{ $infrastructure->reference_code ?? ('INF-' . $infrastructure->id) }} · {{ $infrastructure->type }}</span>
        <h1>{{ $infrastructure->name }}</h1>
        <p>
            {{ $infrastructure->zone?->name ?? 'Zone non définie' }}
            @if($infrastructure->location) · {{ $infrastructure->location }} @endif
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a class="button button-outline" href="{{ route('infrastructures.index') }}">
            ← Liste
        </a>
        <a class="button button-outline" href="{{ route('infrastructures.edit', $infrastructure) }}">
            ✎ Modifier
        </a>
        <button id="btn-analyze-infra" class="button button-ai-scan" type="button" onclick="analyzeInfrastructureHealth()">
            <span class="ai-spin-icon">⚡</span>
            <span id="btn-analyze-text">Analyser avec l'IA</span>
        </button>
        <a class="button" href="{{ route('maintenances.create', ['infrastructure_id' => $infrastructure->id]) }}">
            + Planifier une maintenance
        </a>
    </div>
</div>

{{-- 2. Summary KPI Cards --}}
<div class="kpi-grid project-kpis" style="grid-template-columns: repeat(5, 1fr);">
    <div class="kpi">
        <span>Santé & État</span>
        <strong style="color: {{ $infrastructure->health_score >= 70 ? 'var(--aqua)' : ($infrastructure->health_score >= 50 ? '#aa721c' : 'var(--coral)') }};">
            {{ $infrastructure->health_score }}%
        </strong>
        <small>{{ $infrastructure->health_label }} · {{ $infrastructure->condition_label }}</small>
    </div>

    <div class="kpi">
        <span>Statut actuel</span>
        <strong>
            <span class="badge {{ $infrastructure->status_badge_class }}" style="font-size: 13px; padding: 4px 10px;">
                {{ $infrastructure->status_label }}
            </span>
        </strong>
        <small>Criticité : {{ $infrastructure->criticality_label }}</small>
    </div>

    <div class="kpi">
        <span>Maintenances</span>
        <strong>{{ $metrics['total_maintenances'] }}</strong>
        <small>{{ $metrics['completed_maintenances'] }} terminées · {{ $metrics['upcoming_maintenances'] }} en attente</small>
    </div>

    <div class="kpi">
        <span>Dernière maintenance</span>
        <strong>
            {{ $infrastructure->last_maintenance_date ? $infrastructure->last_maintenance_date->format('d/m/Y') : 'Aucune' }}
        </strong>
        <small>Historique validé</small>
    </div>

    <div class="kpi">
        <span>Budget maintenance</span>
        <strong>{{ number_format($metrics['total_cost'], 2, ',', ' ') }} €</strong>
        <small>Coût total cumulé</small>
    </div>
</div>

{{-- Alerts if overdue or due soon --}}
@if($infrastructure->is_maintenance_overdue)
    <div class="flash-message flash-error" style="margin-bottom: 22px;">
        <strong>⚠ Maintenance en retard :</strong> Cette infrastructure avait une échéance planifiée au {{ $infrastructure->next_maintenance_date?->format('d/m/Y') }}. Une intervention technique doit être planifiée en priorité.
    </div>
@elseif($infrastructure->is_maintenance_due_soon)
    <div class="flash-message flash-warning" style="margin-bottom: 22px;">
        <strong>⏱ Échéance proche :</strong> La prochaine révision réglementaire est prévue le {{ $infrastructure->next_maintenance_date?->format('d/m/Y') }}.
    </div>
@endif

{{-- AI Health Analyst Section --}}
<div id="ai-health-analyst-wrapper" style="margin-bottom: 25px;">
    {{-- Loading state --}}
    <div id="ai-health-loading" class="panel" style="display: none; background: linear-gradient(135deg, #073f4a 0%, #0d5f6e 100%); color: #fff; padding: 25px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="ai-radar-spinner">⚡</div>
            <div>
                <h3 style="margin: 0 0 4px; color: #fff; font-size: 18px;">Analyse de santé technique en cours (openai/gpt-oss-120b)...</h3>
                <p style="margin: 0; color: #bad1d0; font-size: 13px;">Évaluation de la condition matérielle, recoupement des antécédents de maintenance et formulation des préconisations.</p>
            </div>
        </div>
    </div>

    {{-- Analysis Results Panel --}}
    @php
        $analysis = $infrastructure->ai_health_analysis;
    @endphp
    <div id="ai-health-panel" class="panel" style="{{ $analysis ? '' : 'display: none;' }} border-left: 5px solid var(--aqua); background: #ffffff;">
        <div class="panel-heading" style="border-bottom: 1px solid var(--line); padding-bottom: 14px; margin-bottom: 18px;">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 800; color: var(--aqua); letter-spacing: 0.1em;">
                    <span style="display: inline-block; width: 8px; height: 8px; background: var(--aqua); border-radius: 50%;"></span>
                    DIAGNOSTIC DE SANTÉ IA · AQUASECURE
                </div>
                <h2 id="ai-analysis-title" style="margin: 4px 0 0; font-size: 20px;">
                    {{ $analysis['assessment_title'] ?? 'Diagnostic de santé de l’ouvrage' }}
                </h2>
                <small style="color: var(--muted);" id="ai-analyzed-timestamp">
                    Dernière analyse le {{ $infrastructure->ai_health_analyzed_at?->format('d/m/Y à H:i') ?? 'N/A' }}
                </small>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="ai-risk-level-badge" class="badge" style="font-size: 12px; padding: 6px 12px; text-transform: uppercase;">
                    @php
                        $lvl = $analysis['overall_risk_level'] ?? 'medium';
                    @endphp
                    Niveau de risque : {{ ucfirst($lvl) }}
                </span>
                <button type="button" class="button button-small button-outline" onclick="analyzeInfrastructureHealth()">
                    🔄 Réanalyser
                </button>
            </div>
        </div>

        {{-- Section A: Condition overview --}}
        <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 6px; padding: 14px 18px; margin-bottom: 20px;">
            <strong style="display: block; font-size: 12px; color: var(--deep); text-transform: uppercase; margin-bottom: 4px;">A. Synthèse de l'état fonctionnel</strong>
            <p id="ai-condition-summary" style="margin: 0; font-size: 14px; line-height: 1.5; color: var(--ink);">
                {{ $analysis['condition_summary'] ?? '' }}
            </p>
        </div>

        {{-- Section B: Top 3 Risk Factors --}}
        <div style="margin-bottom: 20px;">
            <strong style="display: block; font-size: 13px; color: var(--deep); text-transform: uppercase; margin-bottom: 12px;">B. Principaux facteurs de risque technique (Max 3)</strong>
            <div id="ai-risk-factors-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
                @if(!empty($analysis['top_risk_factors']))
                    @foreach($analysis['top_risk_factors'] as $factor)
                        <div style="background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                    <h4 style="margin: 0; font-size: 13px; font-weight: 700; color: var(--deep);">{{ $factor['title'] }}</h4>
                                    <span class="badge" style="font-size: 10px;">{{ ucfirst($factor['urgency'] ?? 'medium') }}</span>
                                </div>
                                <p style="margin: 0 0 8px; font-size: 12px; color: var(--muted); line-height: 1.4;">{{ $factor['explanation'] }}</p>
                            </div>
                            <div style="background: #fbfdfc; border-top: 1px dashed var(--line); padding-top: 8px; font-size: 11px; color: var(--ink);">
                                <strong>Preuve :</strong> {{ $factor['evidence'] }}
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- Section C & D: Recommended Actions & Checklist --}}
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div style="border: 1px solid var(--line); border-radius: 6px; padding: 16px; background: #fff;">
                <strong style="display: block; font-size: 12px; color: var(--deep); text-transform: uppercase; margin-bottom: 10px;">C. Actions préconisées par ordre d'urgence</strong>
                <ol id="ai-actions-list" style="margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.6; color: var(--ink);">
                    @if(!empty($analysis['recommended_actions']))
                        @foreach($analysis['recommended_actions'] as $act)
                            <li style="margin-bottom: 8px;">
                                <strong>{{ is_array($act) ? $act['action'] : $act }}</strong>
                                @if(is_array($act) && !empty($act['reason']))
                                    <div style="font-size: 11px; color: var(--muted);">{{ $act['reason'] }}</div>
                                @endif
                            </li>
                        @endforeach
                    @endif
                </ol>
            </div>

            <div style="border: 1px solid var(--line); border-radius: 6px; padding: 16px; background: #fff;">
                <strong style="display: block; font-size: 12px; color: var(--deep); text-transform: uppercase; margin-bottom: 10px;">D. Points de contrôle recommandés</strong>
                <ul id="ai-checklist-list" style="margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.6; color: var(--ink);">
                    @if(!empty($analysis['maintenance_checklist']))
                        @foreach($analysis['maintenance_checklist'] as $item)
                            <li>[ ] {{ $item }}</li>
                        @endforeach
                    @endif
                </ul>
            </div>
        </div>

        {{-- Section E: Missing Information or Data Gaps --}}
        <div id="ai-missing-box" style="background: #fff8eb; border: 1px solid #fde68a; border-radius: 6px; padding: 12px 16px; font-size: 12px; color: #92400e; {{ !empty($analysis['missing_information']) ? '' : 'display: none;' }}">
            <strong>⚠️ Limites du dossier et données manquantes :</strong>
            <ul id="ai-missing-list" style="margin: 4px 0 0 18px;">
                @if(!empty($analysis['missing_information']))
                    @foreach($analysis['missing_information'] as $m)
                        <li>{{ $m }}</li>
                    @endforeach
                @endif
            </ul>
        </div>
    </div>
</div>

{{-- 3. Detail Grid --}}
<div class="detail-grid">
    {{-- Left Column: Overview + Lifecycle Timeline --}}
    <div>
        {{-- Section: Spécifications & Description --}}
        <div class="panel" style="margin-bottom: 20px;">
            <div class="panel-heading" style="margin-bottom: 15px;">
                <div>
                    <h2>Description & Caractéristiques</h2>
                    <p>Données d'exploitation et dimensionnement de l'infrastructure</p>
                </div>
            </div>

            <p style="color: var(--ink); line-height: 1.6; margin-bottom: 20px;">
                {{ $infrastructure->description ?: 'Aucune description détaillée n’a été enregistrée pour cet ouvrage.' }}
            </p>

            <div class="detail-meta" style="grid-template-columns: repeat(3, 1fr); gap: 16px; background: #fbfdfc; padding: 16px; border-radius: 6px; border: 1px solid var(--line);">
                <div>
                    <span>Capacité nominale</span>
                    <strong>{{ $infrastructure->capacity ?: 'Non renseignée' }}</strong>
                </div>
                <div>
                    <span>Date d'installation</span>
                    <strong>{{ $infrastructure->installation_date ? $infrastructure->installation_date->format('d/m/Y') : '—' }}</strong>
                </div>
                <div>
                    <span>Mise en service</span>
                    <strong>{{ $infrastructure->commissioning_date ? $infrastructure->commissioning_date->format('d/m/Y') : '—' }}</strong>
                </div>
                <div>
                    <span>Coordonnées GPS</span>
                    <strong>
                        @if($infrastructure->latitude && $infrastructure->longitude)
                            {{ number_format($infrastructure->latitude, 4) }}, {{ number_format($infrastructure->longitude, 4) }}
                        @else
                            Non géolocalisé
                        @endif
                    </strong>
                </div>
                <div>
                    <span>Bassin / Zone</span>
                    <strong>{{ $infrastructure->zone?->name ?? '—' }}</strong>
                </div>
                <div>
                    <span>Niveau de criticité</span>
                    <strong>{{ $infrastructure->criticality_label }}</strong>
                </div>
            </div>
        </div>

        {{-- Section: Lifecycle Timeline --}}
        <div class="panel">
            <div class="panel-heading" style="margin-bottom: 10px;">
                <div>
                    <h2>Cycle de vie & Historique des opérations</h2>
                    <p>Traçabilité chronologique des maintenances et contrôles réalisés</p>
                </div>
                <a class="button button-small" href="{{ route('maintenances.create', ['infrastructure_id' => $infrastructure->id]) }}">
                    + Ajouter une opération
                </a>
            </div>

            <div class="lifecycle-timeline">
                {{-- Milestone: Installation --}}
                @if($infrastructure->installation_date)
                    <div class="timeline-item">
                        <div class="timeline-marker done">✓</div>
                        <div class="timeline-content">
                            <div class="timeline-content-head">
                                <h4>Installation & Déploiement initial de l'ouvrage</h4>
                                <small style="color: var(--muted);">{{ $infrastructure->installation_date->format('d/m/Y') }}</small>
                            </div>
                            <p>Mise en place des équipements sur site et raccordement au réseau de surveillance AquaSecure.</p>
                        </div>
                    </div>
                @endif

                {{-- Chronological Maintenances --}}
                @forelse($infrastructure->maintenances as $maintenance)
                    <div class="timeline-item">
                        <div class="timeline-marker {{ $maintenance->status === 'completed' ? 'done' : ($maintenance->is_overdue ? 'overdue' : '') }}">
                            {{ $maintenance->status === 'completed' ? '✓' : ($maintenance->is_overdue ? '!' : '⛯') }}
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-content-head">
                                <div>
                                    <h4 style="display: inline-flex; align-items: center; gap: 8px;">
                                        <a href="{{ route('maintenances.show', $maintenance) }}" style="color: var(--ink);">
                                            {{ $maintenance->type }} · {{ $maintenance->reference_code ?? ('#' . $maintenance->id) }}
                                        </a>
                                        <span class="badge {{ $maintenance->status_badge_class }}">
                                            {{ $maintenance->display_status_label }}
                                        </span>
                                    </h4>
                                    @if($maintenance->technician)
                                        <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                            Technicien : <strong>{{ $maintenance->technician->name }}</strong>
                                            @if($maintenance->team) ({{ $maintenance->team }}) @endif
                                        </div>
                                    @endif
                                </div>
                                <small style="color: var(--muted);">
                                    {{ $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y à H:i') : 'Date non définie' }}
                                </small>
                            </div>

                            <p>{{ $maintenance->description }}</p>

                            @if($maintenance->result)
                                <div style="margin-top: 8px; padding: 8px 12px; background: #fff; border-left: 3px solid var(--aqua); border-radius: 4px; font-size: 11px;">
                                    <strong>Compte-rendu :</strong> {{ $maintenance->result }}
                                </div>
                            @endif

                            <div style="display: flex; gap: 15px; margin-top: 10px; font-size: 11px; color: var(--muted);">
                                @if($maintenance->cost)
                                    <span>Coût : <strong>{{ number_format($maintenance->cost, 2, ',', ' ') }} €</strong></span>
                                @endif
                                @if($maintenance->duration_hours)
                                    <span>Durée : <strong>{{ $maintenance->duration_hours }} h</strong></span>
                                @endif
                                <a href="{{ route('maintenances.show', $maintenance) }}" style="color: var(--aqua); font-weight: 700; margin-left: auto;">
                                    Consulter l'opération →
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="timeline-item">
                        <div class="timeline-marker">○</div>
                        <div class="timeline-content">
                            <div class="timeline-content-head">
                                <h4>Aucune opération de maintenance enregistrée</h4>
                            </div>
                            <p>Cet équipement ne dispose pour le moment d'aucun historique d'intervention.</p>
                            <a class="button button-small" href="{{ route('maintenances.create', ['infrastructure_id' => $infrastructure->id]) }}" style="margin-top: 8px;">
                                Planifier la première maintenance
                            </a>
                        </div>
                    </div>
                @endforelse

                {{-- Milestone: Next Scheduled Maintenance --}}
                @if($infrastructure->next_maintenance_date)
                    <div class="timeline-item">
                        <div class="timeline-marker {{ $infrastructure->is_maintenance_overdue ? 'overdue' : '' }}">⏱</div>
                        <div class="timeline-content" style="border-style: dashed;">
                            <div class="timeline-content-head">
                                <h4>Prochaine maintenance recommandée</h4>
                                <small style="color: {{ $infrastructure->is_maintenance_overdue ? 'var(--coral)' : 'var(--deep)' }}; font-weight: 700;">
                                    {{ $infrastructure->next_maintenance_date->format('d/m/Y') }}
                                </small>
                            </div>
                            <p>Échéance préventive calculée pour assurer la longévité et le maintien des normes sanitaires de l'ouvrage.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right Column: Media, Health Card & Quick Actions --}}
    <div>
        {{-- Health Score Card --}}
        <div class="panel" style="margin-bottom: 20px;">
            <div class="panel-heading" style="margin-bottom: 10px;">
                <div>
                    <h2>Indicateur de Santé</h2>
                    <p>Calcul déterministe basé sur l'état et l'historique</p>
                </div>
            </div>

            <div style="text-align: center; padding: 15px 0;">
                <div style="font-size: 42px; font-weight: 800; letter-spacing: -0.05em; color: {{ $infrastructure->health_score >= 70 ? 'var(--aqua)' : ($infrastructure->health_score >= 50 ? '#aa721c' : 'var(--coral)') }};">
                    {{ $infrastructure->health_score }}%
                </div>
                <strong style="font-size: 14px; color: var(--ink);">{{ $infrastructure->health_label }}</strong>
                <p style="font-size: 11px; color: var(--muted); margin: 4px 0 16px;">
                    État matériel : <strong>{{ $infrastructure->condition_label }}</strong>
                </p>

                <div class="infra-card-health-bar" style="height: 8px; margin: 0 10px;">
                    <i style="width: {{ $infrastructure->health_score }}%; background: {{ $infrastructure->health_score >= 70 ? 'var(--aqua)' : ($infrastructure->health_score >= 50 ? '#aa721c' : 'var(--coral)') }};"></i>
                </div>
            </div>

            <ul style="list-style: none; padding: 0; margin: 15px 0 0; font-size: 11px; color: var(--muted); border-top: 1px solid var(--line); padding-top: 12px;">
                <li style="display: flex; justify-content: space-between; padding: 4px 0;">
                    <span>Statut :</span>
                    <strong style="color: var(--ink);">{{ $infrastructure->status_label }}</strong>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 4px 0;">
                    <span>Condition physique :</span>
                    <strong style="color: var(--ink);">{{ $infrastructure->condition_label }}</strong>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 4px 0;">
                    <span>Dernier contrôle :</span>
                    <strong style="color: var(--ink);">{{ $infrastructure->last_maintenance_date ? $infrastructure->last_maintenance_date->format('d/m/Y') : 'Non renseigné' }}</strong>
                </li>
            </ul>
        </div>

        {{-- Infrastructure Photo Card --}}
        @if($infrastructure->image_path)
            <div class="panel" style="margin-bottom: 20px; padding: 0; overflow: hidden;">
                <img src="{{ asset('storage/' . $infrastructure->image_path) }}"
                     alt="{{ $infrastructure->name }}"
                     style="width: 100%; height: 210px; object-fit: cover; display: block;">
                <div style="padding: 12px 16px; font-size: 11px; color: var(--muted);">
                    Vue photographique de l'ouvrage sur site
                </div>
            </div>
        @endif

        {{-- Maintenance Actions Panel --}}
        <div class="panel">
            <div class="panel-heading" style="margin-bottom: 12px;">
                <div>
                    <h2>Actions Techniques</h2>
                    <p>Gestion opérationnelle de l'ouvrage</p>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a class="button button-small" href="{{ route('maintenances.create', ['infrastructure_id' => $infrastructure->id]) }}" style="justify-content: center;">
                    + Planifier une maintenance sur ce site
                </a>
                <a class="button button-small button-outline" href="{{ route('maintenances.index', ['infrastructure_id' => $infrastructure->id]) }}" style="justify-content: center;">
                    Consulter toutes ses maintenances ({{ $infrastructure->maintenances->count() }})
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Toast Notification --}}
<div id="ai-infra-toast" class="intelligence-toast" style="display: none;">
    <span id="ai-toast-icon">✓</span>
    <span id="ai-toast-message">Notification</span>
</div>
@endsection

@push('styles')
<style>
.button-ai-scan {
    background: linear-gradient(135deg, var(--deep) 0%, #0d5f6e 100%);
    color: #fff;
    box-shadow: 0 4px 14px rgba(7, 63, 74, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.2s ease;
}
.button-ai-scan:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(7, 63, 74, 0.35);
}
.ai-radar-spinner {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(22, 183, 163, 0.2);
    border: 2px solid var(--aqua);
    color: var(--aqua);
    display: grid;
    place-items: center;
    font-size: 20px;
    animation: pulseRadar 1.5s infinite;
}
@keyframes pulseRadar {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 183, 163, 0.4); }
    50% { transform: scale(1.1); box-shadow: 0 0 15px 4px rgba(22, 183, 163, 0.6); }
}
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
</style>
@endpush

@push('scripts')
<script>
function showInfraToast(message, isSuccess = true) {
    const toast = document.getElementById('ai-infra-toast');
    const toastIcon = document.getElementById('ai-toast-icon');
    const toastMsg = document.getElementById('ai-toast-message');

    toastIcon.textContent = isSuccess ? '✓' : '⚠️';
    toastMsg.textContent = message;
    toast.style.background = isSuccess ? 'var(--deep)' : '#a8473d';
    toast.style.display = 'flex';

    setTimeout(() => {
        toast.style.display = 'none';
    }, 4500);
}

function analyzeInfrastructureHealth() {
    const btn = document.getElementById('btn-analyze-infra');
    const btnText = document.getElementById('btn-analyze-text');
    const loadingPanel = document.getElementById('ai-health-loading');
    const resultsPanel = document.getElementById('ai-health-panel');

    btn.disabled = true;
    btnText.textContent = 'Analyse en cours...';
    loadingPanel.style.display = 'block';

    fetch('{{ route('infrastructures.ai-health', $infrastructure) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Erreur lors de l\'analyse');
        }
        return data;
    })
    .then(data => {
        const a = data.analysis;
        if (!a) return;

        document.getElementById('ai-analysis-title').textContent = a.assessment_title || 'Diagnostic de santé de l’ouvrage';
        document.getElementById('ai-analyzed-timestamp').textContent = 'Dernière analyse le ' + (a.analyzed_at_display || 'À l’instant');
        document.getElementById('ai-condition-summary').textContent = a.condition_summary || '';

        // Risk badge
        const badge = document.getElementById('ai-risk-level-badge');
        const lvl = (a.overall_risk_level || 'medium').toLowerCase();
        badge.className = `badge badge-risk-${lvl}`;
        badge.textContent = `Niveau de risque : ${lvl.toUpperCase()}`;

        // Top factors
        const factorsGrid = document.getElementById('ai-risk-factors-grid');
        factorsGrid.innerHTML = '';
        (a.top_risk_factors || []).forEach(f => {
            const card = document.createElement('div');
            card.style.cssText = 'background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between;';
            card.innerHTML = `
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                        <h4 style="margin: 0; font-size: 13px; font-weight: 700; color: var(--deep);">${f.title || 'Facteur'}</h4>
                        <span class="badge" style="font-size: 10px;">${(f.urgency || 'medium').toUpperCase()}</span>
                    </div>
                    <p style="margin: 0 0 8px; font-size: 12px; color: var(--muted); line-height: 1.4;">${f.explanation || ''}</p>
                </div>
                <div style="background: #fbfdfc; border-top: 1px dashed var(--line); padding-top: 8px; font-size: 11px; color: var(--ink);">
                    <strong>Preuve :</strong> ${f.evidence || ''}
                </div>
            `;
            factorsGrid.appendChild(card);
        });

        // Actions
        const actionsList = document.getElementById('ai-actions-list');
        actionsList.innerHTML = '';
        (a.recommended_actions || []).forEach(act => {
            const li = document.createElement('li');
            li.style.marginBottom = '8px';
            li.innerHTML = `<strong>${act.action || act}</strong>` + (act.reason ? `<div style="font-size: 11px; color: var(--muted);">${act.reason}</div>` : '');
            actionsList.appendChild(li);
        });

        // Checklist
        const checklistList = document.getElementById('ai-checklist-list');
        checklistList.innerHTML = '';
        (a.maintenance_checklist || []).forEach(item => {
            const li = document.createElement('li');
            li.textContent = `[ ] ${item}`;
            checklistList.appendChild(li);
        });

        // Missing info
        const missingBox = document.getElementById('ai-missing-box');
        const missingList = document.getElementById('ai-missing-list');
        missingList.innerHTML = '';
        if (a.missing_information && a.missing_information.length > 0) {
            missingBox.style.display = 'block';
            a.missing_information.forEach(m => {
                const li = document.createElement('li');
                li.textContent = m;
                missingList.appendChild(li);
            });
        } else {
            missingBox.style.display = 'none';
        }

        resultsPanel.style.display = 'block';
        showInfraToast(data.message || 'Diagnostic IA généré avec succès !', true);
    })
    .catch(error => {
        showInfraToast(error.message || 'Erreur technique lors du diagnostic.', false);
    })
    .finally(() => {
        loadingPanel.style.display = 'none';
        btn.disabled = false;
        btnText.textContent = 'Analyser avec l\'IA';
    });
}
</script>
@endpush

