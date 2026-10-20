@extends('layouts.back')

@section('content')
<style>
.detail-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
    align-items: start;
}
@media (max-width: 960px) {
    .detail-grid {
        grid-template-columns: 1fr !important;
    }
}
.stepper-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fbfdfc;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 24px;
    overflow-x: auto;
    max-width: 100%;
    -webkit-overflow-scrolling: touch;
}
.detail-meta-responsive {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) !important;
    gap: 14px;
    background: #fbfdfc;
    padding: 16px;
    border-radius: 6px;
    border: 1px solid var(--line);
}
.content-heading-wrap {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}
.content-heading-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
@media (max-width: 640px) {
    .content-heading-actions {
        width: 100%;
        margin-top: 10px;
    }
    .content-heading-actions .button,
    .content-heading-actions a {
        flex: 1 1 auto;
        justify-content: center;
        text-align: center;
    }
}
/* Fixed Modal Backdrop & Dialog */
.intelligence-modal-backdrop {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(7, 63, 74, 0.72) !important;
    backdrop-filter: blur(4px) !important;
    -webkit-backdrop-filter: blur(4px) !important;
    z-index: 99999 !important;
    display: flex;
    justify-content: center !important;
    align-items: center !important;
    padding: 20px !important;
    box-sizing: border-box !important;
}
.intelligence-modal-backdrop[style*="display: none"],
.intelligence-modal-backdrop[style*="display:none"],
.intelligence-modal-backdrop.hidden {
    display: none !important;
}
.intelligence-modal-dialog {
    background: #fff !important;
    border-radius: 8px !important;
    width: 100% !important;
    max-width: 780px !important;
    max-height: 90vh !important;
    display: flex !important;
    flex-direction: column !important;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35) !important;
    overflow: hidden !important;
    margin: auto !important;
    animation: intelligenceModalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
