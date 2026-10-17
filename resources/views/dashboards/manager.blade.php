@extends('layouts.back')

@section('content')
<style>
.mgr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.mgr-kpi-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    position: relative;
    overflow: hidden;
}
.mgr-kpi-card.alert-card {
    border-color: #f97316;
    background: #fffaf5;
}
.mgr-kpi-card.alert-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 4px;
    background: #ea580c;
}
.mgr-kpi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.mgr-kpi-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--muted);
}
.mgr-kpi-icon {
    font-size: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(0,0,0,0.04);
}
.mgr-kpi-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--ink);
    line-height: 1.1;
    margin-bottom: 4px;
}
.mgr-kpi-desc {
    font-size: 12px;
    color: var(--muted);
}

.mgr-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}
.mgr-section-title {
    font-size: 18px;
    font-weight: 800;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 10px;
}
.mgr-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    font-size: 11px;
    font-weight: 800;
    border-radius: 9999px;
    background: #ea580c;
    color: #fff;
}

/* Validation Card */
.validation-card {
    background: #fff;
    border: 1px solid #fed7aa;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(234, 88, 12, 0.08);
    transition: box-shadow 0.2s ease;
}
.validation-card:hover {
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.14);
}
.val-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    border-bottom: 1px solid #ffedd5;
    padding-bottom: 14px;
    margin-bottom: 16px;
}
.val-card-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    background: #fffaf5;
    border: 1px solid #ffedd5;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 16px;
    font-size: 13px;
}
.val-report-box {
    background: #fff;
    border: 1px dashed #fdba74;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 16px;
    font-size: 13px;
}
.val-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

/* Invitations Split Grid */
.invitations-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
@media (max-width: 960px) {
    .invitations-grid {
        grid-template-columns: 1fr;
    }
}
.invitation-item {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    transition: background 0.15s;
}
.invitation-item:hover {
    background: #fbfdfc;
}
</style>

<div class="content-heading">
    <div>
        <span class="eyebrow">SUPERVISION OPÉRATIONNELLE</span>
        <h1>Espace Gestionnaire — {{ Auth::user()->name }}</h1>
        <p>Validez les rapports des techniciens, confirmez les ordres de mission et pilotez l'activité en temps réel.</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a class="button button-outline" href="{{ route('maintenance-intelligence.index') }}">
            ✨ AI Maintenance Intelligence
        </a>
        <a class="button" href="{{ route('maintenances.index') }}">
            ⛯ Toutes les maintenances
        </a>
    </div>
</div>

{{-- Flash Messages --}}
@if(session('success'))
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
        <span style="font-size: 18px;">✓</span>
        <div>{{ session('success') }}</div>
    </div>
@endif

@if(session('info'))
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
        <span style="font-size: 18px;">ℹ</span>
        <div>{{ session('info') }}</div>
    </div>
@endif

@if(session('error'))
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
        <span style="font-size: 18px;">⚠</span>
        <div>{{ session('error') }}</div>
    </div>
@endif

