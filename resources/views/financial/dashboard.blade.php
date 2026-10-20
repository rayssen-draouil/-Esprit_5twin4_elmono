@extends('layouts.back')

@section('content')
<div class="content-heading">
    <div><span class="eyebrow">PILOTAGE BUDGÉTAIRE ET FINANCIER</span><h1>Tableau de bord financier</h1><p>Suivi des budgets, engagements, paiements et financements des projets.</p></div>
    <div class="actions">
        <a class="button button-ghost" href="{{ route('projects.index') }}">Voir tous les projets</a>
    </div>
</div>

<!-- KPI Grid -->
<div class="kpi-grid">
    <div class="kpi"><span>Nombre de projets suivis</span><strong>{{ $projects->count() }}</strong></div>
    <div class="kpi"><span>Budget initial total</span><strong>{{ number_format($financialSummary['initial_budget'], 0, ',', ' ') }} €</strong></div>
    <div class="kpi"><span>Budget révisé total</span><strong>{{ number_format($financialSummary['revised_budget'], 0, ',', ' ') }} €</strong></div>
    <div class="kpi"><span>Financements confirmés</span><strong>{{ number_format($financialSummary['confirmed_funding'], 0, ',', ' ') }} €</strong></div>
    <div class="kpi"><span>Dépenses engagées</span><strong>{{ number_format($financialSummary['engaged_expenses'], 0, ',', ' ') }} €</strong></div>
    <div class="kpi"><span>Dépenses réellement payées</span><strong>{{ number_format($financialSummary['paid_amounts'], 0, ',', ' ') }} €</strong></div>
    <div class="kpi"><span>Reste à payer</span><strong>{{ number_format($financialSummary['remaining_to_pay'], 0, ',', ' ') }} €</strong></div>
    <div class="kpi"><span>Déficit global de couverture</span><strong>{{ number_format($financialSummary['funding_deficit'], 0, ',', ' ') }} €</strong></div>
</div>

<!-- Charts Grid -->
<div class="dashboard-grid">
    <!-- Budget vs Réalisé -->
    <section class="panel chart-panel">
        <div class="panel-heading"><div><span class="eyebrow">ANALYSE</span><h2>Budget vs Réalisé par projet</h2><p>Comparaison du budget initial, révisé, des dépenses engagées et payées.</p></div></div>
        <div class="chart-container">
            <canvas id="budgetVsRealChart" height="200"></canvas>
        </div>
    </section>

    <!-- Financements par source -->
    <section class="panel">
        <div class="panel-heading"><div><span class="eyebrow">FINANCEMENTS</span><h2>Répartition des financements confirmés</h2><p>Par source de financement.</p></div></div>
        <div class="chart-container">
            <canvas id="fundingSourcesChart" height="200"></canvas>
        </div>
    </section>
</div>

<!-- Évolution financière dans le temps -->
<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">ÉVOLUTION</span><h2>Évolution financière dans le temps</h2><p>Dépenses payées et financements décaissés par mois.</p></div></div>
    <div class="chart-container">
        <canvas id="financialEvolutionChart" height="200"></canvas>
    </div>
</section>

<!-- Alertes financières -->
<section class="panel alert-panel">
    <div class="panel-heading"><div><span class="eyebrow">ALERTES</span><h2>Alertes financières prioritaires</h2></div></div>
    <div class="alert-list">
        @forelse ($alerts as $alert)
            <div class="alert-row">
                <span class="incident-mark {{ $alert['type'] === 'budget_exceeded' ? 'danger' : ($alert['type'] === 'insufficient_funding' ? 'warning' : 'info') }}">{{ $alert['icon'] }}</span>
                <div>
                    <strong>{{ $alert['title'] }}</strong>
                    <small>{{ $alert['description'] }}</small>
                </div>
                @if ($alert['action_url'])
                    <a href="{{ $alert['action_url'] }}" class="button button-sm button-ghost">Voir</a>
                @endif
            </div>
        @empty
            <p>Aucune alerte financière détectée.</p>
        @endforelse
    </div>
</section>

