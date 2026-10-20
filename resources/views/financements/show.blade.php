@extends('layouts.back')

@push('styles')
<style>
    :root {
        --premium-deep: #073f4a;
        --premium-aqua: #16b7a3;
        --premium-success: #177853;
        --premium-danger: #a8473d;
        --premium-warning: #9a6814;
        --premium-info: #23638c;
        --premium-aqua-light: rgba(22, 183, 163, 0.15);
        --premium-success-light: rgba(23, 120, 83, 0.15);
        --premium-danger-light: rgba(168, 71, 61, 0.15);
        --premium-warning-light: rgba(154, 104, 20, 0.15);
        --premium-info-light: rgba(35, 99, 140, 0.15);
        --premium-shadow: 0 4px 14px rgba(7, 63, 74, 0.2);
        --premium-shadow-hover: 0 8px 24px rgba(7, 63, 74, 0.3);
        --line: #e2e8f0;
        --ink: #0f172a;
        --muted: #64748b;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 24px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        white-space: nowrap;
    }
    .badge-success { background: var(--premium-success-light); color: var(--premium-success); }
    .badge-info { background: var(--premium-info-light); color: var(--premium-info); }
    .badge-warning { background: var(--premium-warning-light); color: var(--premium-warning); }
    .badge-danger { background: var(--premium-danger-light); color: var(--premium-danger); }

    .button {
        background: linear-gradient(135deg, var(--premium-deep), #0a5f6e);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 800;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: var(--premium-shadow);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }
    .button:hover {
        background: linear-gradient(135deg, #0a5f6e, var(--premium-deep));
        transform: translateY(-2px);
        box-shadow: var(--premium-shadow-hover);
    }
    .button-ghost {
        background: transparent;
        color: var(--premium-deep);
        border: 1px solid var(--premium-deep);
        box-shadow: none;
    }
    .button-ghost:hover {
        background: var(--premium-deep);
        color: #fff;
    }

    .button-submit {
        background: linear-gradient(135deg, var(--premium-aqua), #0f9e8c);
        color: #fff;
    }
    .button-submit:hover {
        background: linear-gradient(135deg, #0f9e8c, var(--premium-aqua));
    }
    .button-decide {
        background: linear-gradient(135deg, var(--premium-deep), #0a5f6e);
        color: #fff;
    }
    .button-decide.button-approve { background: linear-gradient(135deg, var(--premium-success), #1d9d6b); }
    .button-decide.button-reject { background: linear-gradient(135deg, var(--premium-danger), #c95c50); }
    .button-decide.button-request-changes { background: linear-gradient(135deg, var(--premium-warning), #b37a1a); }
    .button-decide:hover {
        background: linear-gradient(135deg, #0a5f6e, var(--premium-deep));
        transform: translateY(-2px);
        box-shadow: var(--premium-shadow-hover);
    }

    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-top: 16px;
        padding: 20px;
        background: linear-gradient(135deg, #fff 0%, #f8faf9 100%);
        border: 1px solid var(--line);
        border-radius: 12px;
    }

    .decision-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .decision-form label {
        font-size: 12px;
        font-weight: 700;
        color: var(--muted);
        margin-bottom: 4px;
        display: block;
    }

    .decision-form .input-small,
    .decision-form input[type="text"],
    .decision-form input[name="comment"] {
        padding: 10px 14px;
        border: 1px solid var(--line);
        border-radius: 8px;
        font-size: 13px;
        color: var(--ink);
        background: #fff;
        min-width: 140px;
        transition: all 0.25s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }
    .decision-form .input-small:focus,
    .decision-form input[name="comment"]:focus {
        outline: none;
        border-color: var(--premium-aqua);
        box-shadow: 0 0 0 3px var(--premium-aqua-light);
    }

    .panel {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 12px 40px rgba(7, 63, 74, 0.08);
    }

    .panel-heading {
        background: linear-gradient(135deg, #fff 0%, #f8faf9 100%);
        padding: 20px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>
@endpush
@section('content')
<div class="content-heading"><div><span class="eyebrow">DÉTAIL DU FINANCEMENT</span><h1>{{ $financement->source }}</h1><p>Ressource associée au projet {{ $financement->project->name }}</p></div><div class="actions"><a class="button button-ghost" href="{{ route('financements.edit', $financement) }}">Modifier</a><a class="button" href="{{ route('projects.show', $financement->project) }}">Voir le projet</a></div></div>

<!-- Statut de validation -->
<section class="panel validation-panel">
    <div class="panel-heading"><div><span class="eyebrow">VALIDATION ADMINISTRATIVE</span><h2>Statut de validation</h2></div></div>
    <div class="detail-meta detail-meta-wide">
        <div><span>Projet</span><strong>{{ $financement->project->name }}</strong></div>
        <div><span>Statut de validation</span><strong>
            <span class="badge @php
                $badge = match($financement->validation_status) {
                    'draft' => 'badge-info',
                    'pending_approval' => 'badge-warning',
                    'changes_requested' => 'badge-warning',
                    'approved' => 'badge-success',
                    'rejected' => 'badge-danger',
                    default => 'badge-info',
                };
            @endphp {{ $badge }}">{{ str_replace('_', ' ', ucfirst($financement->validation_status)) }}</span>
        </div>
        @if($financement->validation_comment)
            <div class="form-full"><span>Commentaire</span><p>{{ $financement->validation_comment }}</p></div>
        @endif
        @if($financement->validated_at)
            <div><span>Validé par</span><strong>{{ $financement->validator?->name ?? '—' }}</strong></div>
            <div><span>Date de décision</span><strong>{{ $financement->validated_at->format('d/m/Y H:i') }}</strong></div>
        @endif
    </div>

    <!-- Analyse financière de couverture -->
    <section class="panel validation-panel">
        <div class="panel-heading"><div><span class="eyebrow">ANALYSE FINANCIÈRE</span><h2>Couverture du projet</h2></div></div>
        <div class="detail-meta detail-meta-wide">
            <div><span>Budget du projet</span><strong>{{ number_format($coverage['budget'], 0, ',', ' ') }} €{{ !$coverage['is_budget_valid'] ? ' (budget invalide)' : '' }}</strong></div>
            <div><span>Financements confirmés (hors demande)</span><strong>{{ number_format($coverage['confirmed_others'], 0, ',', ' ') }} €</strong></div>
            <div><span>Montant de la demande</span><strong>{{ number_format($coverage['request_amount'], 0, ',', ' ') }} €</strong></div>
            <div><span>Total prévisionnel après approbation</span><strong>{{ number_format($coverage['projected_total'], 0, ',', ' ') }} €</strong></div>
            <div><span>Taux de couverture</span><strong class="@php echo $coverage['coverage_rate'] === null ? 'text-danger' : 'text-info'; @endphp">{{ $coverage['coverage_rate'] !== null ? number_format($coverage['coverage_rate'], 2) . ' %' : 'N/A' }}</strong></div>
            <div><span>Montant restant à financer</span><strong>{{ number_format($coverage['remaining_to_fund'], 0, ',', ' ') }} €</strong></div>
        </div>
        @if(Auth::user()->role === 'admin')
        <div class="form-full">
            <span>Niveau d'alerte</span>
            <span class="badge @php
                $badge = match($coverage['alert_level']) {
                    'rouge' => 'badge-danger',
                    'orange' => 'badge-warning',
                    'bleu' => 'badge-info',
                    'vert' => 'badge-success',
                    default => 'badge-info',
                };
            @endphp {{ $badge }}">{{ $coverage['alert_label'] }}</span>
        </div>
        <div class="form-full alert {{ $coverage['is_overfunded'] ? 'alert-warning' : 'alert-info' }}">
            {{ $coverage['alert_message'] }}
        </div>
        @if($coverage['is_overfunded'])
            <div class="form-full alert alert-warning">
                ⚠ Surfinancement : le total prévisionnel ({{ number_format($coverage['projected_total'], 0, ',', ' ') }} €) dépasse le budget du projet ({{ number_format($coverage['budget'], 0, ',', ' ') }} €).
            </div>
        @endif
        @endif
    </section>

    <!-- Boutons d'action -->
    <div class="form-actions">
        @if(Auth::user()->role === 'admin' && $financement->validation_status === 'pending_approval' && $financement->resubmitted_after_changes_requested)
            <div class="alert alert-info" style="width:100%;">
                Ce financement a été resoumis avec modifications après une demande de corrections.
            </div>
        @endif

        @if(Auth::user()->role === 'gestionnaire' || Auth::user()->role === 'manager')
            @if($financement->validation_status === 'draft' || $financement->validation_status === 'changes_requested')
                <form method="POST" action="{{ route('financements.submit', $financement) }}">
                    @csrf
                    <button class="button button-submit" type="submit">Soumettre pour validation</button>
                </form>
            @endif
        @endif

        @if(Auth::user()->role === 'admin' && $financement->validation_status === 'pending_approval')
            <form method="POST" action="{{ route('financements.decide', $financement) }}" class="decision-form">
                @csrf
                <label for="action">Décision</label>
                <select name="action" id="action" class="input-small">
                    <option value="approve">Approuver</option>
                    <option value="request_changes">Demander corrections</option>
                    <option value="reject">Rejeter</option>
                </select>
                <label for="comment">Commentaire</label>
                <input name="comment" id="comment" type="text" placeholder="Obligatoire pour rejet/corrections">
                <button class="button button-decide" type="submit">Décider</button>
            </form>
        @endif
    </div>
</section>

<section class="panel detail-meta detail-meta-wide"><div><span>Enregistré par</span><strong>{{ $financement->user?->name ?? 'Non attribué' }}</strong></div><div><span>Source</span><strong>{{ $financement->source }}</strong></div><div><span>Montant</span><strong>{{ number_format($financement->amount, 2, ',', ' ') }} €</strong></div><div><span>Statut métier</span><strong>{{ str_replace('_', ' ', ucfirst($financement->status)) }}</strong></div><div><span>Date de financement</span><strong>{{ $financement->funded_at?->format('d/m/Y') ?? '—' }}</strong></div></section>

<!-- Historique des décisions -->
@if($financement->decisionHistories->count() > 0)
<section class="panel"><span class="eyebrow">HISTORIQUE DES DÉCISIONS</span><h2>Traçabilité des validations</h2><table><thead><tr><th>Date</th><th>Action</th><th>Ancien statut</th><th>Nouveau statut</th><th>Commentaire</th><th>Par</th></tr></thead><tbody>@foreach($financement->decisionHistories as $history)<tr><td>{{ $history->created_at->format('d/m/Y H:i') }}</td><td>{{ str_replace('_', ' ', ucfirst($history->action_type)) }}</td><td>{{ $history->old_status ?? '—' }}</td><td>{{ str_replace('_', ' ', ucfirst($history->new_status)) }}</td><td>{{ $history->comment ?? '—' }}</td><td>{{ $history->user->name }}</td></tr>@endforeach</tbody></table></section>
@endif

<form method="POST" action="{{ route('financements.destroy', $financement) }}" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce financement ?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Supprimer le financement</button></form>
@endsection