{{-- 1. KPI Overview --}}
<div class="mgr-kpi-grid">
    <div class="mgr-kpi-card {{ $stats['awaiting_validation_count'] > 0 ? 'alert-card' : '' }}">
        <div class="mgr-kpi-header">
            <span class="mgr-kpi-title">À Valider (Urgents)</span>
            <span class="mgr-kpi-icon" style="background: #ffedd5; color: #ea580c;">⏳</span>
        </div>
        <div class="mgr-kpi-value" style="{{ $stats['awaiting_validation_count'] > 0 ? 'color: #ea580c;' : '' }}">
            {{ $stats['awaiting_validation_count'] }}
        </div>
        <div class="mgr-kpi-desc">
            @if($stats['awaiting_validation_count'] > 0)
                <strong style="color: #ea580c;">Action requise</strong> — Travaux terminés à approuver
            @else
                Aucun compte-rendu en attente
            @endif
        </div>
    </div>

    <div class="mgr-kpi-card">
        <div class="mgr-kpi-header">
            <span class="mgr-kpi-title">Invitations en cours</span>
            <span class="mgr-kpi-icon" style="background: #e0f2fe; color: #0284c7;">✉</span>
        </div>
        <div class="mgr-kpi-value" style="color: #0284c7;">
            {{ $stats['pending_invitations_count'] }}
        </div>
        <div class="mgr-kpi-desc">Réponses des techniciens en attente</div>
    </div>

    <div class="mgr-kpi-card">
        <div class="mgr-kpi-header">
            <span class="mgr-kpi-title">En cours d'exécution</span>
            <span class="mgr-kpi-icon" style="background: #ccfbf1; color: #0d9488;">⚙</span>
        </div>
        <div class="mgr-kpi-value" style="color: #0d9488;">
            {{ $stats['in_progress_count'] }}
        </div>
        <div class="mgr-kpi-desc">Techniciens actuellement sur site</div>
    </div>

    <div class="mgr-kpi-card">
        <div class="mgr-kpi-header">
            <span class="mgr-kpi-title">Validées ce mois</span>
            <span class="mgr-kpi-icon" style="background: #dcfce7; color: #16a34a;">✓</span>
        </div>
        <div class="mgr-kpi-value" style="color: #16a34a;">
            {{ $stats['completed_month_count'] }}
        </div>
        <div class="mgr-kpi-desc">Opérations clôturées et archivées</div>
    </div>
</div>

