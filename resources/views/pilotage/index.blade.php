@extends('layouts.back')

@section('content')
<div class="pilotage-container">
    <div class="content-heading">
        <div><span class="eyebrow">CENTRE DE PILOTAGE</span><h1>Statistiques Projets &amp; Financements</h1><p>Données réelles en provenance de la base de données AquaSecure.</p></div>
</div>

<!-- Statut vide -->
@if ($totalProjects === 0 && $totalFinancements === 0)
    <section class="panel">
        <div class="panel-heading"><div><span class="eyebrow">ÉTAT VIDE</span><h2>Aucune donnée disponible</h2></div></div>
        <div style="padding:40px;text-align:center;color:var(--muted);">
            <p>Aucun projet ni financement n'existe encore dans la base de données.</p>
            <p>Créez d'abord des projets et des demandes de financement pour visualiser les statistiques.</p>
            <div class="actions" style="margin-top:20px;">
                <a href="{{ route('projects.index') }}" class="button">Créer un projet</a>
                <a href="{{ route('financements.index') }}" class="button button-ghost">Créer un financement</a>
            </div>
        </div>
    </section>
@else

    <!-- KPI Grid principal -->
    <div class="kpi-grid">
        <div class="kpi"><span>Total projets</span><strong>{{ $totalProjects }}</strong></div>
        <div class="kpi"><span>En attente</span><strong>{{ $projectsPending }}</strong></div>
        <div class="kpi"><span>Approuvés</span><strong>{{ $projectsApproved }}</strong></div>
        <div class="kpi"><span>Rejetés</span><strong>{{ $projectsRejected }}</strong></div>
        <div class="kpi"><span>Total financements</span><strong>{{ $totalFinancements }}</strong></div>
        <div class="kpi"><span>En attente</span><strong>{{ $financementsPending }}</strong></div>
        <div class="kpi"><span>Approuvés</span><strong>{{ $financementsApproved }}</strong></div>
        <div class="kpi"><span>Rejetés</span><strong>{{ $financementsRejected }}</strong></div>
        <div class="kpi"><span>Sous-financés</span><strong class="text-warning">{{ $underfundedProjectsCount }}</strong></div>
        <div class="kpi"><span>Bloqués</span><strong class="text-warning">{{ $blockedProjectsCount }}</strong></div>
        <div class="kpi"><span>Budget total</span><strong>{{ number_format($budgetTotal, 0, ',', ' ') }} €</strong></div>
        <div class="kpi"><span>Financements confirmés</span><strong>{{ number_format($confirmedFundingTotal, 0, ',', ' ') }} €</strong></div>
        <div class="kpi"><span>Reste à financer</span><strong>{{ number_format($remainingToFund, 0, ',', ' ') }} €</strong></div>
        <div class="kpi"><span>Taux de couverture</span><strong>{{ $globalCoverageRate ?? 0 }} %</strong></div>
    </div>

    <!-- Validations prioritaires -->
    <section class="panel alert-panel">
        <div class="panel-heading"><div><span class="eyebrow">VALIDATIONS PRIORITAIRES</span><h2>Demandes en attente de décision</h2><p>{{ count($pendingValidations) }} demande(s) en attente.</p></div><a href="{{ route('projects.index') }}">Tous les projets ↗</a> <a href="{{ route('financements.index') }}">Tous les financements ↗</a></div>
        <div class="alert-list">
            @forelse ($pendingValidations as $item)
                <div class="alert-row">
                    <span class="incident-mark warning">↗</span>
                    <div>
                        <strong>{{ $item['type'] === 'projet' ? 'Projet' : 'Financement' }} : {{ $item['title'] }}</strong>
                        <small>{{ date('d/m Y H:i', strtotime($item['submitted_at'])) }} · <a href="{{ $item['url'] }}">Voir détails ↗</a></small>
                    </div>
                    @if ($item['submitted_at'] < now()->subDays(7))
                        <b class="soft">Ancien &gt;7j</b>
                    @endif
                </div>
            @empty
                <p>Aucune demande en attente.</p>
            @endforelse
        </div>
    </section>

    <!-- Alertes financières -->
    <section class="panel alert-panel">
        <div class="panel-heading"><div><span class="eyebrow">ALERTES FINANCIÈRES</span><h2>Projets sous-financés ou en dépassement budgétaire</h2><p>{{ count($financialAlerts) }} alerte(s) détectée(s).</p></div></div>
        <div class="alert-list">
            @forelse ($financialAlerts as $alert)
                <div class="alert-row">
                    <span class="incident-mark {{ $alert['type'] === 'budget_exceeded' ? 'danger' : 'warning' }}">!</span>
                    <div>
                        <strong>{{ $alert['title'] }}</strong>
                        <small>{{ $alert['description'] }}</small>
                    </div>
                    @if ($alert['url'])
                        <a href="{{ $alert['url'] }}" class="button button-sm button-ghost">Voir</a>
                    @endif
                </div>
            @empty
                <p>Aucune alerte financière détectée.</p>
            @endforelse
        </div>
    </section>

    <!-- Activité récente -->
    <section class="panel">
        <div class="panel-heading"><div><span class="eyebrow">ACTIVITÉ RÉCENTE</span><h2>Dernières décisions de validation</h2></div><a href="{{ route('back.incidents') }}">Tout voir ↗</a></div>
        <ul class="activity-list">
            @forelse ($recentDecisions as $decision)
                <li><span class="activity-dot {{ $decision->action_type === 'approve' ? 'success' : ($decision->action_type === 'reject' ? 'danger' : 'info') }}"></span><div><strong>{{ $decision->action_type }}</strong> sur {{ $decision->resource_type }} par {{ $decision->user->name ?? 'Système' }}<small>{{ $decision->created_at->format('d/m Y H:i') }}</small></div></li>
            @empty
                <li>Aucune activité récente.</li>
            @endforelse
        </ul>
    </section>

    <!-- Notifications non lues -->
    <section class="panel">
        <div class="panel-heading"><div><span class="eyebrow">NOTIFICATIONS</span><h2>Non lues</h2></div></div>
        <ul class="activity-list">
            @forelse ($unreadNotifications as $notification)
                <li><span class="activity-dot"></span><div><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong><small>{{ $notification->data['message'] ?? '' }}</small></div></li>
            @empty
                <li>Aucune notification non lue.</li>
            @endforelse
        </ul>
    </section>

    <!-- Onglets : Projets / Financements / Validations -->
    <div class="dashboard-tabs">
        <button class="tab active" onclick="switchTab(event, 'projets')">📊 Projets</button>
        <button class="tab" onclick="switchTab(event, 'financements')">💰 Financements</button>
        <button class="tab" onclick="switchTab(event, 'validations')">✅ Validations</button>
    </div>

    <!-- Vue Projets -->
    <div id="tab-projets" class="tab-content active">
        <div class="dashboard-grid">
            <section class="panel chart-panel">
                <div class="panel-heading"><div><span class="eyebrow">RÉPARTITION</span><h2>Projets par statut métier</h2><p>Répartition des projets selon leur statut (planned, in_progress, completed, etc.).</p></div></div>
                <div class="chart-container">
                    <canvas id="projectsByStatusChart" height="180"></canvas>
                </div>
                @if (count($projectsByStatus) === 0)
                    <p style="padding:20px;text-align:center;color:var(--muted);">Aucune donnée disponible.</p>
                @endif
            </section>

            <section class="panel chart-panel">
                <div class="panel-heading"><div><span class="eyebrow">VALIDATION</span><h2>Projets par statut de validation</h2><p>Répartition selon le statut de validation (pending_approval, approved, rejected, etc.).</p></div></div>
                <div class="chart-container">
                    <canvas id="projectsByValidationChart" height="180"></canvas>
                </div>
                @if (count($projectsByValidation) === 0)
                    <p style="padding:20px;text-align:center;color:var(--muted);">Aucune donnée disponible.</p>
                @endif
            </section>
        </div>

        <section class="panel">
            <div class="panel-heading"><div><span class="eyebrow">BUDGETS</span><h2>Budgets des projets</h2><p>Budgets initiaux et révisés par projet.</p></div></div>
            <table>
                <thead>
                    <tr>
                        <th>Projet</th>
                        <th>Statut métier</th>
                        <th>Statut validation</th>
                        <th>Budget révisé</th>
                        <th>Financement confirmé</th>
                        <th>Reste à financer</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projectsData as $p)
                        @php
                            $statusText = $p['is_underfunded'] ? 'Sous-financé' : 'Financé';
                            $statusClass = $p['is_underfunded'] ? 'warning' : 'success';
                        @endphp
                        <tr>
                            <td><strong>{{ $p['name'] }}</strong></td>
                            <td>{{ ucfirst($p['status']) }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $p['validation_status'])) }}</td>
                            <td>{{ number_format($p['revised_budget'], 0, ',', ' ') }} €</td>
                            <td>{{ number_format($p['confirmed'], 0, ',', ' ') }} €</td>
                            <td>{{ number_format($p['deficit'], 0, ',', ' ') }} €</td>
                            <td><x-ui.badge variant="{{ $statusClass }}">{{ $statusText }}</x-ui.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--muted);">Aucun projet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel">
            <div class="panel-heading"><div><span class="eyebrow">ALERTES</span><h2>Projets sous-financés</h2><p>{{ count($underfundedProjects) }} projet(s) sous-financé(s).</p></div></div>
            <table>
                <thead>
                    <tr><th>Projet</th><th>Budget</th><th>Confirmé</th><th>Déficit</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @php
                        $underfundedData = array_filter($projectsData, fn ($p) => $p['is_underfunded']);
                    @endphp
                    @forelse ($underfundedData as $p)
                        <tr>
                            <td><strong>{{ $p['name'] }}</strong></td>
                            <td>{{ number_format($p['revised_budget'], 0, ',', ' ') }} €</td>
                            <td>{{ number_format($p['confirmed'], 0, ',', ' ') }} €</td>
                            <td>{{ number_format($p['deficit'], 0, ',', ' ') }} €</td>
                            <td class="table-actions"><a href="{{ $p['url'] }}" class="button button-ghost button-sm">Voir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--muted);">Aucun projet sous-financé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading"><div><span class="eyebrow">ÉVOLUTION</span><h2>Évolution des soumissions (12 derniers mois)</h2><p>Nombre de projets soumis par mois.</p></div></div>
            <div class="chart-container">
                <canvas id="submissionsByMonthChart" height="180"></canvas>
            </div>
            @if ($submissionsByMonth['labels'] && count(array_filter($submissionsByMonth['data'])) === 0)
                <p style="padding:20px;text-align:center;color:var(--muted);">Aucune donnée disponible sur les 12 derniers mois.</p>
            @endif
        </section>

        <section class="panel">
            <div class="panel-heading"><div><span class="eyebrow">BLOQUÉS</span><h2>Projets bloqués (changements demandés / rejetés)</h2><p>{{ count($blockedProjects) }} projet(s) bloqué(s).</p></div></div>
            <table>
                <thead>
                    <tr><th>Projet</th><th>Statut validation</th><th>Commentaire</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($blockedProjects as $project)
                        <tr>
                            <td><strong>{{ $project->name }}</strong></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $project->validation_status)) }}</td>
                            <td>{{ $project->validation_comment ?? '—' }}</td>
                            <td class="table-actions"><a href="{{ route('projects.show', $project) }}" class="button button-ghost button-sm">Voir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--muted);">Aucun projet bloqué.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <!-- Vue Financements -->
    <div id="tab-financements" class="tab-content">
        <div class="dashboard-grid">
            <section class="panel chart-panel">
                <div class="panel-heading"><div><span class="eyebrow">MONTANTS</span><h2>Financements : approuvés, en attente, rejetés</h2><p>Montants agrégés par statut de validation.</p></div></div>
                <div class="chart-container">
                    <canvas id="financementsByStatusChart" height="180"></canvas>
                </div>
            </section>

            <section class="panel chart-panel">
                <div class="panel-heading"><div><span class="eyebrow">ALERTES</span><h2>Répartition des alertes financières</h2><p>Niveaux de couverture pour les financements en attente.</p></div></div>
                <div class="chart-container">
                    <canvas id="alertDistributionChart" height="180"></canvas>
                </div>
            </section>
        </div>

        <section class="panel">
            <div class="panel-heading"><div><span class="eyebrow">RÉSUMÉ</span><h2>Couverture financière globale</h2><p>Taux de couverture du budget total par les financements confirmés.</p></div></div>
            <div class="kpi-grid">
                <div class="kpi"><span>Montants approuvés</span><strong>{{ number_format($montantsApprouves, 0, ',', ' ') }} €</strong></div>
                <div class="kpi"><span>Montants rejetés</span><strong>{{ number_format($montantsRejetes, 0, ',', ' ') }} €</strong></div>
                <div class="kpi"><span>Demandes en attente</span><strong>{{ $financementsPending }}</strong></div>
                <div class="kpi"><span>Taux de couverture global</span><strong>{{ $globalCoverageRate ?? 0 }} %</strong></div>
                <div class="kpi"><span>Montant restant à financer</span><strong>{{ number_format($remainingToFund, 0, ',', ' ') }} €</strong></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-heading"><div><span class="eyebrow">DÉTAIL DES ALERTES</span><h2>Répartition par niveau de risque</h2><p>Financements en attente classés par niveau de couverture financière.</p></div></div>
            <table>
                <thead>
                    <tr><th>Niveau</th><th>Libellé</th><th>Nombre</th><th>Seuil de couverture</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong class="text-danger">Rouge</strong></td>
                        <td>Couverture très insuffisante (&lt; 10 %)</td>
                        <td>{{ $alertDistribution['rouge'] }}</td>
                        <td>&lt; 10 %</td>
                    </tr>
                    <tr>
                        <td><strong class="text-warning">Orange</strong></td>
                        <td>Couverture faible (10 % – 30 %)</td>
                        <td>{{ $alertDistribution['orange'] }}</td>
                        <td>10 % – 30 %</td>
                    </tr>
                    <tr>
                        <td><strong class="text-info">Bleu</strong></td>
                        <td>Couverture partiellement acceptable (30 % – 50 %)</td>
                        <td>{{ $alertDistribution['bleu'] }}</td>
                        <td>30 % – 50 %</td>
                    </tr>
                    <tr>
                        <td><strong class="text-success">Vert</strong></td>
                        <td>Couverture favorable (≥ 50 %)</td>
                        <td>{{ $alertDistribution['vert'] }}</td>
                        <td>≥ 50 %</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>

    <!-- Vue Validations -->
    <div id="tab-validations" class="tab-content">
        <div class="dashboard-grid">
            <section class="panel chart-panel">
                <div class="panel-heading"><div><span class="eyebrow">TAUX</span><h2>Taux d'approbation et de rejet</h2><p>Répartition des décisions sur les demandes terminées.</p></div></div>
                <div class="chart-container">
                    <canvas id="validationRatesChart" height="180"></canvas>
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading"><div><span class="eyebrow">DURÉE</span><h2>Durée moyenne de traitement</h2><p>Durée moyenne des demandes terminées (hors demandes en attente).</p></div></div>
                <div class="kpi-grid">
                    <div class="kpi"><span>Demandes en attente</span><strong>{{ count($pendingValidations) }}</strong></div>
                    <div class="kpi"><span>Demandes terminées</span><strong>{{ $totalDecided }}</strong></div>
                    <div class="kpi"><span>Durée moyenne</span><strong>{{ $avgProcessingTime !== null ? number_format($avgProcessingTime, 1) . ' h' : '—' }}</strong></div>
                    <div class="kpi"><span>Taux d'approbation</span><strong>{{ $approvalRate ?? 0 }} %</strong></div>
                    <div class="kpi"><span>Taux de rejet</span><strong>{{ $rejectionRate ?? 0 }} %</strong></div>
                </div>
            </section>
        </div>

        <section class="panel alert-panel">
            <div class="panel-heading"><div><span class="eyebrow">INTERVENTION</span><h2>Demandes anciennes (&gt; 7 jours en attente)</h2><p>{{ count($oldPendingValidations) }} demande(s) nécessitant une intervention.</p></div></div>
            <table>
                <thead>
                    <tr><th>Type</th><th>Titre</th><th>En attente depuis</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($oldPendingValidations as $item)
                        <tr>
                            <td>{{ $item['type'] === 'projet' ? 'Projet' : 'Financement' }}</td>
                            <td><strong>{{ $item['title'] }}</strong></td>
                            <td>{{ date('d/m Y', strtotime($item['submitted_at'])) }}</td>
                            <td class="table-actions"><a href="{{ $item['url'] }}" class="button button-ghost button-sm">Voir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--muted);">Aucune demande ancienne en attente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

