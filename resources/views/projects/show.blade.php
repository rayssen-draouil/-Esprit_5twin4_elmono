@extends('layouts.back')
@section('content')
<div class="content-heading"><div><span class="eyebrow">DÉTAIL DU PROJET</span><h1>{{ $project->name }}</h1><p>{{ $project->type }} · {{ str_replace('_', ' ', ucfirst($project->status)) }}</p><p>Responsable : {{ $project->user?->name ?? 'Non attribué' }}</p></div><div class="actions"><a class="button button-ghost" href="{{ route('projects.edit', $project) }}">Modifier</a><a class="button" href="{{ route('financements.create', ['project_id' => $project->id]) }}">+ Ajouter un financement</a></div></div>

<!-- Statut de validation -->
<section class="panel validation-panel">
    <div class="panel-heading"><div><span class="eyebrow">VALIDATION ADMINISTRATIVE</span><h2>Statut de validation</h2></div></div>
    <div class="detail-meta detail-meta-wide">
        <div><span>Statut de validation</span><strong>
            <span class="badge @php
                $badge = match($project->validation_status) {
                    'draft' => 'badge-info',
                    'pending_approval' => 'badge-warning',
                    'changes_requested' => 'badge-warning',
                    'approved' => 'badge-success',
                    'rejected' => 'badge-danger',
                    default => 'badge-info',
                };
            @endphp {{ $badge }}">{{ str_replace('_', ' ', ucfirst($project->validation_status)) }}</span>
        </div>
        @if($project->validation_comment)
            <div class="form-full"><span>Commentaire</span><p>{{ $project->validation_comment }}</p></div>
        @endif
        @if($project->validated_at)
            <div><span>Validé par</span><strong>{{ $project->validator?->name ?? '—' }}</strong></div>
            <div><span>Date de décision</span><strong>{{ $project->validated_at->format('d/m/Y H:i') }}</strong></div>
        @endif
    </div>

    <!-- Boutons d'action -->
    <div class="form-actions">
        @if(Auth::user()->role === 'gestionnaire' || Auth::user()->role === 'manager')
            @if($project->validation_status === 'draft' || $project->validation_status === 'changes_requested')
                <form method="POST" action="{{ route('projects.submit', $project) }}">
                    @csrf
                    <button class="button" type="submit">Soumettre pour validation</button>
                </form>
            @endif
        @endif

        @if(Auth::user()->role === 'admin' && $project->validation_status === 'pending_approval')
            <form method="POST" action="{{ route('projects.decide', $project) }}" style="display:inline;">
                @csrf
                <select name="action" class="input-small">
                    <option value="approve">Approuver</option>
                    <option value="request_changes">Demander corrections</option>
                    <option value="reject">Rejeter</option>
                </select>
                <label>Commentaire <input name="comment" placeholder="Obligatoire pour rejet/corrections"></label>
                <button class="button" type="submit">Décider</button>
            </form>
        @endif
    </div>
</section>

<div class="detail-grid"><section class="panel"><span class="eyebrow">PRÉSENTATION</span><p class="detail-description">{{ $project->description ?: 'Aucune description renseignée.' }}</p><div class="detail-meta"><div><span>Budget</span><strong>{{ number_format($project->budget, 2, ',', ' ') }} €</strong></div><div><span>Date de début</span><strong>{{ $project->start_date?->format('d/m/Y') ?? '—' }}</strong></div><div><span>Date de fin</span><strong>{{ $project->end_date?->format('d/m/Y') ?? '—' }}</strong></div><div><span>Progression</span><strong>{{ $project->progress }}%</strong></div></div><div class="progress progress-large"><i style="width:{{ $project->progress }}%"></i></div></section><section class="panel funding-summary"><span class="eyebrow">FINANCEMENT</span><div><span>Total financé</span><strong>{{ number_format($funded, 2, ',', ' ') }} €</strong></div><div><span>Reste à financer</span><strong>{{ number_format($remaining, 2, ',', ' ') }} €</strong></div><div><span>Taux de financement</span><strong>{{ $fundingRate }}%</strong></div>@if($overBudget)<p class="flash-message flash-warning">Financement supérieur au budget.</p>@endif</section></div>

<section class="panel table-wrap"><div class="panel-heading"><div><span class="eyebrow">RESSOURCES</span><h2>Financements associés</h2></div><a href="{{ route('financements.create', ['project_id' => $project->id]) }}">Ajouter ↗</a></div><table><thead><tr><th>Source</th><th>Montant</th><th>Statut</th><th>Date</th><th></th></tr></thead><tbody>@forelse($project->financements as $financement)<tr><td>{{ $financement->source }}</td><td>{{ number_format($financement->amount, 2, ',', ' ') }} €</td><td><span class="badge badge-info">{{ str_replace('_', ' ', ucfirst($financement->status)) }}</span></td><td>{{ $financement->funded_at?->format('d/m/Y') ?? '—' }}</td><td><a href="{{ route('financements.show', $financement) }}">Voir</a></td></tr>@empty<tr><td colspan="5">Aucun financement associé.</td></tr>@endforelse</tbody></table></section>

<!-- Historique des décisions -->
@if($project->decisionHistories->count() > 0)
<section class="panel"><span class="eyebrow">HISTORIQUE DES DÉCISIONS</span><h2>Traçabilité des validations</h2><table><thead><tr><th>Date</th><th>Action</th><th>Ancien statut</th><th>Nouveau statut</th><th>Commentaire</th><th>Par</th></tr></thead><tbody>@foreach($project->decisionHistories as $history)<tr><td>{{ $history->created_at->format('d/m/Y H:i') }}</td><td>{{ str_replace('_', ' ', ucfirst($history->action_type)) }}</td><td>{{ $history->old_status ?? '—' }}</td><td>{{ str_replace('_', ' ', ucfirst($history->new_status)) }}</td><td>{{ $history->comment ?? '—' }}</td><td>{{ $history->user->name }}</td></tr>@endforeach</tbody></table></section>
@endif

<form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce projet ?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Supprimer le projet</button></form>
<x-project-doctor :focusProjectId="$project->id" />
@endsection