{{-- 2. SECTION PRIORITAIRE : Interventions en attente de validation managériale --}}
<section class="panel" id="section-validations" style="margin-bottom: 28px; border-left: 4px solid #ea580c;">
    <div class="mgr-section-header">
        <div>
            <div class="mgr-section-title">
                <span>⚡ Validation des Maintenances Terminées</span>
                @if($awaitingValidation->isNotEmpty())
                    <span class="mgr-count-badge">{{ $awaitingValidation->count() }}</span>
                @endif
            </div>
            <p style="margin: 4px 0 0 0; color: var(--muted); font-size: 13px;">
                Examinez les comptes-rendus techniques transmis par les techniciens pour valider et clôturer définitivement les dossiers.
            </p>
        </div>
    </div>

    @if($awaitingValidation->isEmpty())
        <div style="text-align: center; padding: 36px 20px; background: #fbfdfc; border-radius: 8px; border: 1px dashed var(--line);">
            <div style="font-size: 32px; margin-bottom: 8px;">✓</div>
            <h3 style="font-size: 16px; font-weight: 700; color: var(--ink); margin-bottom: 4px;">Toutes les opérations sont à jour</h3>
            <p style="color: var(--muted); font-size: 13px; max-width: 480px; margin: 0 auto;">
                Aucune intervention n'est actuellement en attente de validation. Les rapports soumis apparaîtront immédiatement ici.
            </p>
        </div>
    @else
        @foreach($awaitingValidation as $maintenance)
            <div class="validation-card">
                <div class="val-card-top">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                            <span style="font-size: 16px; font-weight: 800; color: #073f4a;">
                                {{ $maintenance->reference_code ?? ('MNT-#' . $maintenance->id) }}
                            </span>
                            @if($maintenance->priority === 'critical')
                                <span class="badge badge-danger">Critique</span>
                            @elseif($maintenance->priority === 'high')
                                <span class="badge badge-warning">Haute</span>
                            @elseif($maintenance->priority === 'medium')
                                <span class="badge badge-info">Moyenne</span>
                            @else
                                <span class="badge badge-gray">Basse</span>
                            @endif
                            <span style="font-size: 12px; font-weight: 600; color: var(--muted); background: #f3f4f6; padding: 2px 8px; border-radius: 4px;">
                                {{ $maintenance->type }}
                            </span>
                        </div>
                        <div style="font-size: 14px; font-weight: 700; color: var(--ink);">
                            Infrastructure : 
                            <a href="{{ route('infrastructures.show', $maintenance->infrastructure) }}" style="color: #0d9488; text-decoration: none;">
                                {{ $maintenance->infrastructure?->name ?? 'Infrastructure non spécifiée' }}
                            </a>
                            @if($maintenance->infrastructure?->zone)
                                <span style="color: var(--muted); font-weight: 400;">({{ $maintenance->infrastructure->zone->name }})</span>
                            @endif
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #ea580c; font-weight: 800; background: #fff7ed; padding: 4px 10px; border-radius: 9999px; border: 1px solid #ffedd5;">
                            ⏳ Soumis {{ $maintenance->completion_submitted_at?->diffForHumans() ?? 'récemment' }}
                        </span>
                        <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                            Le {{ $maintenance->completion_submitted_at?->format('d/m/Y à H:i') }}
                        </div>
                    </div>
                </div>

                {{-- Meta Information --}}
                <div class="val-card-meta">
                    <div>
                        <span style="color: var(--muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Technicien intervenant</span>
                        <strong style="color: var(--ink);">{{ $maintenance->technician?->name ?? 'Non assigné' }}</strong>
                        @if($maintenance->technician?->speciality)
                            <div style="color: var(--muted); font-size: 12px;">{{ $maintenance->technician->speciality }}</div>
                        @endif
                        @if($maintenance->technician?->email)
                            <div style="color: #0d9488; font-size: 11px;">{{ $maintenance->technician->email }}</div>
                        @endif
                    </div>
                    <div>
                        <span style="color: var(--muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Durée & Coût constatés</span>
                        <strong>{{ $maintenance->duration_hours ?? 0 }} heure(s)</strong>
                        <div style="color: var(--muted); font-size: 12px;">
                            Coût réel : {{ $maintenance->cost ? number_format($maintenance->cost, 2, ',', ' ') . ' €' : '0.00 €' }}
                        </div>
                    </div>
                    <div>
                        <span style="color: var(--muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Date de réalisation</span>
                        <strong>{{ $maintenance->started_at?->format('d/m/Y') ?? 'N/D' }}</strong>
                        <div style="color: var(--muted); font-size: 12px;">
                            Clôture terrain : {{ $maintenance->completion_submitted_at?->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </div>

                {{-- Rapport technique --}}
                <div class="val-report-box">
                    <div style="margin-bottom: 8px;">
                        <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #9a3412; display: block; margin-bottom: 2px;">
                            Résultat technique consigné :
                        </span>
                        <div style="color: var(--ink); font-weight: 600;">
                            {{ $maintenance->result ?: 'Aucun résumé de résultat fourni.' }}
                        </div>
                    </div>

                    @if(!empty($maintenance->completion_report['findings']))
                        <div style="margin-bottom: 8px; border-top: 1px dashed #fed7aa; padding-top: 6px;">
                            <span style="font-size: 11px; color: #708388; font-weight: 700; text-transform: uppercase;">Constatations de terrain :</span>
                            <div style="color: var(--ink);">{{ $maintenance->completion_report['findings'] }}</div>
                        </div>
                    @endif

                    @if(!empty($maintenance->completion_report['parts_replaced']))
                        <div style="margin-bottom: 8px; border-top: 1px dashed #fed7aa; padding-top: 6px;">
                            <span style="font-size: 11px; color: #708388; font-weight: 700; text-transform: uppercase;">Pièces remplacées :</span>
                            <div style="color: var(--ink);">{{ $maintenance->completion_report['parts_replaced'] }}</div>
                        </div>
                    @endif

                    @if(!empty($maintenance->completion_report['tests_verification']))
                        <div style="border-top: 1px dashed #fed7aa; padding-top: 6px;">
                            <span style="font-size: 11px; color: #708388; font-weight: 700; text-transform: uppercase;">Tests & Contrôles effectués :</span>
                            <div style="color: var(--ink);">{{ $maintenance->completion_report['tests_verification'] }}</div>
                        </div>
                    @endif
                </div>

                {{-- Actions de validation et de correction --}}
                <div class="val-actions-bar">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        {{-- Bouton Valider et Clôturer --}}
                        <form method="POST" action="{{ route('maintenances.validate-completion', $maintenance) }}" style="margin: 0;">
                            @csrf
                            <input type="hidden" name="redirect_to" value="dashboard">
                            <button type="submit" 
                                    class="button" 
                                    style="background: #13886f; font-weight: 800; padding: 10px 20px; box-shadow: 0 2px 8px rgba(19, 136, 111, 0.25);"
                                    onclick="return confirm('Confirmez-vous la validation définitive de cette intervention ? Un email de clôture Brevo sera automatiquement expédié au technicien.')">
                                ✓ Valider et clôturer l'intervention
                            </button>
                        </form>

                        {{-- Bouton Demander des corrections --}}
                        <button type="button" 
                                class="button button-outline" 
                                onclick="toggleCorrectionsBox('corr-box-{{ $maintenance->id }}')" 
                                style="border-color: #ea580c; color: #ea580c; padding: 10px 18px; font-weight: 700;">
                            💬 Demander des corrections
                        </button>
                    </div>

                    <a href="{{ route('maintenances.show', $maintenance) }}" class="button button-ghost" style="font-size: 13px;">
                        Consulter le dossier complet →
                    </a>
                </div>

                {{-- Bloc déroulant pour demande de corrections --}}
                <div id="corr-box-{{ $maintenance->id }}" style="display: none; margin-top: 16px; background: #fffaf5; border: 1px solid #fed7aa; border-radius: 8px; padding: 16px;">
                    <form method="POST" action="{{ route('maintenances.request-corrections', $maintenance) }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="dashboard">
                        <label for="corr-notes-{{ $maintenance->id }}" style="display: block; font-size: 13px; font-weight: 700; color: #9a3412; margin-bottom: 6px;">
                            Indiquez les compléments, mesures ou pièces à préciser par {{ $maintenance->technician?->name ?? 'le technicien' }} :
                        </label>
                        <textarea id="corr-notes-{{ $maintenance->id }}" 
                                  name="correction_notes" 
                                  rows="3" 
                                  required 
                                  placeholder="Ex: Merci de renseigner la pression exacte relevée en amont du réducteur après remise en eau..."
                                  style="width: 100%; border: 1px solid #fdba74; border-radius: 6px; padding: 10px; font-size: 13px; font-family: inherit; margin-bottom: 10px;"></textarea>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="button" style="background: #ea580c; padding: 8px 16px; font-size: 13px;">
                                Transmettre la demande de corrections (Notification Brevo)
                            </button>
                            <button type="button" class="button button-ghost" onclick="toggleCorrectionsBox('corr-box-{{ $maintenance->id }}')" style="font-size: 13px;">
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</section>

{{-- 3. SECTION INVITATIONS & CONFIRMATIONS TECHNICIENS --}}
<div class="invitations-grid" id="section-invitations">
    {{-- Colonne 1 : Invitations en attente d'acceptation --}}
    <section class="panel" style="margin-bottom: 0;">
        <div class="panel-heading" style="margin-bottom: 14px;">
            <div>
                <h2 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <span>✉ Invitations en Attente</span>
                    @if($pendingInvitations->isNotEmpty())
                        <span class="badge badge-info" style="font-size: 11px;">{{ $pendingInvitations->count() }}</span>
                    @endif
                </h2>
                <p style="font-size: 12px; color: var(--muted);">Ordres de mission envoyés par Brevo, en attente de réponse du technicien.</p>
            </div>
        </div>

        @if($pendingInvitations->isEmpty())
            <div style="padding: 24px; text-align: center; color: var(--muted); font-size: 13px; background: #fbfdfc; border-radius: 6px; border: 1px dashed var(--line);">
                Aucune invitation en attente. Tous les techniciens assignés ont répondu.
            </div>
        @else
            @foreach($pendingInvitations as $invitation)
                <div class="invitation-item">
                    <div style="flex: 1 1 auto;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <strong style="color: #073f4a; font-size: 13px;">
                                {{ $invitation->maintenance?->reference_code ?? ('MNT-#' . $invitation->maintenance_id) }}
                            </strong>
                            <span class="badge badge-warning" style="font-size: 10px;">En attente</span>
                            @if($invitation->email_delivery_status === 'sent')
                                <span style="font-size: 10px; color: #16a34a; font-weight: 700;">✓ Email Brevo délivré</span>
                            @else
                                <span style="font-size: 10px; color: #dc2626; font-weight: 700;">⚠ Échec envoi</span>
                            @endif
                        </div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--ink);">
                            {{ $invitation->technician?->name ?? 'Technicien' }}
                            <span style="font-weight: 400; color: var(--muted); font-size: 12px;">({{ $invitation->technician?->speciality ?? 'Généraliste' }})</span>
                        </div>
                        <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                            Infra : {{ $invitation->maintenance?->infrastructure?->name ?? 'N/D' }}
                            • Expire {{ $invitation->expires_at?->diffForHumans() ?? 'prochainement' }}
                        </div>
                    </div>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <form method="POST" action="{{ route('maintenances.resend-invitation', $invitation->maintenance) }}">
                            @csrf
                            <input type="hidden" name="redirect_to" value="dashboard">
                            <button type="submit" class="button button-outline" style="padding: 6px 12px; font-size: 11px;" title="Renvoyer l'email d'invitation au technicien via Brevo">
                                ↺ Renvoyer
                            </button>
                        </form>
                        <a href="{{ route('maintenances.show', $invitation->maintenance) }}" class="button button-ghost" style="padding: 6px 10px; font-size: 11px;">
                            Voir
                        </a>
                    </div>
                </div>
            @endforeach
        @endif
    </section>

    {{-- Colonne 2 : Dernières Confirmations & Réponses Techniciens --}}
    <section class="panel" style="margin-bottom: 0;">
        <div class="panel-heading" style="margin-bottom: 14px;">
            <div>
                <h2 style="font-size: 16px;">✓ Confirmations & Activité Récente</h2>
                <p style="font-size: 12px; color: var(--muted);">Dernières réponses des techniciens aux sollicitations.</p>
            </div>
        </div>

        @if($recentInvitations->isEmpty())
            <div style="padding: 24px; text-align: center; color: var(--muted); font-size: 13px; background: #fbfdfc; border-radius: 6px; border: 1px dashed var(--line);">
                Aucune confirmation récente enregistrée.
            </div>
        @else
            @foreach($recentInvitations as $invitation)
                <div class="invitation-item">
                    <div style="flex: 1 1 auto;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <strong style="color: #073f4a; font-size: 13px;">
                                {{ $invitation->maintenance?->reference_code ?? ('MNT-#' . $invitation->maintenance_id) }}
                            </strong>
                            @if($invitation->status === 'accepted')
                                <span class="badge badge-success" style="font-size: 10px;">✓ Confirmée (Verrouillée)</span>
                            @elseif($invitation->status === 'declined')
                                <span class="badge badge-danger" style="font-size: 10px;">✕ Refusée</span>
                            @else
                                <span class="badge badge-gray" style="font-size: 10px;">{{ ucfirst($invitation->status) }}</span>
                            @endif
                        </div>
                        <div style="font-size: 13px; color: var(--ink);">
                            <strong>{{ $invitation->technician?->name ?? 'Technicien' }}</strong>
                            @if($invitation->status === 'accepted')
                                <span style="font-size: 12px; color: #16a34a;">a accepté la mission le {{ $invitation->responded_at?->format('d/m/Y à H:i') }}</span>
                            @elseif($invitation->status === 'declined')
                                <div style="color: #dc2626; font-size: 11px; margin-top: 2px;">
                                    Motif : « {{ $invitation->decline_reason ?: 'Non précisé' }} »
                                </div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('maintenances.show', $invitation->maintenance) }}" class="button button-ghost" style="padding: 6px 10px; font-size: 11px;">
                            Détails →
                        </a>
                    </div>
                </div>
            @endforeach
        @endif
    </section>
</div>

{{-- 4. SECTION RÉCAPITULATIVE : Maintenances En Cours et Clôturées Récemment --}}
<div class="invitations-grid" style="margin-top: 24px;">
    {{-- En cours --}}
    <section class="panel">
        <div class="panel-heading" style="margin-bottom: 12px;">
            <div>
                <h2 style="font-size: 16px;">⚙ Interventions En Cours de Réalisation</h2>
                <p style="font-size: 12px; color: var(--muted);">Chantiers actuellement pris en charge sur le réseau hydraulique.</p>
            </div>
            <a href="{{ route('maintenances.index', ['status' => 'in_progress']) }}" style="font-size: 12px; color: #0d9488; text-decoration: none; font-weight: 600;">
                Voir tout →
            </a>
        </div>

        @if($inProgressMaintenances->isEmpty())
            <div style="padding: 20px; text-align: center; color: var(--muted); font-size: 13px;">
                Aucune intervention en cours d'exécution.
            </div>
        @else
            @foreach($inProgressMaintenances as $m)
                <div class="invitation-item">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="color: #073f4a; font-size: 13px;">{{ $m->reference_code }}</strong>
                            <span class="badge badge-info" style="font-size: 10px;">En cours</span>
                        </div>
                        <div style="font-size: 12px; color: var(--ink); margin-top: 2px;">
                            {{ $m->infrastructure?->name }} • Technicien : <strong>{{ $m->technician?->name ?? 'N/D' }}</strong>
                        </div>
                        <div style="font-size: 11px; color: var(--muted);">
                            Démarrée le {{ $m->started_at?->format('d/m/Y à H:i') ?? 'N/D' }}
                        </div>
                    </div>
                    <a href="{{ route('maintenances.show', $m) }}" class="button button-ghost" style="padding: 6px 10px; font-size: 11px;">
                        Suivre
                    </a>
                </div>
            @endforeach
        @endif
    </section>

    {{-- Récemment validées et clôturées --}}
    <section class="panel">
        <div class="panel-heading" style="margin-bottom: 12px;">
            <div>
                <h2 style="font-size: 16px;">✓ Récemment Validées & Clôturées</h2>
                <p style="font-size: 12px; color: var(--muted);">Dossiers approuvés par la direction et notifiés par Brevo.</p>
            </div>
            <a href="{{ route('maintenances.index', ['status' => 'completed']) }}" style="font-size: 12px; color: #16a34a; text-decoration: none; font-weight: 600;">
                Archives →
            </a>
        </div>

        @if($recentlyValidated->isEmpty())
            <div style="padding: 20px; text-align: center; color: var(--muted); font-size: 13px;">
                Aucune intervention clôturée récemment.
            </div>
        @else
            @foreach($recentlyValidated as $m)
                <div class="invitation-item">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <strong style="color: #073f4a; font-size: 13px;">{{ $m->reference_code }}</strong>
                            <span class="badge badge-success" style="font-size: 10px;">✓ Clôturée</span>
                        </div>
                        <div style="font-size: 12px; color: var(--ink); margin-top: 2px;">
                            {{ $m->infrastructure?->name }} • Réalisée par <strong>{{ $m->technician?->name ?? 'N/D' }}</strong>
                        </div>
                        <div style="font-size: 11px; color: var(--muted);">
                            Validée le {{ $m->completion_validated_at?->format('d/m/Y H:i') ?? $m->completed_at?->format('d/m/Y') }}
                            @if($m->validator)
                                par {{ $m->validator->name }}
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('maintenances.show', $m) }}" class="button button-ghost" style="padding: 6px 10px; font-size: 11px;">
                        Consulter
                    </a>
                </div>
            @endforeach
        @endif
    </section>
</div>

<script>
function toggleCorrectionsBox(boxId) {
    const box = document.getElementById(boxId);
    if (!box) return;
    if (box.style.display === 'none' || box.style.display === '') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}
</script>
@endsection