<!-- Tableau détaillé des projets -->
<section class="panel table-wrap">
    <div class="panel-heading"><div><span class="eyebrow">DÉTAIL</span><h2>Tableau détaillé des projets</h2></div></div>
    <table>
        <thead>
            <tr>
                <th>Projet</th>
                <th>Budget initial</th>
                <th>Budget révisé</th>
                <th>Financement confirmé</th>
                <th>Dépenses engagées</th>
                <th>Dépenses payées</th>
                <th>Marge budgétaire</th>
                <th>Taux de couverture</th>
                <th>État financier</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($projects as $project)
                @php
                    $initialBudget = app(\App\Services\ProjectFinancialService::class)->getInitialBudget($project);
                    $revisedBudget = app(\App\Services\ProjectFinancialService::class)->getRevisedBudget($project);
                    $confirmedFunding = app(\App\Services\ProjectFinancialService::class)->getConfirmedFunding($project);
                    $engagedExpenses = app(\App\Services\ProjectFinancialService::class)->getEngagedExpenses($project);
                    $paidAmounts = app(\App\Services\ProjectFinancialService::class)->getPaidAmounts($project);
                    $budgetMargin = $revisedBudget - $engagedExpenses;
                    $coverageRate = $revisedBudget > 0 ? min(100, round(($confirmedFunding / $revisedBudget) * 100, 1)) : 0;
                    $paymentRate = $engagedExpenses > 0 ? min(100, round(($paidAmounts / $engagedExpenses) * 100, 1)) : 0;

                    $financialStatus = 'normal';
                    $statusText = 'Normal';
                    $statusClass = 'badge-info';

                    if ($revisedBudget > 0 && $engagedExpenses > $revisedBudget) {
                        $financialStatus = 'budget_exceeded';
                        $statusText = 'Dépassement budgétaire';
                        $statusClass = 'badge-danger';
                    } elseif ($revisedBudget > 0 && $confirmedFunding < $revisedBudget) {
                        $financialStatus = 'insufficient_funding';
                        $statusText = 'Financement insuffisant';
                        $statusClass = 'badge-warning';
                    }
                @endphp
                <tr>
                    <td><strong>{{ $project->name }}</strong></td>
                    <td>{{ number_format($initialBudget, 2, ',', ' ') }} €</td>
                    <td>{{ number_format($revisedBudget, 2, ',', ' ') }} €</td>
                    <td>{{ number_format($confirmedFunding, 2, ',', ' ') }} €</td>
                    <td>{{ number_format($engagedExpenses, 2, ',', ' ') }} €</td>
                    <td>{{ number_format($paidAmounts, 2, ',', ' ') }} €</td>
                    <td>{{ number_format($budgetMargin, 2, ',', ' ') }} €</td>
                    <td>{{ $coverageRate }}%</td>
                    <td><x-ui.badge variant="{{ str_replace('badge-', '', $statusClass) }}">{{ $statusText }}</x-ui.badge></td>
                    <td class="table-actions">
                        <a href="{{ route('projects.show', $project) }}" class="button button-ghost button-sm">Voir</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>

@push('scripts')
<script>
// Initialize charts after DOM loads
document.addEventListener('DOMContentLoaded', function() {
    // Budget vs Réalisé Chart
    const budgetCtx = document.getElementById('budgetVsRealChart').getContext('2d');
    new Chart(budgetCtx, {
        type: 'bar',
        data: {
            labels: @json($projects->pluck('name')),
            datasets: [
                {
                    label: 'Budget initial',
                    data: @json($projects->map(function($p) {
                        return app(\App\Services\ProjectFinancialService::class)->getInitialBudget($p);
                    })),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Budget révisé',
                    data: @json($projects->map(function($p) {
                        return app(\App\Services\ProjectFinancialService::class)->getRevisedBudget($p);
                    })),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Dépenses engagées',
                    data: @json($projects->map(function($p) {
                        return app(\App\Services\ProjectFinancialService::class)->getEngagedExpenses($p);
                    })),
                    backgroundColor: 'rgba(255, 206, 86, 0.5)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Dépenses payées',
                    data: @json($projects->map(function($p) {
                        return app(\App\Services\ProjectFinancialService::class)->getPaidAmounts($p);
                    })),
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value + ' €';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' €';
                        }
                    }
                }
            }
        }
    });

    // Financements par source Chart
    const fundingCtx = document.getElementById('fundingSourcesChart').getContext('2d');
    new Chart(fundingCtx, {
        type: 'pie',
        data: {
            labels: @json(array_keys($fundingBySource)),
            datasets: [{
                label: 'Financements confirmés par source',
                data: @json(array_values($fundingBySource)),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)',
                    'rgba(255, 159, 64, 0.5)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const sum = context.dataset._meta[Object.keys(context.dataset._meta)[0]].total;
                            const percentage = (value / sum * 100).toFixed(1) + '%';
                            return label + ': ' + value + ' € (' + percentage + ')';
                        }
                    }
                }
            }
        }
    });

    // Évolution financière dans le temps Chart
    const evolutionCtx = document.getElementById('financialEvolutionChart').getContext('2d');
    new Chart(evolutionCtx, {
        type: 'line',
        data: {
            labels: @json($months),
            datasets: [
                {
                    label: 'Dépenses payées',
                    data: @json($paidByMonth),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: false
                },
                {
                    label: 'Financements décaissés',
                    data: @json($fundedByMonth),
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value + ' €';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' €';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush
@endsection