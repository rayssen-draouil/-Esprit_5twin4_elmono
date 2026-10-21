<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectFinancialService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FinancialDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Get projects accessible to the user (based on role)
        $user = $request->user();
        $projects = Project::with(['budgetRevisions', 'expenses', 'expenses.expensePayments', 'financements', 'user'])
            ->when($user->role !== 'admin', function ($query) {
                // For non-admins, we could filter by user_id if projects were associated with users.
                // Since the existing projects do not have user_id set, and the financial dashboard is
                // intended for monitoring by admins/managers/gestionnaires, we show all projects.
                // In a future update, we can properly associate projects with users and filter accordingly.
                // For now, we show all projects to non-admins as well (but note: only admin, manager, gestionnaire
                // can access this route due to middleware).
            })
            ->get();

        // Calculate financial summary
        $financialSummary = ProjectFinancialService::getProjectsFinancialSummary($projects);

        // Prepare data for charts
        $chartData = $this->getChartData($projects);

        // Get financial alerts
        $alerts = $this->getFinancialAlerts($projects);

        // Prepare data for the view
        $data = [
            'projects' => $projects,
            'financialSummary' => $financialSummary,
            'chartData' => $chartData,
            'fundingBySource' => $chartData['fundingBySource'],
            'months' => $chartData['months'],
            'paidByMonth' => $chartData['paidByMonth'],
            'fundedByMonth' => $chartData['fundedByMonth'],
            'alerts' => $alerts,
        ];

        return view('financial.dashboard', $data);
    }

    /**
     * Get data for charts
     */
    private function getChartData($projects): array
    {
        // Budget vs Réalisé data
        $budgetVsReal = [
            'projects' => $projects->pluck('name'),
            'initial_budget' => $projects->map(function ($p) {
                return app(ProjectFinancialService::class)->getInitialBudget($p);
            }),
            'revised_budget' => $projects->map(function ($p) {
                return app(ProjectFinancialService::class)->getRevisedBudget($p);
            }),
            'engaged_expenses' => $projects->map(function ($p) {
                return app(ProjectFinancialService::class)->getEngagedExpenses($p);
            }),
            'paid_amounts' => $projects->map(function ($p) {
                return app(ProjectFinancialService::class)->getPaidAmounts($p);
            }),
        ];

        // Funding by source
        $fundingBySource = [];
        foreach ($projects as $project) {
            $financings = $project->financements()->where('status', 'received')->get();
            foreach ($financings as $financing) {
                $source = $financing->source;
                if (! isset($fundingBySource[$source])) {
                    $fundingBySource[$source] = 0;
                }
                $fundingBySource[$source] += $financing->amount;
            }
        }

        // Financial evolution over time (last 12 months)
        $months = [];
        $paidByMonth = [];
        $fundedByMonth = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $months[] = Carbon::now()->subMonths($i)->format('M Y');

            // Paid expenses for this month
            $paid = 0;
            foreach ($projects as $project) {
                $paid += $project->expenses()
                    ->where('project_expenses.status', 'approved')
                    ->join('expense_payments', 'project_expenses.id', '=', 'expense_payments.expense_id')
                    ->where('expense_payments.status', 'confirmed')
                    ->whereRaw("DATE_FORMAT(expense_payments.payment_date, '%Y-%m') = ?", [$month])
                    ->sum('expense_payments.amount');
            }
            $paidByMonth[] = $paid;

            // Funded amounts for this month
            $funded = 0;
            foreach ($projects as $project) {
                $funded += $project->financements()
                    ->where('status', 'received')
                    ->whereRaw("DATE_FORMAT(financements.funded_at, '%Y-%m') = ?", [$month])
                    ->sum('amount');
            }
            $fundedByMonth[] = $funded;
        }

        return [
            'budgetVsReal' => $budgetVsReal,
            'fundingBySource' => $fundingBySource,
            'months' => $months,
            'paidByMonth' => $paidByMonth,
            'fundedByMonth' => $fundedByMonth,
        ];
    }

    /**
     * Get financial alerts
     */
    private function getFinancialAlerts($projects): array
    {
        $alerts = [];

        foreach ($projects as $project) {
            $initialBudget = app(ProjectFinancialService::class)->getInitialBudget($project);
            $revisedBudget = app(ProjectFinancialService::class)->getRevisedBudget($project);
            $confirmedFunding = app(ProjectFinancialService::class)->getConfirmedFunding($project);
            $engagedExpenses = app(ProjectFinancialService::class)->getEngagedExpenses($project);
            $paidAmounts = app(ProjectFinancialService::class)->getPaidAmounts($project);

            // Budget exceeded alert
            if ($revisedBudget > 0 && $engagedExpenses > $revisedBudget) {
                $alerts[] = [
                    'type' => 'budget_exceeded',
                    'icon' => '!',
                    'title' => "Dépassement budgétaire sur {$project->name}",
                    'description' => 'Les dépenses engagées ('.number_format($engagedExpenses, 0, ',', ' ').' €) dépassent le budget révisé ('.number_format($revisedBudget, 0, ',', ' ').' €).',
                    'action_url' => route('projects.show', $project),
                ];
            }

            // Insufficient funding alert
            if ($revisedBudget > 0 && $confirmedFunding < $revisedBudget) {
                $alerts[] = [
                    'type' => 'insufficient_funding',
                    'icon' => '↗',
                    'title' => "Financement insuffisant pour {$project->name}",
                    'description' => 'Le financement confirmé ('.number_format($confirmedFunding, 0, ',', ' ').' €) est inférieur au budget révisé ('.number_format($revisedBudget, 0, ',', ' ').' €).',
                    'action_url' => route('projects.show', $project),
                ];
            }

            // Pending budget revisions
            $pendingRevisions = $project->budgetRevisions()->where('status', 'pending_approval')->count();
            if ($pendingRevisions > 0) {
                $alerts[] = [
                    'type' => 'pending_revision',
                    'icon' => '⏳',
                    'title' => "Révision budgétaire en attente pour {$project->name}",
                    'description' => "$pendingRevisions demande(s) de révision budgétaire en attente d'approbation.",
                    'action_url' => route('projects.show', $project),
                ];
            }

            // Expenses pending approval
            $pendingExpenses = $project->expenses()->where('project_expenses.status', 'pending_approval')->count();
            if ($pendingExpenses > 0) {
                $alerts[] = [
                    'type' => 'pending_expenses',
                    'icon' => '⏳',
                    'title' => "Dépenses en attente d'approbation pour {$project->name}",
                    'description' => "$pendingExpenses dépense(s) en attente d'approbation.",
                    'action_url' => route('projects.show', $project),
                ];
            }

            // Payments pending confirmation
            $pendingPayments = $project->expenses()
                ->where('project_expenses.status', 'approved')
                ->join('expense_payments', 'project_expenses.id', '=', 'expense_payments.expense_id')
                ->where('expense_payments.status', 'pending')
                ->count();
            if ($pendingPayments > 0) {
                $alerts[] = [
                    'type' => 'pending_payments',
                    'icon' => '⏳',
                    'title' => "Paiements en attente de confirmation pour {$project->name}",
                    'description' => "$pendingPaiements paiement(s) en attente de confirmation.",
                    'action_url' => route('projects.show', $project),
                ];
            }
        }

        // Limit to most critical alerts
        return collect($alerts)->take(8)->all();
    }
}
