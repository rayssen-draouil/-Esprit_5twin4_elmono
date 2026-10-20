@extends('layouts.back', ['title' => 'Intervention ' . ($maintenance->reference_code ?? ('#' . $maintenance->id))])

@section('content')
<div class="breadcrumb" style="margin-bottom: 15px;">
    <a href="{{ route('technicien.interventions.index') }}">Mes interventions</a>
    <span>/</span>
    <strong>{{ $maintenance->reference_code ?? ('MNT-#' . $maintenance->id) }}</strong>
</div>

<div class="content-heading" style="margin-bottom: 24px;">
    <div>
        <span class="eyebrow">ORDRE DE MISSION TECHNIQUE</span>
        <h1 style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            {{ $maintenance->reference_code ?? ('MNT-#' . $maintenance->id) }}
            <span class="badge {{ $maintenance->status_badge_class }}" style="font-size: 13px; padding: 6px 14px; border-radius: 20px;">
                {{ $maintenance->display_status_label }}
            </span>
            <span class="badge {{ $maintenance->priority_badge_class }}" style="font-size: 13px; padding: 6px 14px; border-radius: 20px;">
                Priorité {{ $maintenance->priority_label }}
            </span>
            @if($maintenance->technician_assignment_locked)
                <span class="badge badge-success" style="font-size: 12px; padding: 5px 12px; border-radius: 20px;">
                    🔒 Affectation Verrouillée
                </span>
            @endif
        </h1>
        <p>Intervention assignée à <strong>{{ $technician->name }}</strong> ({{ $technician->speciality }}).</p>
    </div>

    <div>
        <a href="{{ route('technicien.interventions.index') }}" class="button button-outline">
            ← Revenir à la liste
        </a>
    </div>
</div>

