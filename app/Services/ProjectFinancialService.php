<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectBudgetRevision;
use App\Models\ProjectExpense;
use App\Models\ExpensePayment;
use App\Models\Financement;
use Illuminate\Support\Facades\DB;

class ProjectFinancialService
{
    /**
     * Get the initial budget of a project (original budget)
     */
    public static function getInitialBudget(Project $project): float
    {
        return (float) $project->budget;
    }

    /**
     * Get the current approved budget (latest approved revision or original)
     */
    public static function getRevisedBudget(Project $project): float
    {
        $latestApprovedRevision = $project->budgetRevisions()
            ->where('status', 'approved')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first();

        if ($latestApprovedRevision) {
            return (float) $latestApprovedRevision->proposed_amount;
        }

        return (float) $project->budget;
    }

    /**
     * Get the budget variance (revised - initial)
     */
    public static function getBudgetVariance(Project $project): float
    {
        return self::getRevisedBudget($project) - self::getInitialBudget($project);
    }

    /**
     * Get the budget variance percentage
     */
    public static function getBudgetVariancePercentage(Project $project): float|null
    {
        $initial = self::getInitialBudget($project);
        if ($initial <= 0) {
            return null;
        }

        return round((self::getBudgetVariance($project) / $initial) * 100, 2);
    }

    /**
     * Get total engaged expenses (approved expenses)
     */
    public static function getEngagedExpenses(Project $project): float
    {
        return $project->expenses()
            ->where('status', 'approved')
            ->sum('amount');
    }

    /**
     * Get total paid amounts (confirmed payments)
     */
    public static function getPaidAmounts(Project $project): float
    {
        return $project->expenses()
            ->where('project_expenses.status', 'approved')
            ->join('expense_payments', 'project_expenses.id', '=', 'expense_payments.expense_id')
            ->where('expense_payments.status', 'confirmed')
            ->sum('expense_payments.amount');
    }

    /**
     * Get remaining to pay (engaged - paid)
     */
    public static function getRemainingToPay(Project $project): float
    {
        return max(0, self::getEngagedExpenses($project) - self::getPaidAmounts($project));
    }

    /**
     * Get total confirmed funding
     */
    public static function getConfirmedFunding(Project $project): float
    {
        return $project->financements()
            ->where('status', 'received')
            ->where('validation_status', 'approved')
            ->sum('amount');
    }

    /**
     * Get funding deficit (max(0, budget - funding))
     */
    public static function getFundingDeficit(Project $project): float
    {
        return max(0, self::getRevisedBudget($project) - self::getConfirmedFunding($project));
    }

    /**
     * Get financial coverage rate (funding / budget * 100)
     */
    public static function getFinancialCoverageRate(Project $project): float|null
    {
        $budget = self::getRevisedBudget($project);
        if ($budget <= 0) {
            return null;
        }

        return min(100, round((self::getConfirmedFunding($project) / $budget) * 100, 2));
    }

    /**
     * Get budget consumption rate (engaged / budget * 100)
     */
    public static function getBudgetConsumptionRate(Project $project): float|null
    {
        $budget = self::getRevisedBudget($project);
        if ($budget <= 0) {
            return null;
        }

        return min(100, round((self::getEngagedExpenses($project) / $budget) * 100, 2));
    }

    /**
     * Get payment rate (paid / engaged * 100)
     */
    public static function getPaymentRate(Project $project): float|null
    {
        $engaged = self::getEngagedExpenses($project);
        if ($engaged <= 0) {
            return null;
        }

        return min(100, round((self::getPaidAmounts($project) / $engaged) * 100, 2));
    }

    /**
     * Get budget history with revisions
     */
    public static function getBudgetHistory(Project $project): array
    {
        $history = [];

        // Add initial budget
        $history[] = [
            'amount' => $project->budget,
            'type' => 'initial',
            'date' => $project->created_at,
            'description' => 'Budget initial',
            'approved_by' => null,
            'approved_at' => $project->created_at,
        ];

        // Add approved revisions
        $revisions = $project->budgetRevisions()
            ->where('status', 'approved')
            ->orderBy('approved_at')
            ->get();

        foreach ($revisions as $revision) {
            $history[] = [
                'amount' => $revision->proposed_amount,
                'type' => 'revision',
                'date' => $revision->approved_at,
                'description' => $revision->reason,
                'approved_by' => $revision->approvedBy,
                'approved_at' => $revision->approved_at,
            ];
        }

        return $history;
    }

    /**
     * Get financial summary for multiple projects
     */
    public static function getProjectsFinancialSummary($projects): array
    {
        $totals = [
            'initial_budget' => 0,
            'revised_budget' => 0,
            'engaged_expenses' => 0,
            'paid_amounts' => 0,
            'confirmed_funding' => 0,
            'remaining_to_pay' => 0,
            'funding_deficit' => 0,
        ];

        foreach ($projects as $project) {
            $totals['initial_budget'] += self::getInitialBudget($project);
            $totals['revised_budget'] += self::getRevisedBudget($project);
            $totals['engaged_expenses'] += self::getEngagedExpenses($project);
            $totals['paid_amounts'] += self::getPaidAmounts($project);
            $totals['confirmed_funding'] += self::getConfirmedFunding($project);
            $totals['remaining_to_pay'] += self::getRemainingToPay($project);
            $totals['funding_deficit'] += self::getFundingDeficit($project);
        }

        return $totals;
    }
}