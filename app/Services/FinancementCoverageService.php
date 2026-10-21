<?php

namespace App\Services;

use App\Models\Financement;
use App\Models\Project;

class FinancementCoverageService
{
    public const ALERTE_ROUGE = 'rouge';

    public const ALERTE_ORANGE = 'orange';

    public const ALERTE_BLEU = 'bleu';

    public const ALERTE_VERT = 'vert';

    public const SEUIL_ROUGE = 10;

    public const SEUIL_ORANGE = 30;

    public const SEUIL_BLEU = 50;

    /**
     * Calcule l'analyse de couverture financière pour une demande de financement.
     *
     * @param  Financement  $financement  La demande en cours d'évaluation
     * @return array{
     *   budget: float,
     *   confirmed_others: float,
     *   request_amount: float,
     *   projected_total: float,
     *   coverage_rate: float|null,
     *   remaining_to_fund: float,
     *   alert_level: string,
     *   alert_label: string,
     *   alert_message: string,
     *   is_overfunded: bool,
     *   is_budget_valid: bool
     * }
     */
    public static function calculate(Financement $financement): array
    {
        $project = $financement->project;
        $budget = self::getBudget($project);
        $isBudgetValid = $budget > 0;

        $confirmedOthers = self::getConfirmedOthers($project, $financement);
        $requestAmount = (float) $financement->amount;

        $projectedTotal = $confirmedOthers + $requestAmount;
        $coverageRate = $isBudgetValid ? round(($projectedTotal / $budget) * 100, 2) : null;
        $remainingToFund = $isBudgetValid ? max(0, $budget - $projectedTotal) : 0;

        $isOverfunded = $isBudgetValid && $projectedTotal > $budget;

        $alertLevel = self::getAlertLevel($coverageRate);
        $alertLabel = self::getAlertLabel($alertLevel);
        $alertMessage = self::getAlertMessage($alertLevel, $coverageRate, $isOverfunded, $budget, $projectedTotal, $remainingToFund);

        return [
            'budget' => $budget,
            'confirmed_others' => $confirmedOthers,
            'request_amount' => $requestAmount,
            'projected_total' => $projectedTotal,
            'coverage_rate' => $coverageRate,
            'remaining_to_fund' => $remainingToFund,
            'alert_level' => $alertLevel,
            'alert_label' => $alertLabel,
            'alert_message' => $alertMessage,
            'is_overfunded' => $isOverfunded,
            'is_budget_valid' => $isBudgetValid,
        ];
    }

    private static function getBudget(Project $project): float
    {
        if (method_exists($project, 'budgetRevisions')) {
            $latestApprovedRevision = $project->budgetRevisions()
                ->where('status', 'approved')
                ->orderByDesc('approved_at')
                ->orderByDesc('id')
                ->first();

            if ($latestApprovedRevision) {
                return (float) $latestApprovedRevision->proposed_amount;
            }
        }

        return (float) ($project->budget ?? 0);
    }

    private static function getConfirmedOthers(Project $project, Financement $currentFinancement): float
    {
        return $project->financements()
            ->where('id', '!=', $currentFinancement->id)
            ->where('status', 'received')
            ->where('validation_status', 'approved')
            ->sum('amount');
    }

    private static function getAlertLevel(?float $coverageRate): string
    {
        if ($coverageRate === null) {
            return self::ALERTE_ROUGE;
        }

        if ($coverageRate < self::SEUIL_ROUGE) {
            return self::ALERTE_ROUGE;
        }

        if ($coverageRate < self::SEUIL_ORANGE) {
            return self::ALERTE_ORANGE;
        }

        if ($coverageRate < self::SEUIL_BLEU) {
            return self::ALERTE_BLEU;
        }

        return self::ALERTE_VERT;
    }

    private static function getAlertLabel(string $level): string
    {
        return match ($level) {
            self::ALERTE_ROUGE => 'Rouge',
            self::ALERTE_ORANGE => 'Orange',
            self::ALERTE_BLEU => 'Bleu',
            self::ALERTE_VERT => 'Vert',
            default => 'Rouge',
        };
    }

    private static function getAlertMessage(
        string $level,
        ?float $coverageRate,
        bool $isOverfunded,
        float $budget,
        float $projectedTotal,
        float $remainingToFund
    ): string {
        if ($isOverfunded) {
            return 'Attention : le total prévisionnel dépasse le budget du projet (surfinancement).';
        }

        if ($coverageRate === null) {
            return 'Budget du projet nul ou invalide : impossible de calculer la couverture.';
        }

        return match ($level) {
            self::ALERTE_ROUGE => 'Couverture financière très insuffisante (moins de 10 % du budget).',
            self::ALERTE_ORANGE => 'Couverture financière faible (entre 10 % et 30 % du budget).',
            self::ALERTE_BLEU => 'Couverture financière partiellement acceptable (entre 30 % et 50 % du budget).',
            self::ALERTE_VERT => 'Couverture financière favorable (50 % ou plus du budget).',
            default => 'Couverture financière très insuffisante.',
        };
    }
}