@keyframes intelligenceModalPop {
    from {
        opacity: 0;
        transform: scale(0.96) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>

<div class="content-heading-wrap">
    <div>
        <span class="eyebrow">OPÉRATION TECHNIQUE · {{ $maintenance->type }}</span>
        <h1 style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 4px 0 8px;">
            {{ $maintenance->reference_code ?? ('#' . $maintenance->id) }}
            <span class="badge {{ $maintenance->status_badge_class }}" style="font-size: 13px; padding: 4px 10px;">
                {{ $maintenance->display_status_label }}
            </span>
            <span class="badge {{ $maintenance->priority_badge_class }}" style="font-size: 13px; padding: 4px 10px;">
                Priorité {{ $maintenance->priority_label }}
            </span>
            @if($maintenance->prolonged_at)
                <span class="badge badge-warning" style="font-size: 13px; padding: 4px 10px;">
                    ⏳ Prolongée (+{{ $maintenance->prolongation_days }}j)
                </span>
            @endif
        </h1>
        <p style="color: var(--muted); margin: 0; font-size: 13px;">
            Prévue le {{ $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y à H:i') : 'Date non fixée' }}
            ({{ $maintenance->relative_time_message }})
        </p>
    </div>
    <div class="content-heading-actions">
        <button type="button" class="button button-outline" onclick="openTechRecommendationModalShow()" style="display: inline-flex; align-items: center; gap: 6px; border-color: var(--aqua); color: var(--deep); font-weight: 700; padding: 10px 16px; border-radius: 6px; cursor: pointer; background: #fff;">
            <span style="color: var(--aqua);">✨</span> Recommander technicien IA
        </button>
        <a class="button button-outline" href="{{ route('maintenances.index') }}" style="padding: 10px 16px; border-radius: 6px;">
            ← Liste des maintenances
        </a>
        <a class="button button-outline" href="{{ route('maintenances.edit', $maintenance) }}" style="padding: 10px 16px; border-radius: 6px;">
            ✎ Modifier
        </a>
        @if($maintenance->infrastructure)
            <a class="button" href="{{ route('infrastructures.show', $maintenance->infrastructure) }}" style="padding: 10px 16px; border-radius: 6px; background: var(--deep); color: #fff;">
                ▦ Voir l'infrastructure
            </a>
        @endif
    </div>
</div>

{{-- Citizen Report Approval Banner --}}
@if($maintenance->status === 'reported')
    <div class="panel" style="background: #fff4db; border-color: #f7da92; margin-bottom: 22px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <span class="eyebrow" style="color: #9a6814;">SIGNALEMENT CITOYEN EN ATTENTE D'ACTION</span>
                <h3 style="margin: 4px 0; font-size: 17px; color: var(--ink);">
                    Ce dysfonctionnement a été signalé par un usager et requiert une prise en charge technique.
                </h3>
                <p style="margin: 0; font-size: 13px; color: var(--muted);">
                    Vous pouvez valider l'alerte pour mandater immédiatement une équipe et basculer l'opération en cours d'intervention.
                </p>
            </div>
            <form method="POST" action="{{ route('maintenances.start', $maintenance) }}" style="display: flex; gap: 10px; align-items: center;">
                @csrf
                <button class="button" type="submit" style="background: var(--aqua);">
                    ⚡ Valider & Démarrer la maintenance
                </button>
            </form>
        </div>
    </div>
@endif

{{-- Lifecycle Progress Stepper: Planned -> In Progress -> Completed --}}
<div class="stepper-wrap">
    @if($maintenance->status === 'reported')
        <div class="stepper-step completed">
            <div class="stepper-step-num">!</div>
            <span>Signalement reçu</span>
        </div>
        <div class="stepper-line"></div>
    @endif

    <div class="stepper-step {{ in_array($maintenance->status, ['planned', 'in_progress', 'completed']) ? 'completed' : ($maintenance->status === 'reported' ? '' : 'active') }}">
        <div class="stepper-step-num">1</div>
        <span>Planifiée</span>
    </div>
    <div class="stepper-line {{ in_array($maintenance->status, ['in_progress', 'completed']) ? 'completed' : '' }}"></div>

    <div class="stepper-step {{ $maintenance->status === 'completed' ? 'completed' : ($maintenance->status === 'in_progress' ? 'active' : '') }}">
        <div class="stepper-step-num">2</div>
        <span>En cours d'exécution</span>
    </div>
    <div class="stepper-line {{ $maintenance->status === 'completed' ? 'completed' : '' }}"></div>

    <div class="stepper-step {{ $maintenance->status === 'completed' ? 'completed' : '' }}">
        <div class="stepper-step-num">3</div>
        <span>Terminée & Validée</span>
    </div>

    @if($maintenance->status === 'cancelled')
        <div style="margin-left: auto;">
            <span class="badge badge-neutral" style="font-size: 12px;">Opération annulée</span>
        </div>
    @elseif($maintenance->is_overdue)
        <div style="margin-left: auto;">
            <span class="badge badge-danger" style="font-size: 12px;">Échéance dépassée</span>
        </div>
    @endif
</div>

{{-- Prolongation Alert Banner --}}
@if($maintenance->prolonged_at)
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 5px solid #f59e0b; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.08);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
                <strong style="color: #b45309; font-size: 14px;">⏳ Échéance reportée de {{ $maintenance->prolongation_days }} jour(s) par le technicien</strong>
                <span style="color: #92400e; font-size: 13px; display: block; margin-top: 3px;">
                    Nouvelle date planifiée : <strong>{{ $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y') : 'Non définie' }}</strong>
                    @if($maintenance->prolongation_reason)
                        — Motif : « <em>{{ $maintenance->prolongation_reason }}</em> »
                    @endif
                </span>
            </div>
            <span class="badge badge-warning" style="font-size: 12px;">+{{ $maintenance->prolongation_days }} jours</span>
        </div>
    </div>
@endif

{{-- Main Details Grid --}}
<div class="detail-grid">
    {{-- Left Column: Description, Technical Report & Dates --}}
    <div>
        <div class="panel" style="margin-bottom: 20px;">
            <div class="panel-heading" style="margin-bottom: 14px;">
                <div>
                    <h2>Descriptif des Travaux</h2>
                    <p>Objectifs et périmètre de l'intervention</p>
                </div>
            </div>

            @php
                $desc = $maintenance->description ?? '';
                $hasChecklist = str_contains($desc, '--- GAMME OPÉRATOIRE & CHECKLIST ---') || str_contains($desc, '[ ]') || str_contains($desc, '[x]');
                
                $introText = $desc;
                $checklistItems = [];
                
                if ($hasChecklist) {
                    if (str_contains($desc, '--- GAMME OPÉRATOIRE & CHECKLIST ---')) {
                        $parts = explode('--- GAMME OPÉRATOIRE & CHECKLIST ---', $desc);
                        $introText = trim($parts[0]);
                        $checklistRaw = trim($parts[1] ?? '');
                    } else {
                        $introText = '';
                        $checklistRaw = $desc;
                    }
                    
                    $lines = preg_split('/\r\n|\r|\n/', $checklistRaw);
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if (empty($trimmed)) continue;
                        if (str_starts_with($trimmed, '[ ]') || str_starts_with($trimmed, '[x]') || str_starts_with($trimmed, '[X]')) {
                            $isChecked = str_starts_with($trimmed, '[x]') || str_starts_with($trimmed, '[X]');
                            $itemText = trim(preg_replace('/^\[[ xX]?\]\s*/', '', $trimmed));
                            $checklistItems[] = [
                                'checked' => $isChecked,
                                'text' => $itemText,
                            ];
                        } elseif (empty($introText) && empty($checklistItems)) {
                            $introText .= ($introText ? "\n" : '') . $trimmed;
                        }
                    }
                }
            @endphp

            @if($introText)
                <p style="color: var(--ink); line-height: 1.6; margin-bottom: 18px; font-size: 14px;">
                    {!! nl2br(e($introText)) !!}
                </p>
            @endif

            @if(!empty($checklistItems))
                <div style="background: linear-gradient(135deg, #f7faf9 0%, #f0f7f5 100%); border: 1px solid var(--line); border-radius: 8px; padding: 18px; margin-bottom: 22px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid rgba(22, 183, 163, 0.2); padding-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                        <span style="font-size: 11px; font-weight: 800; color: var(--aqua); letter-spacing: 0.08em; text-transform: uppercase; display: flex; align-items: center; gap: 6px;">
                            <span>📋</span> GAMME OPÉRATOIRE & CHECKLIST TECHNIQUE
                        </span>
                        <span class="badge badge-info" style="font-size: 10px;">{{ count($checklistItems) }} points de contrôle</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        @foreach($checklistItems as $idx => $item)
                            <div style="display: flex; align-items: flex-start; gap: 10px; background: #fff; padding: 10px 14px; border: 1px solid var(--line); border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                                <div style="width: 20px; height: 20px; border-radius: 4px; border: 2px solid {{ $item['checked'] ? 'var(--aqua)' : '#cbd5e1' }}; background: {{ $item['checked'] ? 'var(--aqua)' : '#fff' }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; color: #fff; font-size: 11px; font-weight: 800;">
                                    {{ $item['checked'] ? '✓' : '' }}
                                </div>
                                <div style="flex: 1; font-size: 13px; color: var(--ink); line-height: 1.4;">
                                    <span style="color: var(--muted); font-size: 11px; margin-right: 4px;">#{{ $idx + 1 }}</span>
                                    {{ $item['text'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif(!$introText)
                <p style="color: var(--muted); font-style: italic; margin-bottom: 22px;">
                    Aucune description spécifique fournie pour cette maintenance.
                </p>
            @endif

            <div class="detail-meta detail-meta-responsive">
                <div>
                    <span>Date planifiée</span>
                    <strong>{{ $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y H:i') : '—' }}</strong>
                </div>
                <div>
                    <span>Début réel</span>
                    <strong>{{ $maintenance->started_at ? $maintenance->started_at->format('d/m/Y H:i') : '—' }}</strong>
                </div>
                <div>
                    <span>Achèvement effectif</span>
                    <strong>{{ $maintenance->completed_at ? $maintenance->completed_at->format('d/m/Y H:i') : '—' }}</strong>
                </div>
                <div>
                    <span>Coût de l'opération</span>
                    <strong>{{ $maintenance->cost ? number_format($maintenance->cost, 2, ',', ' ') . ' €' : 'Non chiffré' }}</strong>
                </div>
                <div>
                    <span>Temps passé</span>
                    <strong>{{ $maintenance->duration_hours ? $maintenance->duration_hours . ' heures' : 'Non précisé' }}</strong>
                </div>
                <div>
                    <span>Prochaine révision</span>
                    <strong>{{ $maintenance->next_maintenance_date ? $maintenance->next_maintenance_date->format('d/m/Y') : 'Non planifiée' }}</strong>
                </div>
            </div>
        </div>

        {{-- Manager Completion Review Box --}}
        @if($maintenance->status === 'awaiting_validation')
            <div class="panel" style="border: 2px solid #aa721c; background: #fffcf5; margin-bottom: 20px; box-shadow: 0 6px 24px rgba(170, 114, 28, 0.12);">
                <div class="panel-heading" style="margin-bottom: 14px;">
                    <div>
                        <span class="eyebrow" style="color: #aa721c;">REVUE DE CLÔTURE · ACTION GESTIONNAIRE REQUISE</span>
                        <h2 style="color: #784a05; font-size: 18px; margin-top: 4px;">Rapport Technique Soumis par le Technicien</h2>
                        <p style="color: #784a05; font-size: 13px;">Soumis le {{ $maintenance->completion_submitted_at?->format('d/m/Y à H:i') }} par <strong>{{ $maintenance->technician?->name ?? 'Le technicien' }}</strong>.</p>
                    </div>
                    <span class="badge badge-warning" style="font-size: 12px; padding: 6px 14px; border-radius: 20px;">
                        En attente de validation
                    </span>
                </div>

                <div style="background: #ffffff; border: 1px solid #fed7aa; border-radius: 8px; padding: 18px; margin-bottom: 20px; font-size: 14px; color: #172c32;">
                    <div style="margin-bottom: 12px;">
                        <strong style="color: #708388; font-size: 11px; display: block; text-transform: uppercase;">Travaux réalisés & Compte-rendu</strong>
                        <div style="margin-top: 4px; white-space: pre-line; line-height: 1.5;">{{ $maintenance->result }}</div>
                    </div>

                    @if(!empty($maintenance->completion_report['findings']))
                        <div style="margin-bottom: 12px;">
                            <strong style="color: #708388; font-size: 11px; display: block; text-transform: uppercase;">Constatations de terrain</strong>
                            <div style="margin-top: 4px;">{{ $maintenance->completion_report['findings'] }}</div>
                        </div>
                    @endif

                    @if(!empty($maintenance->completion_report['parts_replaced']))
                        <div style="margin-bottom: 12px;">
                            <strong style="color: #708388; font-size: 11px; display: block; text-transform: uppercase;">Pièces remplacées</strong>
                            <div style="margin-top: 4px;">{{ $maintenance->completion_report['parts_replaced'] }}</div>
                        </div>
                    @endif

                    @if(!empty($maintenance->completion_report['tests_verification']))
                        <div style="margin-bottom: 12px;">
                            <strong style="color: #708388; font-size: 11px; display: block; text-transform: uppercase;">Tests & Mesures de vérification</strong>
                            <div style="margin-top: 4px;">{{ $maintenance->completion_report['tests_verification'] }}</div>
                        </div>
                    @endif

                    <div style="display: flex; flex-wrap: wrap; gap: 20px; border-top: 1px dashed #fed7aa; padding-top: 10px; font-size: 13px; color: var(--muted);">
                        <span>Durée constatée : <strong>{{ $maintenance->duration_hours }} h</strong></span>
                        <span>Date réelle de fin : <strong>{{ $maintenance->completion_submitted_at?->format('d/m/Y H:i') }}</strong></span>
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                    {{-- Form: Validate and Close --}}
                    <form method="POST" action="{{ route('maintenances.validate-completion', $maintenance) }}" style="margin: 0;">
                        @csrf
                        <button type="submit" class="button" style="background: #13886f; font-weight: 800; padding: 12px 24px; box-shadow: 0 4px 12px rgba(19, 136, 111, 0.25);">
                            ✓ Valider et clôturer (Terminée & Validée)
                        </button>
                    </form>

                    {{-- Button: Request corrections --}}
                    <button type="button" class="button button-outline" onclick="document.getElementById('manager-corrections-box').style.display = 'block'; this.style.display = 'none';" style="border-color: #ea580c; color: #ea580c; padding: 12px 20px;">
                        💬 Demander des corrections au technicien
                    </button>
                </div>

                <div id="manager-corrections-box" style="display: none; margin-top: 18px; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 18px;">
                    <form method="POST" action="{{ route('maintenances.request-corrections', $maintenance) }}">
                        @csrf
                        <label for="correction_notes" style="display: block; font-size: 13px; font-weight: 700; color: #9a3412; margin-bottom: 6px;">
                            Précisez les points à corriger ou compléter par le technicien :
                        </label>
                        <textarea id="correction_notes" name="correction_notes" rows="3" required placeholder="Ex: Veuillez préciser la valeur de pression mesurée lors du test d'étanchéité..." style="width: 100%; border: 1px solid #fed7aa; border-radius: 6px; padding: 10px; font-family: inherit; font-size: 14px; margin-bottom: 12px;"></textarea>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="button" style="background: #ea580c; color: #fff; padding: 10px 18px; font-size: 13px;">
                                Transmettre la demande de corrections
                            </button>
                            <button type="button" class="button button-ghost" onclick="document.getElementById('manager-corrections-box').style.display = 'none';">
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Compte-rendu technique --}}
        <div class="panel">
            <div class="panel-heading" style="margin-bottom: 14px;">
                <div>
                    <h2>Compte-Rendu & Rapport d'Intervention</h2>
                    <p>Observations et conclusions rédigées par l'équipe technique</p>
                </div>
                @if($maintenance->status === 'completed')
                    <span class="badge badge-success" style="font-size: 11px;">✓ Validée</span>
                @endif
            </div>

            @if($maintenance->result)
                <div style="background: #fbfdfc; border-left: 4px solid var(--aqua); border-radius: 6px; padding: 16px 20px; font-size: 13px; line-height: 1.6; color: var(--ink);">
                    {{ $maintenance->result }}
                    @if($maintenance->completion_validated_at)
                        <div style="margin-top: 10px; font-size: 11px; color: #13886f; font-weight: 700; border-top: 1px dashed var(--line); padding-top: 8px;">
                            ✓ Clôturée et validée le {{ $maintenance->completion_validated_at->format('d/m/Y à H:i') }}
                            @if($maintenance->validator) par {{ $maintenance->validator->name }} @endif
                        </div>
                    @endif
                </div>
            @else
                <div style="background: #fbfdfc; border: 1px dashed var(--line); border-radius: 6px; padding: 22px; text-align: center; color: var(--muted); font-size: 13px;">
                    Aucun compte-rendu technique n'a encore été consigné pour cette opération.
                    <br>
                    <a class="text-link" href="{{ route('maintenances.edit', $maintenance) }}" style="margin-top: 8px;">
                        Ajouter un compte-rendu d'intervention →
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Right Column: Associated Infrastructure & Technician Cards --}}
    <div>
        {{-- Infrastructure Card --}}
        @if($maintenance->infrastructure)
            <div class="panel" style="margin-bottom: 20px;">
                <div class="panel-heading" style="margin-bottom: 12px;">
                    <div>
                        <span class="eyebrow">INFRASTRUCTURE RATTACHÉE</span>
                        <h2 style="margin-top: 2px;">
                            <a href="{{ route('infrastructures.show', $maintenance->infrastructure) }}" style="color: var(--ink);">
                                {{ $maintenance->infrastructure->name }}
                            </a>
                        </h2>
                    </div>
                    <span class="badge {{ $maintenance->infrastructure->status_badge_class }}">
                        {{ $maintenance->infrastructure->status_label }}
                    </span>
                </div>

                <div class="infra-card-meta-list" style="margin-bottom: 14px;">
                    <div>
                        <span>Référence</span>
                        <strong>{{ $maintenance->infrastructure->reference_code }}</strong>
                    </div>
                    <div>
                        <span>Type</span>
                        <strong>{{ $maintenance->infrastructure->type }}</strong>
                    </div>
                    <div>
                        <span>Bassin</span>
                        <strong>{{ $maintenance->infrastructure->zone?->name ?? '—' }}</strong>
                    </div>
                    <div>
                        <span>Santé site</span>
                        <strong>{{ $maintenance->infrastructure->health_score }}% ({{ $maintenance->infrastructure->health_label }})</strong>
                    </div>
                </div>

                <div style="text-align: right;">
                    <a class="button button-small button-outline" href="{{ route('infrastructures.show', $maintenance->infrastructure) }}">
                        Consulter la fiche infrastructure →
                    </a>
                </div>
            </div>
        @endif

        <div class="panel">
            <div class="panel-heading" style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px;">
                <div>
                    <span class="eyebrow">RESSOURCE TECHNIQUE</span>
                    <h2 style="margin-top: 2px;">Intervenants</h2>
                </div>
                @if(!$maintenance->technician_assignment_locked)
                    <button type="button" class="button button-small" onclick="openTechRecommendationModalShow()" style="background: linear-gradient(135deg, var(--deep) 0%, #1e3a47 100%); border: 1px solid var(--aqua); color: #fff; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(22, 183, 163, 0.2);">
                        <span style="color: var(--aqua);">✨</span> Recommander avec l'IA
                    </button>
                @else
                    <span class="badge badge-success" style="font-size: 11px; padding: 4px 10px; border-radius: 20px;">
                        🔒 Affectation verrouillée
                    </span>
                @endif
            </div>

            @if($maintenance->technician)
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                    <span class="avatar" style="width: 42px; height: 42px; font-size: 14px;">
                        {{ collect(explode(' ', $maintenance->technician->name))->map(fn ($n) => substr($n, 0, 1))->join('') }}
                    </span>
                    <div>
                        <strong style="font-size: 15px; color: var(--ink);">
                            <a href="{{ route('techniciens.show', $maintenance->technician) }}" style="color: var(--ink);">
                                {{ $maintenance->technician->name }}
                            </a>
                        </strong>
                        <small style="color: var(--muted); display: block; font-size: 11px;">
                            Spécialité : {{ $maintenance->technician->speciality ?? 'Généraliste' }}
                        </small>
                    </div>
                </div>

                {{-- Status & Invitation Feedback Badges --}}
                <div style="margin-bottom: 14px;">
                    @if($maintenance->technician_assignment_locked)
                        <div style="background: #def5ed; border: 1px solid #13886f; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #13886f;">
                            🔒 <strong>Affectation acceptée et verrouillée</strong>
                            <div style="font-size: 11px; margin-top: 2px;">
                                Confirmée par le technicien le {{ $maintenance->assignment_accepted_at?->format('d/m/Y à H:i') }}.
                            </div>
                        </div>
                    @elseif($maintenance->latestInvitation && $maintenance->latestInvitation->isPending())
                        <div style="background: #fff9eb; border: 1px solid #aa721c; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #784a05;">
                            ⏳ <strong>Invitation Brevo en attente d'acceptation</strong>
                            <div style="font-size: 11px; margin-top: 3px;">
                                Email envoyé le {{ $maintenance->latestInvitation->invited_at?->format('d/m/Y à H:i') }}.
                                @if($maintenance->latestInvitation->email_delivery_status === 'sent')
                                    <span style="color: #13886f; font-weight: 700;">✓ Email Brevo délivré</span>
                                @else
                                    <span style="color: #bd5149; font-weight: 700;">⚠ Notification non confirmée</span>
                                @endif
                            </div>
                            @if($maintenance->latestInvitation->email_delivery_status === 'failed')
                                <div style="margin-top: 6px; color: #bd5149; font-size: 11px;">
                                    Erreur : {{ $maintenance->latestInvitation->email_error }}
                                </div>
                                <form method="POST" action="{{ route('maintenances.resend-invitation', $maintenance) }}" style="margin-top: 8px;">
                                    @csrf
                                    <button type="submit" class="button button-small" style="background: #bd5149; padding: 4px 10px; font-size: 11px; color: #fff;">
                                        Retenter l'envoi de l'invitation
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>

                <ul style="list-style: none; padding: 0; margin: 0; font-size: 12px; color: var(--muted);">
                    @if($maintenance->technician->phone)
                        <li style="padding: 4px 0;">📞 {{ $maintenance->technician->phone }}</li>
                    @endif
                    @if($maintenance->technician->email)
                        <li style="padding: 4px 0;">✉ {{ $maintenance->technician->email }}</li>
                    @endif
                    @if($maintenance->team)
                        <li style="padding: 4px 0;">👥 Équipe : <strong>{{ $maintenance->team }}</strong></li>
                    @endif
                </ul>
            @else
                <p style="font-size: 12px; color: var(--muted); margin: 0 0 10px;">
                    Aucun technicien individuel n'est affecté à cette opération.
                </p>
                @if($maintenance->team)
                    <p style="font-size: 12px; color: var(--ink); margin: 0;">
                        Équipe en charge : <strong>{{ $maintenance->team }}</strong>
                    </p>
                @endif
            @endif
        </div>
    </div>
</div>

{{-- Modal: AI Technician Matchmaker (Show View) --}}
<div id="modal-tech-recommendation-show" class="intelligence-modal-backdrop" style="display: none;" onclick="if(event.target === this) closeTechRecommendationModalShow()">
    <div class="intelligence-modal-dialog" style="max-width: 760px; max-height: 88vh; background: #fff; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); display: flex; flex-direction: column; overflow: hidden; margin: auto;">
        {{-- Header --}}
        <div class="intelligence-modal-header" style="padding: 18px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: flex-start; background: #fbfdfc; flex-shrink: 0;">
            <div>
                <span class="eyebrow" style="color: var(--aqua); font-size: 11px; font-weight: 800; letter-spacing: 0.1em;">AI TECHNICIAN MATCHMAKER · INTELLIGENCE OPÉRATIONNELLE</span>
                <h2 style="margin: 4px 0 0; font-size: 20px; color: var(--deep);">Recommandation pour {{ $maintenance->reference_code ?? ('#' . $maintenance->id) }}</h2>
                <small style="color: var(--muted);">Classement factuel des techniciens éligibles basé sur les qualifications et charges réelles</small>
            </div>
            <button class="modal-close-btn" type="button" onclick="closeTechRecommendationModalShow()" style="border: 0; background: none; font-size: 20px; cursor: pointer; color: var(--muted);">✕</button>
        </div>

        {{-- Mission Context Pill Strip --}}
        <div style="background: #f1f8f7; border-bottom: 1px solid var(--line); padding: 10px 24px; display: flex; gap: 14px; font-size: 12px; color: var(--deep); flex-shrink: 0; flex-wrap: wrap;">
            <span>🔧 Mission : <strong>{{ $maintenance->type }}</strong></span>
            <span>⚡ Priorité : <strong>{{ strtoupper($maintenance->priority) }}</strong></span>
            <span>▦ Site : <strong>{{ $maintenance->infrastructure?->name ?? 'Non spécifié' }}</strong></span>
            <span id="tech-rec-ai-badge-show" style="margin-left: auto; font-weight: 700; color: var(--aqua);">✨ Analyse Groq AI</span>
        </div>

        {{-- Scrollable Body --}}
        <div class="intelligence-modal-body" style="padding: 20px 24px; overflow-y: auto; flex: 1 1 auto; min-height: 0;">
            {{-- Loading State --}}
            <div id="tech-rec-loading-show" style="display: none; text-align: center; padding: 40px 20px;">
                <div style="width: 48px; height: 48px; border: 4px solid #e2e8f0; border-top-color: var(--aqua); border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px;"></div>
                <h3 style="font-size: 16px; margin: 0 0 6px; color: var(--deep);">Classement des techniciens par l'IA en cours…</h3>
                <p style="font-size: 13px; color: var(--muted); margin: 0;">Vérification des disponibilités réelles, adéquation des spécialités et minimisation de la surcharge.</p>
            </div>

            {{-- Error State --}}
            <div id="tech-rec-error-show" style="display: none; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                <strong style="color: #991b1b; display: block; margin-bottom: 4px;">Échec de la recommandation</strong>
                <p id="tech-rec-error-msg-show" style="color: #b91c1c; font-size: 13px; margin: 0;"></p>
            </div>

            {{-- Empty State --}}
            <div id="tech-rec-empty-show" style="display: none; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 20px; text-align: center;">
                <div style="font-size: 28px; margin-bottom: 8px;">👷‍♂️</div>
                <h4 style="font-size: 15px; color: #92400e; margin: 0 0 6px;">Aucun candidat éligible disponible</h4>
                <p id="tech-rec-empty-reason-show" style="font-size: 13px; color: #b45309; margin: 0;"></p>
            </div>

            {{-- Results Container --}}
            <div id="tech-rec-results-show" style="display: none;">
                <div id="tech-rec-cards-container-show"></div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="intelligence-modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; background: #fbfdfc; flex-shrink: 0;">
            <button type="button" class="button button-outline" onclick="closeTechRecommendationModalShow()">Fermer</button>
        </div>
    </div>
</div>

<script>
function openTechRecommendationModalShow() {
    const modal = document.getElementById('modal-tech-recommendation-show');
    if (!modal) return;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    document.getElementById('tech-rec-loading-show').style.display = 'block';
    document.getElementById('tech-rec-results-show').style.display = 'none';
    document.getElementById('tech-rec-empty-show').style.display = 'none';
    document.getElementById('tech-rec-error-show').style.display = 'none';

    fetch("{{ route('maintenances.recommend-technicians') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            maintenance_id: {{ $maintenance->id }},
            type: @json($maintenance->type),
            priority: @json($maintenance->priority),
            infrastructure_id: {{ $maintenance->infrastructure_id ?? 'null' }},
            description: @json(substr((string)$maintenance->description, 0, 300)),
        })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('tech-rec-loading-show').style.display = 'none';

        if (!data.success) {
            document.getElementById('tech-rec-error-show').style.display = 'block';
            document.getElementById('tech-rec-error-msg-show').textContent = data.message || "Une erreur est survenue lors de l'analyse.";
            return;
        }

        const recs = data.recommendations;
        const rankings = recs.candidate_rankings || [];
        const allCandidates = recs.all_candidates || [];

        if (rankings.length === 0 && allCandidates.length === 0) {
            document.getElementById('tech-rec-empty-show').style.display = 'block';
            document.getElementById('tech-rec-empty-reason-show').textContent = recs.no_suitable_candidate_reason || "Aucun technicien éligible disponible.";
            return;
        }

        renderCandidateRankingsShow(rankings, allCandidates, recs.is_ai_generated);
        document.getElementById('tech-rec-results-show').style.display = 'block';
    })
    .catch(err => {
        document.getElementById('tech-rec-loading-show').style.display = 'none';
        document.getElementById('tech-rec-error-show').style.display = 'block';
        document.getElementById('tech-rec-error-msg-show').textContent = "Erreur de connexion au service d'analyse IA.";
    });
}

function closeTechRecommendationModalShow() {
    const modal = document.getElementById('modal-tech-recommendation-show');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTechRecommendationModalShow();
    }
});

function renderCandidateRankingsShow(rankings, allCandidates, isAi) {
    const container = document.getElementById('tech-rec-cards-container-show');
    container.innerHTML = '';

    const aiBadge = document.getElementById('tech-rec-ai-badge-show');
    if (aiBadge) {
        aiBadge.textContent = isAi ? '✨ Analyse Groq AI (gpt-oss-120b)' : '⚡ Analyse Déterministe AquaSecure';
    }

    rankings.forEach(cand => {
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
                <button type="button" class="button button-small" onclick="showAssignConfirmation(${cand.technician_id}, '${escapeHtml(cand.name).replace(/'/g, "\\'")}', '${escapeHtml(cand.speciality || '').replace(/'/g, "\\'")}')" style="${isTop ? 'background: var(--aqua);' : 'background: var(--deep);'} color: #fff;">
                    ✉ Inviter & Proposer à ce technicien
                </button>
            </div>
        `;
        container.appendChild(card);
    });

    renderRemainingTechniciansShow(container, rankings, allCandidates);
}

/**
 * Affiche les techniciens de l'effectif non retenus par le classement IA.
 * Ils restent affectables directement depuis la fenêtre de recommandation.
 */
function renderRemainingTechniciansShow(container, rankings, allCandidates) {
    const rankedIds = (rankings || []).map(c => Number(c.technician_id));
    const others = (allCandidates || []).filter(c => !rankedIds.includes(Number(c.id)));

    if (!others.length) {
        return;
    }

    const header = document.createElement('div');
    header.style.cssText = 'margin: 24px 0 12px; padding-top: 16px; border-top: 1px dashed var(--line);';
    header.innerHTML = `
        <span style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em;">Effectif technique restant (${others.length})</span>
        <p style="font-size: 12px; color: var(--muted); margin: 4px 0 0;">Ces techniciens n'ont pas été retenus par le classement IA, mais restent directement affectables.</p>
    `;
    container.appendChild(header);

    others.forEach(cand => {
        const card = document.createElement('div');
        card.style.cssText = 'border: 1px solid var(--line); background: #ffffff; border-radius: 8px; padding: 14px; margin-bottom: 10px;';

        const score = Number(cand.compatibility_score || 0);
        const matchLabel = score >= 80 ? 'Excellente' : (score >= 60 ? 'Bonne' : 'Partielle');
        const matchClass = matchLabel === 'Excellente' ? 'background: #d1fae5; color: #065f46;' : (matchLabel === 'Bonne' ? 'background: #e0f2fe; color: #0369a1;' : 'background: #fef3c7; color: #92400e;');
        const inactive = cand.status === 'inactive';

        const action = inactive
            ? '<span style="font-size: 11px; color: var(--muted);">Technicien inactif — non affectable</span>'
            : `<button type="button" class="button button-small" onclick="showAssignConfirmation(${Number(cand.id)}, '${escapeHtml(cand.name).replace(/'/g, "\\'")}', '${escapeHtml(cand.speciality || '').replace(/'/g, "\\'")}')" style="background: var(--deep); color: #fff;">✉ Inviter & Proposer à ce technicien</button>`;

        card.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; font-weight: 800; font-size: 13px; background: #f1f5f9; color: var(--ink);">
                        ${score}
                    </span>
                    <div>
                        <strong style="font-size: 14px; color: var(--deep); display: block;">${escapeHtml(cand.name)}</strong>
                        <span style="font-size: 12px; color: var(--muted);">${escapeHtml(cand.speciality)}</span>
                    </div>
                </div>
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; ${matchClass};">${matchLabel}</span>
                    <span style="font-size: 11px; padding: 3px 8px; border-radius: 4px; background: #f1f5f9; color: var(--ink);">💼 ${Number(cand.active_workload || 0)} intervention(s) en cours</span>
                    ${inactive ? '<span style="font-size: 11px; padding: 3px 8px; border-radius: 4px; background: #fee2e2; color: #991b1b;">Inactif</span>' : ''}
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; align-items: center; border-top: 1px dashed var(--line); padding-top: 10px; margin-top: 10px;">
                ${action}
            </div>
        `;
        container.appendChild(card);
    });
}

let pendingAssignTechId = null;

function showAssignConfirmation(techId, techName, techSpeciality) {
    pendingAssignTechId = techId;
    document.getElementById('confirm-modal-tech-name').textContent = techName;
    document.getElementById('confirm-modal-tech-speciality').textContent = techSpeciality || 'Généraliste';
    document.getElementById('modal-assign-confirm').style.display = 'flex';
}

function closeAssignConfirmation() {
    pendingAssignTechId = null;
    const modal = document.getElementById('modal-assign-confirm');
    if (modal) modal.style.display = 'none';
}

function submitAssignmentInvitation() {
    if (!pendingAssignTechId) return;

    const btn = document.getElementById('btn-confirm-send-invitation');
    btn.disabled = true;
    btn.textContent = "Transmission en cours...";

    fetch("{{ route('maintenances.assign-technician', $maintenance) }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ technician_id: pendingAssignTechId })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = "Confirmer & Envoyer l'invitation";
        closeAssignConfirmation();
        closeTechRecommendationModalShow();

        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || "Erreur lors de l'envoi de l'invitation.");
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = "Confirmer & Envoyer l'invitation";
        alert("Erreur de communication avec le serveur.");
    });
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

{{-- Confirmation Dialog Modal for Manager --}}
<div id="modal-assign-confirm" class="intelligence-modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 10000;" onclick="if(event.target === this) closeAssignConfirmation()">
    <div style="background: #fff; border-radius: 10px; max-width: 520px; width: 92%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; border: 1px solid var(--line);">
        <div style="background: #073f4a; color: #fff; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 800; font-size: 16px;">
                ✉ Confirmation de l'invitation d'intervention
            </div>
            <button type="button" onclick="closeAssignConfirmation()" style="background: transparent; border: 0; color: #bad1d0; font-size: 20px; cursor: pointer;">✕</button>
        </div>

        <div style="padding: 24px; font-size: 14px;">
            <p style="margin-top: 0; color: #526a6d; line-height: 1.5;">
                Vous êtes sur le point d'adresser une invitation d'intervention officielle au technicien sélectionné :
            </p>

            <div style="background: #f7faf9; border: 1px solid #dce7e5; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px;">
                <div style="margin-bottom: 6px;">
                    <span style="color: #708388; font-size: 12px;">Technicien sélectionné :</span>
                    <strong style="color: #073f4a; font-size: 15px; display: block;" id="confirm-modal-tech-name">—</strong>
                    <small style="color: #526a6d;" id="confirm-modal-tech-speciality">—</small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px; border-top: 1px dashed #dce7e5; padding-top: 10px; font-size: 12px;">
                    <div>
                        <span style="color: #708388;">Référence :</span>
                        <strong style="display: block;">{{ $maintenance->reference_code ?? ('MNT-#' . $maintenance->id) }}</strong>
                    </div>
                    <div>
                        <span style="color: #708388;">Date planifiée :</span>
                        <strong style="display: block;">{{ $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y H:i') : 'Non définie' }}</strong>
                    </div>
                </div>
            </div>

            <div style="background: #fff9eb; border-left: 4px solid #aa721c; padding: 12px 14px; border-radius: 4px; font-size: 12px; color: #784a05; margin-bottom: 20px; line-height: 1.5;">
                <strong>Procédure Brevo Transactionnelle :</strong> Un email sécurisé sera immédiatement expédié au technicien avec un jeton cryptographique à usage unique. L'affectation ne sera <em>définitivement verrouillée</em> et l'opération passée <em>« En cours d'exécution »</em> qu'après acceptation expresse du technicien.
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="button button-outline" onclick="closeAssignConfirmation()">
                    Annuler
                </button>
                <button type="button" id="btn-confirm-send-invitation" class="button" onclick="submitAssignmentInvitation()" style="background: #073f4a; color: #fff; font-weight: 800;">
                    Confirmer & Envoyer l'invitation
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