{{-- Session Feedback --}}
@if(session('success'))
    <div style="background: #def5ed; border-left: 4px solid #13886f; color: #13886f; padding: 16px 20px; border-radius: 6px; margin-bottom: 24px; font-weight: 600;">
        ✓ {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div style="background: #ffe4e1; border-left: 4px solid #bd5149; color: #bd5149; padding: 16px 20px; border-radius: 6px; margin-bottom: 24px; font-weight: 600;">
        ⚠ {{ session('error') }}
    </div>
@endif

{{-- Lifecycle Progress Stepper (1 Planifiée -> 2 En cours -> 3 Terminée) --}}
<div class="stepper-wrap" style="display: flex; align-items: center; gap: 12px; background: #fbfdfc; border: 1px solid var(--line); border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; overflow-x: auto;">
    <div class="stepper-step completed">
        <div class="stepper-step-num">1</div>
        <span>Planifiée</span>
    </div>
    <div class="stepper-line {{ in_array($maintenance->status, ['in_progress', 'awaiting_validation', 'completed']) ? 'completed' : '' }}"></div>

    <div class="stepper-step {{ $maintenance->status === 'completed' ? 'completed' : (in_array($maintenance->status, ['in_progress', 'awaiting_validation']) ? 'active' : '') }}">
        <div class="stepper-step-num">2</div>
        <span>En cours d'exécution</span>
    </div>
    <div class="stepper-line {{ $maintenance->status === 'completed' ? 'completed' : '' }}"></div>

    <div class="stepper-step {{ $maintenance->status === 'completed' ? 'completed' : '' }}">
        <div class="stepper-step-num">3</div>
        <span>Terminée & Validée</span>
    </div>

    @if($maintenance->status === 'completed')
        <div style="margin-left: auto;">
            <span class="badge badge-success" style="font-size: 12px; padding: 6px 14px; border-radius: 20px;">
                ✓ Mission Accomplie (Santé Ouvrage: 100%)
            </span>
        </div>
    @elseif($maintenance->prolonged_at)
        <div style="margin-left: auto;">
            <span class="badge badge-warning" style="font-size: 12px; padding: 6px 14px; border-radius: 20px;">
                ⏳ Échéance reportée (+{{ $maintenance->prolongation_days }}j)
            </span>
        </div>
    @endif
</div>

{{-- Prolongation notification banner if active --}}
@if($maintenance->prolonged_at)
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 5px solid #f59e0b; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.08);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
                <strong style="color: #b45309; font-size: 14px; display: block;">⏳ Intervention prolongée de {{ $maintenance->prolongation_days }} jour(s) :</strong>
                <span style="color: #92400e; font-size: 13px;">
                    Nouvelle date fixée au <strong>{{ $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y') : '' }}</strong>.
                    @if($maintenance->prolongation_reason)
                        — Motif : « <em>{{ $maintenance->prolongation_reason }}</em> »
                    @endif
                </span>
            </div>
            <span style="font-size: 12px; color: #b45309; font-weight: 700;">Gestionnaire informé</span>
        </div>
    </div>
@endif

{{-- Correction Notes from Manager --}}
@if($maintenance->manager_correction_notes && $maintenance->status === 'in_progress')
    <div style="background: #fff7ed; border: 1px solid #fed7aa; border-left: 5px solid #ea580c; border-radius: 8px; padding: 20px 24px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(234, 88, 12, 0.08);">
        <div style="display: flex; align-items: center; gap: 8px; color: #c2410c; font-weight: 800; font-size: 15px; margin-bottom: 8px;">
            <span>⚠ Demande de compléments formulée par le gestionnaire :</span>
        </div>
        <p style="color: #9a3412; font-size: 14px; margin: 0; line-height: 1.6;">
            {{ $maintenance->manager_correction_notes }}
        </p>
        <small style="color: #c2410c; display: block; margin-top: 8px; font-weight: 600;">
            Veuillez ajuster les éléments ci-dessous puis re-soumettre le compte-rendu technique.
        </small>
    </div>
@endif

{{-- Two Columns Layout --}}
<div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 24px; align-items: start;">

    {{-- Left: Infrastructure & Mission Specifications --}}
    <div style="display: flex; flex-direction: column; gap: 20px;">
        {{-- Infrastructure Card --}}
        <div class="panel">
            <h2 style="font-size: 16px; margin-top: 0; margin-bottom: 14px; color: #073f4a; display: flex; align-items: center; gap: 8px;">
                <span>▦</span> Infrastructure concernée
            </h2>

            <div style="font-size: 14px; line-height: 1.6;">
                <div style="margin-bottom: 10px;">
                    <span style="color: var(--muted); font-size: 12px; display: block;">Nom de l'ouvrage</span>
                    <strong style="font-size: 16px; color: #172c32;">{{ $maintenance->infrastructure?->name ?? 'Non spécifié' }}</strong>
                </div>

                <div style="margin-bottom: 10px;">
                    <span style="color: var(--muted); font-size: 12px; display: block;">Type / Famille</span>
                    <span>{{ $maintenance->infrastructure?->type ?? 'Réseau hydraulique' }}</span>
                </div>

                <div style="margin-bottom: 10px;">
                    <span style="color: var(--muted); font-size: 12px; display: block;">Zone géographique</span>
                    <span>{{ $maintenance->infrastructure?->zone?->name ?? 'Zone centrale' }} ({{ $maintenance->infrastructure?->zone?->address ?? 'Bassin' }})</span>
                </div>

                <div style="margin-bottom: 10px;">
                    <span style="color: var(--muted); font-size: 12px; display: block;">Statut actuel de l'infrastructure</span>
                    <span class="badge badge-info">{{ $maintenance->infrastructure?->status ?? 'operational' }}</span>
                </div>

                @if($maintenance->infrastructure?->description)
                    <div style="border-top: 1px dashed var(--line); margin-top: 12px; padding-top: 12px;">
                        <span style="color: var(--muted); font-size: 12px; display: block; margin-bottom: 4px;">Description technique</span>
                        <p style="font-size: 13px; color: #526a6d; margin: 0;">{{ $maintenance->infrastructure->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Mission Parameters Card --}}
        <div class="panel">
            <h2 style="font-size: 16px; margin-top: 0; margin-bottom: 14px; color: #073f4a; display: flex; align-items: center; gap: 8px;">
                <span>⛯</span> Paramètres de l'intervention
            </h2>

            <table style="width: 100%; font-size: 13px;">
                <tr>
                    <td style="padding: 6px 0; color: var(--muted);">Type :</td>
                    <td style="padding: 6px 0; font-weight: 700;">{{ $maintenance->type }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: var(--muted);">Priorité :</td>
                    <td style="padding: 6px 0;">
                        <span class="badge {{ $maintenance->priority_badge_class }}">{{ $maintenance->priority_label }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: var(--muted);">Date prévue :</td>
                    <td style="padding: 6px 0; font-weight: 700;">
                        {{ $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y à H:i') : 'Non définie' }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: var(--muted);">Acceptée le :</td>
                    <td style="padding: 6px 0;">
                        {{ $maintenance->assignment_accepted_at ? $maintenance->assignment_accepted_at->format('d/m/Y à H:i') : '—' }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: var(--muted);">Équipe associée :</td>
                    <td style="padding: 6px 0;">{{ $maintenance->team ?: 'Intervention individuelle' }}</td>
                </tr>
            </table>

            <div style="border-top: 1px dashed var(--line); margin-top: 15px; padding-top: 15px;">
                <span style="color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 6px;">Consignes de mission :</span>
                <div style="background: #f7faf9; border: 1px solid #e5eeee; border-radius: 6px; padding: 12px; font-size: 13px; color: #172c32;">
                    {{ $maintenance->description ?: 'Aucune consigne spécifique.' }}
                </div>
            </div>
        </div>

        @if(in_array($maintenance->status, ['in_progress', 'planned']))
            {{-- Prolongation Request Card --}}
            <div class="panel" style="border: 1.5px solid #fed7aa; background: #fffcf9; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.06);">
                <h3 style="font-size: 15px; margin-top: 0; margin-bottom: 8px; color: #9a3412; display: flex; align-items: center; gap: 8px;">
                    <span>⏳</span> Décaler / Prolonger l'échéance
                </h3>
                <p style="font-size: 13px; color: #7c2d12; margin: 0 0 14px; line-height: 1.5;">
                    En cas de contrainte imprévue (pièces de rechange, météo, etc.), prolongez la date. Le gestionnaire sera immédiatement notifié.
                </p>

                <form method="POST" action="{{ route('technicien.interventions.prolong', $maintenance) }}">
                    @csrf
                    <div style="margin-bottom: 12px;">
                        <label for="prolong_days" style="display: block; font-size: 12px; font-weight: 700; color: #526a6d; margin-bottom: 4px;">Durée de report :</label>
                        <select name="days" id="prolong_days" style="width: 100%; border: 1px solid #fed7aa; border-radius: 6px; padding: 9px; font-size: 13px; background: #ffffff;">
                            <option value="1">+1 jour ouvrable</option>
                            <option value="2">+2 jours</option>
                            <option value="3" selected>+3 jours</option>
                            <option value="5">+5 jours</option>
                            <option value="7">+7 jours (1 semaine)</option>
                            <option value="14">+14 jours (2 semaines)</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label for="prolong_reason" style="display: block; font-size: 12px; font-weight: 700; color: #526a6d; margin-bottom: 4px;">Motif explicatif :</label>
                        <textarea name="reason" id="prolong_reason" rows="2" required placeholder="Ex: Délai fournisseur pour vanne papillon, météo défavorable..." style="width: 100%; border: 1px solid #fed7aa; border-radius: 6px; padding: 8px; font-size: 13px; font-family: inherit;"></textarea>
                    </div>

                    <button type="submit" class="button" style="background: #ea580c; color: #fff; font-size: 13px; font-weight: 700; padding: 10px 18px; border-radius: 6px; width: 100%; justify-content: center; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.2);">
                        ⏳ Confirmer la prolongation
                    </button>
                </form>

                @if(!empty($maintenance->prolongation_history) && is_array($maintenance->prolongation_history))
                    <div style="border-top: 1px dashed #fed7aa; margin-top: 14px; padding-top: 10px;">
                        <span style="font-size: 11px; font-weight: 700; color: #9a3412; text-transform: uppercase;">Historique des reports :</span>
                        <ul style="margin: 6px 0 0; padding-left: 18px; font-size: 12px; color: #7c2d12;">
                            @foreach($maintenance->prolongation_history as $h)
                                <li>+{{ $h['days'] ?? 0 }}j ({{ $h['reason'] ?? '' }})</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Right: Execution Controls & Technical Report --}}
    <div>
        @if($maintenance->status === 'in_progress')
            {{-- Form to submit completion --}}
            <div class="panel" style="border: 2px solid #16b7a3; box-shadow: 0 10px 30px rgba(22, 183, 163, 0.1);">
                <div class="panel-heading" style="margin-bottom: 20px;">
                    <div>
                        <span class="eyebrow" style="color: var(--aqua);">RÉALISATION DES TRAVAUX</span>
                        <h2 style="font-size: 18px; color: #073f4a;">Compte-Rendu Technique d'Intervention</h2>
                        <p>Renseignez les constatations de terrain et soumettez l'intervention à la validation du gestionnaire.</p>
                    </div>
                    <span class="badge badge-info" style="font-size: 12px;">En cours</span>
                </div>

                <form method="POST" action="{{ route('technicien.interventions.complete', $maintenance) }}" id="completion-form">
                    @csrf

                    {{-- Work Performed (Required) --}}
                    <div style="margin-bottom: 18px;">
                        <label for="result" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #172c32;">
                            Compte-rendu des travaux réalisés <span style="color: #bd5149;">*</span>
                        </label>
                        <textarea id="result" name="result" rows="4" required placeholder="Décrivez en détail les opérations exécutées sur l'ouvrage (démontage, purge, nettoyage, réétalonnage, graissage...)" style="width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 12px; font-family: inherit; font-size: 14px;">{{ old('result', $maintenance->result) }}</textarea>
                        @error('result')
                            <small style="color: #bd5149; font-weight: 700;">{{ $message }}</small>
                        @enderror
                    </div>

                    {{-- Technical Diagnostics / Findings --}}
                    <div style="margin-bottom: 18px;">
                        <label for="findings" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #172c32;">
                            Constatations techniques & diagnostic d'usure
                        </label>
                        <textarea id="findings" name="findings" rows="2" placeholder="État des pièces mécaniques, corrosion observée, anomalie de pression ou débit..." style="width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 10px; font-family: inherit; font-size: 14px;">{{ old('findings', $maintenance->completion_report['findings'] ?? '') }}</textarea>
                    </div>

                    {{-- Grid for numbers and date --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                        <div>
                            <label for="duration_hours" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #172c32;">
                                Durée réelle passée (heures)
                            </label>
                            <input type="number" id="duration_hours" name="duration_hours" step="0.5" min="0.1" max="500" value="{{ old('duration_hours', $maintenance->duration_hours ?? 2) }}" style="width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 10px; font-size: 14px;">
                        </div>

                        <div>
                            <label for="completed_at" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #172c32;">
                                Date et heure de fin d'intervention
                            </label>
                            <input type="datetime-local" id="completed_at" name="completed_at" value="{{ old('completed_at', now()->format('Y-m-d\TH:i')) }}" style="width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 10px; font-size: 14px;">
                        </div>
                    </div>

                    {{-- Parts & Consumables Replaced --}}
                    <div style="margin-bottom: 18px;">
                        <label for="parts_replaced" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #172c32;">
                            Pièces, consommables ou composants remplacés
                        </label>
                        <input type="text" id="parts_replaced" name="parts_replaced" placeholder="Ex: Joint torique DN150, filtre à sable primaire, vanne papillon 2''..." value="{{ old('parts_replaced', $maintenance->completion_report['parts_replaced'] ?? '') }}" style="width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 10px; font-size: 14px;">
                    </div>

                    {{-- Tests and Verification --}}
                    <div style="margin-bottom: 18px;">
                        <label for="tests_verification" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #172c32;">
                            Tests de bon fonctionnement & mesures post-intervention
                        </label>
                        <textarea id="tests_verification" name="tests_verification" rows="2" placeholder="Ex: Essai de mise en pression à 4.2 bars conforme, mesure de débit nominal stabilisée, test d'étanchéité OK..." style="width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 10px; font-family: inherit; font-size: 14px;">{{ old('tests_verification', $maintenance->completion_report['tests_verification'] ?? '') }}</textarea>
                    </div>

                    {{-- Remaining issues & Recommendations --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                        <div>
                            <label for="remaining_issues" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #172c32;">
                                Points de vigilance résiduels
                            </label>
                            <textarea id="remaining_issues" name="remaining_issues" rows="2" placeholder="Ex: Légère vibration sur roulement moteur n°2 à surveiller..." style="width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 10px; font-size: 13px;">{{ old('remaining_issues', $maintenance->completion_report['remaining_issues'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label for="recommendations" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #172c32;">
                                Recommandations pour la suite
                            </label>
                            <textarea id="recommendations" name="recommendations" rows="2" placeholder="Ex: Prévoir renouvellement des crépines dans 6 mois..." style="width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 10px; font-size: 13px;">{{ old('recommendations', $maintenance->completion_report['recommendations'] ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <div style="border-top: 1px solid var(--line); padding-top: 20px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="button" style="background: #13886f; font-size: 15px; font-weight: 800; padding: 14px 28px; box-shadow: 0 4px 15px rgba(19, 136, 111, 0.3);">
                            ✓ Signaler l'intervention terminée
                        </button>
                    </div>
                </form>
            </div>
        @elseif($maintenance->status === 'awaiting_validation')
            {{-- Review State Card --}}
            <div class="panel" style="border: 2px solid #aa721c;">
                <div style="background: #fff9eb; border-left: 4px solid #aa721c; padding: 16px 20px; border-radius: 6px; margin-bottom: 24px;">
                    <div style="font-weight: 800; font-size: 16px; color: #784a05; margin-bottom: 4px;">
                        ⏳ En attente de validation par le gestionnaire
                    </div>
                    <p style="margin: 0; font-size: 14px; color: #784a05;">
                        Vous avez soumis votre compte-rendu technique le {{ $maintenance->completion_submitted_at?->format('d/m/Y à H:i') }}. Le gestionnaire étudie actuellement votre rapport pour validation définitive.
                    </p>
                </div>

                <h3 style="font-size: 16px; color: #073f4a; margin-top: 0;">Rapport soumis :</h3>
                <div style="background: #f7faf9; border: 1px solid #e5eeee; border-radius: 8px; padding: 20px; font-size: 14px;">
                    <div style="margin-bottom: 14px;">
                        <strong style="color: #708388; font-size: 12px; display: block; text-transform: uppercase;">Travaux effectués</strong>
                        <div style="color: #172c32; margin-top: 4px; white-space: pre-line;">{{ $maintenance->result }}</div>
                    </div>

                    @if(!empty($maintenance->completion_report['findings']))
                        <div style="margin-bottom: 14px;">
                            <strong style="color: #708388; font-size: 12px; display: block; text-transform: uppercase;">Constatations de terrain</strong>
                            <div style="color: #172c32; margin-top: 4px;">{{ $maintenance->completion_report['findings'] }}</div>
                        </div>
                    @endif

                    @if(!empty($maintenance->completion_report['parts_replaced']))
                        <div style="margin-bottom: 14px;">
                            <strong style="color: #708388; font-size: 12px; display: block; text-transform: uppercase;">Pièces remplacées</strong>
                            <div style="color: #172c32; margin-top: 4px;">{{ $maintenance->completion_report['parts_replaced'] }}</div>
                        </div>
                    @endif

                    @if(!empty($maintenance->completion_report['tests_verification']))
                        <div style="margin-bottom: 14px;">
                            <strong style="color: #708388; font-size: 12px; display: block; text-transform: uppercase;">Tests & Vérifications</strong>
                            <div style="color: #172c32; margin-top: 4px;">{{ $maintenance->completion_report['tests_verification'] }}</div>
                        </div>
                    @endif

                    <div style="display: flex; gap: 20px; border-top: 1px dashed #dce7e5; padding-top: 12px; margin-top: 14px; color: var(--muted); font-size: 13px;">
                        <span>Durée enregistrée : <strong>{{ $maintenance->duration_hours }} h</strong></span>
                        <span>Date de clôture : <strong>{{ $maintenance->completion_submitted_at?->format('d/m/Y H:i') }}</strong></span>
                    </div>
                </div>
            </div>
        @elseif($maintenance->status === 'completed')
            {{-- Completed & Validated Card --}}
            <div class="panel" style="border: 2px solid #13886f;">
                <div style="background: #def5ed; border-left: 4px solid #13886f; padding: 18px 24px; border-radius: 6px; margin-bottom: 24px;">
                    <div style="font-weight: 800; font-size: 16px; color: #13886f; margin-bottom: 4px;">
                        ✓ Opération validée et définitivement clôturée
                    </div>
                    <p style="margin: 0; font-size: 14px; color: #13886f;">
                        Validée le {{ $maintenance->completion_validated_at?->format('d/m/Y à H:i') }} par {{ $maintenance->validator?->name ?? 'Le gestionnaire' }}.
                    </p>
                    @if($maintenance->manager_validation_notes)
                        <div style="margin-top: 10px; padding: 10px 14px; background: #ffffff; border-radius: 4px; font-size: 13px; color: #172c32;">
                            <strong>Note du gestionnaire :</strong> {{ $maintenance->manager_validation_notes }}
                        </div>
                    @endif
                </div>

                <h3 style="font-size: 16px; color: #073f4a; margin-top: 0;">Rapport technique validé :</h3>
                <div style="background: #f7faf9; border: 1px solid #e5eeee; border-radius: 8px; padding: 20px; font-size: 14px;">
                    <div style="margin-bottom: 12px;">
                        <strong style="color: #708388; font-size: 12px; display: block; text-transform: uppercase;">Travaux réalisés</strong>
                        <div style="color: #172c32; margin-top: 4px; white-space: pre-line;">{{ $maintenance->result }}</div>
                    </div>
                    @if(!empty($maintenance->completion_report['tests_verification']))
                        <div>
                            <strong style="color: #708388; font-size: 12px; display: block; text-transform: uppercase;">Contrôles finaux</strong>
                            <div style="color: #172c32; margin-top: 4px;">{{ $maintenance->completion_report['tests_verification'] }}</div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="panel">
                <div style="background: #f7faf9; padding: 20px; border-radius: 6px; color: var(--muted); text-align: center;">
                    Cette intervention est actuellement au statut <strong>{{ $maintenance->display_status_label }}</strong>.
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