@endif

@push('scripts')
<script>
// Formules utilisées pour le Centre de pilotage
// - Taux de couverture global = (Financements confirmés total / Budget total révisé) × 100
// - Projet sous-financé = Budget révisé > Financement confirmé
// - Durée moyenne de traitement = Moyenne des durées (décision - soumission) pour les demandes terminées (approve/reject uniquement)
// - Taux d'approbation = Demandes approuvées / Demandes terminées × 100
// - Taux de rejet = Demandes rejetées / Demandes terminées × 100

function switchTab(e, tabName) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    e.currentTarget.classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {

    // --- Graphique : Projets par statut métier ---
    const statusCtx = document.getElementById('projectsByStatusChart');
    if (statusCtx) {
        new Chart(statusCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json(array_keys($projectsByStatus)),
                datasets: [{
                    label: 'Nombre de projets',
                    data: @json(array_values($projectsByStatus)),
                    backgroundColor: 'rgba(22, 183, 163, 0.6)',
                    borderColor: 'rgba(22, 183, 163, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    // --- Graphique : Projets par statut de validation ---
    const valCtx = document.getElementById('projectsByValidationChart');
    if (valCtx) {
        new Chart(valCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: @json($projectsByValidationLabels),
                datasets: [{
                    label: 'Projets',
                    data: @json(array_values($projectsByValidation)),
                    backgroundColor: [
                        'rgba(22, 183, 163, 0.7)',
                        'rgba(241, 125, 111, 0.7)',
                        'rgba(241, 184, 111, 0.7)',
                        'rgba(55, 99, 140, 0.7)',
                        'rgba(100, 120, 130, 0.7)'
                    ],
                    borderColor: [
                        'rgba(22, 183, 163, 1)',
                        'rgba(241, 125, 111, 1)',
                        'rgba(241, 184, 111, 1)',
                        'rgba(55, 99, 140, 1)',
                        'rgba(100, 120, 130, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' projet(s)';
                            }
                        }
                    }
                }
            }
        });
    }

    // --- Graphique : Financements par statut ---
    const finCtx = document.getElementById('financementsByStatusChart');
    if (finCtx) {
        new Chart(finCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['En attente', 'Approuvés', 'Rejetés'],
                datasets: [{
                    label: 'Montant (€)',
                    data: [
                        {{ $financementsPendingAmount ?? 0 }},
                        {{ $montantsApprouves }},
                        {{ $montantsRejetes }}
                    ],
                    backgroundColor: [
                        'rgba(241, 184, 111, 0.7)',
                        'rgba(22, 183, 163, 0.7)',
                        'rgba(241, 125, 111, 0.7)'
                    ],
                    borderColor: [
                        'rgba(241, 184, 111, 1)',
                        'rgba(22, 183, 163, 1)',
                        'rgba(241, 125, 111, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return v + ' €'; } } }
                }
            }
        });
    }

    // --- Graphique : Répartition des alertes financières ---
    const alertCtx = document.getElementById('alertDistributionChart');
    if (alertCtx) {
        new Chart(alertCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['Rouge (< 10 %)', 'Orange (10-30 %)', 'Bleu (30-50 %)', 'Vert (≥ 50 %)'],
                datasets: [{
                    label: 'Nombre de financements',
                    data: [
                        {{ $alertDistribution['rouge'] }},
                        {{ $alertDistribution['orange'] }},
                        {{ $alertDistribution['bleu'] }},
                        {{ $alertDistribution['vert'] }}
                    ],
                    backgroundColor: [
                        'rgba(241, 125, 111, 0.7)',
                        'rgba(241, 184, 111, 0.7)',
                        'rgba(22, 183, 163, 0.7)',
                        'rgba(34, 139, 83, 0.7)'
                    ],
                    borderColor: [
                        'rgba(241, 125, 111, 1)',
                        'rgba(241, 184, 111, 1)',
                        'rgba(22, 183, 163, 1)',
                        'rgba(34, 139, 83, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' financement(s)';
                            }
                        }
                    }
                }
            }
        });
    }

    // --- Graphique : Taux d'approbation / rejet ---
    const ratesCtx = document.getElementById('validationRatesChart');
    if (ratesCtx) {
        new Chart(ratesCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Approuvées', 'Rejetées'],
                datasets: [{
                    label: 'Demandes',
                    data: [
                        {{ $projectsApproved + $financementsApproved }},
                        {{ $projectsRejected + $financementsRejected }}
                    ],
                    backgroundColor: [
                        'rgba(22, 183, 163, 0.7)',
                        'rgba(241, 125, 111, 0.7)'
                    ],
                    borderColor: [
                        'rgba(22, 183, 163, 1)',
                        'rgba(241, 125, 111, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' demande(s)';
                            }
                        }
                    }
                }
            }
        });
    }

    // --- Graphique : Évolution des soumissions ---
    const subCtx = document.getElementById('submissionsByMonthChart');
    if (subCtx) {
        new Chart(subCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: @json($submissionsByMonth['labels']),
                datasets: [{
                    label: 'Projets soumis',
                    data: @json($submissionsByMonth['data']),
                    borderColor: 'rgba(22, 183, 163, 1)',
                    backgroundColor: 'rgba(22, 183, 163, 0.2)',
                    tension: 0.2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
});
</script>
@endpush
<style>
    /* ============================================================
       Centre de pilotage — Design Ultra Premium
       ============================================================ */

    /* Variables de couleur premium */
    :root {
        --premium-deep: #073f4a;
        --premium-aqua: #16b7a3;
        --premium-aqua-light: rgba(22, 183, 163, 0.15);
        --premium-success: #177853;
        --premium-success-light: rgba(23, 120, 83, 0.15);
        --premium-warning: #9a6814;
        --premium-warning-light: rgba(154, 104, 20, 0.15);
        --premium-danger: #a8473d;
        --premium-danger-light: rgba(168, 71, 61, 0.15);
        --premium-info: #23638c;
        --premium-info-light: rgba(35, 99, 140, 0.15);
        --premium-glass: rgba(255, 255, 255, 0.85);
        --premium-shadow: 0 12px 40px rgba(7, 63, 74, 0.08);
        --premium-shadow-hover: 0 20px 60px rgba(7, 63, 74, 0.14);
    }

    /* KPI Cards Premium avec effet verre */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }

    .kpi {
        background: linear-gradient(145deg, #ffffff, var(--premium-glass));
        backdrop-filter: blur(12px);
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 20px 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        box-shadow: var(--premium-shadow);
        min-height: 120px;
    }

    .kpi::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--premium-aqua), var(--premium-deep));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .kpi:hover {
        transform: translateY(-4px);
        box-shadow: var(--premium-shadow-hover);
        border-color: var(--premium-aqua);
    }

    .kpi:hover::before {
        opacity: 1;
    }

    .kpi span {
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .kpi strong {
        color: var(--ink);
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .kpi strong.text-warning { color: var(--premium-warning); }
    .kpi strong.text-danger { color: var(--premium-danger); }
    .kpi strong.text-success { color: var(--premium-success); }
    .kpi strong.text-info { color: var(--premium-info); }

    /* Tabs modernes */
    .dashboard-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 24px;
        border-bottom: none;
        padding-bottom: 0;
        background: transparent;
    }

    .tab {
        background: #fff;
        border: 1px solid var(--line);
        color: var(--ink);
        padding: 12px 20px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 700;
        font-size: 13px;
        transition: all 0.3s ease;
        box-shadow: var(--premium-shadow);
    }

    .tab.active {
        background: linear-gradient(135deg, var(--premium-deep), #0a5f6e);
        color: #fff;
        border-color: var(--premium-deep);
        box-shadow: 0 8px 24px rgba(7, 63, 74, 0.25);
    }

    .tab:not(.active):hover {
        background: var(--soft);
        border-color: var(--premium-aqua);
        color: var(--premium-aqua);
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Panels premium */
    .panel {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: var(--premium-shadow);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .panel:hover {
        transform: translateY(-2px);
        box-shadow: var(--premium-shadow-hover);
    }

    .panel-heading {
        background: linear-gradient(135deg, #fff 0%, #f8faf9 100%);
        padding: 20px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .panel-heading > div {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .panel-heading h2 {
        margin: 0;
        font-size: 17px;
        font-weight: 800;
        color: var(--ink);
        letter-spacing: -0.02em;
    }

    .panel-heading p {
        margin: 0;
        color: var(--muted);
        font-size: 12px;
    }

    .panel-heading a {
        font-size: 12px;
        font-weight: 700;
        color: var(--premium-aqua);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .panel-heading a:hover {
        color: var(--premium-deep);
    }

    /* Alert list moderne */
    .alert-list {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .alert-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        transition: all 0.25s ease;
    }

    .alert-row:hover {
        background: var(--premium-aqua-light);
        border-color: var(--premium-aqua);
        transform: translateX(4px);
    }

    .incident-mark {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        font-size: 16px;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .incident-mark.warning {
        background: var(--premium-warning-light);
        color: var(--premium-warning);
    }

    .incident-mark.danger {
        background: var(--premium-danger-light);
        color: var(--premium-danger);
    }

    .incident-mark.info {
        background: var(--premium-info-light);
        color: var(--premium-info);
    }

    .alert-row > div {
        flex: 1;
        min-width: 0;
    }

    .alert-row strong {
        display: block;
        font-size: 14px;
        color: var(--ink);
        font-weight: 700;
    }

    .alert-row small {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.4;
    }

    .alert-row b {
        font-size: 10px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .alert-row b.soft {
        background: #fff6dd;
        color: #99721d;
    }

    .alert-row b:not(.soft) {
        background: #fde9e7;
        color: #a8473d;
    }

    /* Badges de décision premium */
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

    /* Boutons premium */
    .button {
        background: linear-gradient(135deg, var(--premium-deep), #0a5f6e);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 12px 20px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 14px rgba(7, 63, 74, 0.2);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        justify-content: center;
    }

    .button:hover {
        background: linear-gradient(135deg, #0a5f6e, var(--premium-deep));
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(7, 63, 74, 0.3);
        color: #fff;
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
        transform: translateY(-2px);
    }

    .button-sm {
        padding: 8px 14px;
        font-size: 11px;
    }

    .button-ghost.button-sm {
        border-color: var(--line);
        color: var(--muted);
    }

    .button-ghost.button-sm:hover {
        background: var(--soft);
        color: var(--premium-aqua);
        border-color: var(--premium-aqua);
    }

    /* Tableaux premium */
    .table-wrap table,
    section.panel table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table-wrap th,
    section.panel th {
        background: #f8faf9;
        color: var(--muted);
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        padding: 14px 16px;
        border-bottom: 1px solid var(--line);
        text-align: left;
    }

    .table-wrap td,
    section.panel td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--line);
        color: var(--ink);
        font-size: 13px;
        transition: background 0.2s ease;
    }

    .table-wrap tr:hover td,
    section.panel tr:hover td {
        background: var(--premium-aqua-light);
    }

    .table-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    /* Activité récente */
    .activity-list {
        list-style: none;
        padding: 20px;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .activity-list li {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        font-size: 13px;
        transition: all 0.25s ease;
    }

    .activity-list li:hover {
        background: var(--premium-aqua-light);
        border-color: var(--premium-aqua);
    }

    .activity-dot {
        display: grid;
        place-items: center;
        width: 28px;
        height: 28px;
        border-radius: 10px;
        flex-shrink: 0;
        font-size: 14px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    }

    .activity-dot.success { background: var(--premium-success-light); color: var(--premium-success); }
    .activity-dot.danger { background: var(--premium-danger-light); color: var(--premium-danger); }
    .activity-dot.info { background: var(--premium-info-light); color: var(--premium-info); }

    .activity-list li strong {
        color: var(--ink);
        font-weight: 800;
    }

    .activity-list li small {
        color: var(--muted);
        font-size: 11px;
        margin-left: 6px;
    }

    /* Conteneur graphique */
    .chart-container {
        position: relative;
        height: 260px;
        width: 100%;
    }

    /* Dashboard grid */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
    }

    @media (max-width: 1024px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .panel-heading {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }
        .panel-heading a {
            margin-top: 8px;
        }
    }

    @media (max-width: 768px) {
        .content-heading {
            flex-direction: column;
            gap: 16px;
            align-items: flex-start;
        }
        .content-heading .actions {
            width: 100%;
            justify-content: space-between;
        }
        .kpi-grid {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .decision-form {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }
        .decision-form label {
            text-align: left;
        }
        .decision-form .input-small,
        .decision-form input[name="comment"] {
            width: 100%;
            min-width: auto;
        }
        .button-decide,
        .button-submit {
            width: 100%;
            justify-content: center;
        }
        .chart-container {
            height: 220px;
        }
    }

    @media (max-width: 480px) {
        .panel-heading {
            padding: 16px;
        }
        .kpi {
            padding: 16px 12px;
        }
        .kpi strong {
            font-size: 20px;
        }
        .chart-container {
            height: 200px;
        }
    }

    /* États vides */
    .panel > p {
        color: var(--muted);
        font-size: 13px;
        padding: 20px;
        text-align: center;
    }

    .text-danger { color: var(--premium-danger); font-weight: 700; }
    .text-warning { color: var(--premium-warning); font-weight: 700; }
    .text-info { color: var(--premium-info); font-weight: 700; }
    .text-success { color: var(--premium-success); font-weight: 700; }

    /* ============================================================
       Boutons d'action et formulaire de décision (projet & financement)
       ============================================================ */
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
    .decision-form input[type="text"]:focus,
    .decision-form input[name="comment"]:focus {
        outline: none;
        border-color: var(--premium-aqua);
        box-shadow: 0 0 0 3px var(--premium-aqua-light);
    }

    .button-decide {
        background: linear-gradient(135deg, var(--premium-deep), #0a5f6e);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 800;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 14px rgba(7, 63, 74, 0.2);
        white-space: nowrap;
    }

    .button-decide:hover {
        background: linear-gradient(135deg, #0a5f6e, var(--premium-deep));
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(7, 63, 74, 0.3);
    }

    .button-submit {
        background: linear-gradient(135deg, var(--premium-aqua), #0f9e8c);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 800;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 14px rgba(22, 183, 163, 0.2);
        white-space: nowrap;
    }

    .button-submit:hover {
        background: linear-gradient(135deg, #0f9e8c, var(--premium-aqua));
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(22, 183, 163, 0.3);
    }

    .button-approve {
        background: linear-gradient(135deg, var(--premium-success), #1d9d6b);
        color: #fff;
    }

    .button-approve:hover {
        background: linear-gradient(135deg, #1d9d6b, var(--premium-success));
    }

    .button-reject {
        background: linear-gradient(135deg, var(--premium-danger), #c95c50);
        color: #fff;
    }

    .button-reject:hover {
        background: linear-gradient(135deg, #c95c50, var(--premium-danger));
    }

    .button-request-changes {
        background: linear-gradient(135deg, var(--premium-warning), #b37a1a);
        color: #fff;
    }

    .button-request-changes:hover {
        background: linear-gradient(135deg, #b37a1a, var(--premium-warning));
    }

    /* Responsive avancé pour le Centre de pilotage */
    @media (max-width: 1024px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .panel-heading {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }
        .panel-heading a {
            margin-top: 8px;
        }
    }

    @media (max-width: 768px) {
        .content-heading {
            flex-direction: column;
            gap: 16px;
            align-items: flex-start;
        }
        .content-heading .actions {
            width: 100%;
            justify-content: space-between;
        }
        .kpi-grid {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .decision-form {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }
        .decision-form label {
            text-align: left;
        }
        .decision-form .input-small,
        .decision-form input[name="comment"] {
            width: 100%;
            min-width: auto;
        }
        .button-decide,
        .button-submit {
            width: 100%;
            justify-content: center;
        }
        .chart-container {
            height: 220px;
        }
    }

    @media (max-width: 480px) {
        .panel-heading {
            padding: 16px;
        }
        .kpi {
            padding: 16px 12px;
        }
        .kpi strong {
            font-size: 20px;
        }
        .chart-container {
            height: 200px;
        }
    }

    /* Conteneur largeur pleine pour le centre de pilotage */
    .pilotage-container {
        width: 100%;
        max-width: none;
        padding: 0 20px;
    }
</style>
</div></section>

@endsection