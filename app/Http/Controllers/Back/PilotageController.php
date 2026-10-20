<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\DecisionHistory;
use App\Models\Financement;
use App\Models\Project;
use App\Models\User;
use App\Services\FinancementCoverageService;
use App\Services\ProjectFinancialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PilotageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Chargement anticipé des relations pour éviter le N+1
        $projects = Project::with(['financements', 'user', 'decisionHistories.user', 'budgetRevisions'])->get();
        $financements = Financement::with(['project', 'user', 'decisionHistories.user'])->get();

        /* ============================================================
           KPIs du dashboard Admin
           ============================================================ */
        $totalProjects = $projects->count();
        $projectsPending = $projects->where('validation_status', 'pending_approval')->count();
        $projectsApproved = $projects->where('validation_status', 'approved')->count();
        $projectsRejected = $projects->where('validation_status', 'rejected')->count();

        $totalFinancements = $financements->count();
        $financementsPending = $financements->where('validation_status', 'pending_approval')->count();
        $financementsApproved = $financements->where('validation_status', 'approved')->count();
        $financementsRejected = $financements->where('validation_status', 'rejected')->count();

        // Totaux financiers (budget révisé, financements confirmés)
        $budgetTotal = 0.0;
        $confirmedFundingTotal = 0.0;
        $underfundedProjectsCount = 0;
        $blockedProjectsCount = 0;

        foreach ($projects as $project) {
            $budget = ProjectFinancialService::getRevisedBudget($project);
            $confirmed = ProjectFinancialService::getConfirmedFunding($project);
            $budgetTotal += $budget;
            $confirmedFundingTotal += $confirmed;

            if ($budget > 0 && $confirmed < $budget) {
                $underfundedProjectsCount++;
            }

            if (in_array($project->validation_status, ['changes_requested', 'rejected'], true)) {
                $blockedProjectsCount++;
            }
        }

        $remainingToFund = max(0, $budgetTotal - $confirmedFundingTotal);
        $globalCoverageRate = $budgetTotal > 0 ? round(($confirmedFundingTotal / $budgetTotal) * 100, 2) : null;

        /* ============================================================
           Vue Projets
           ============================================================ */
        // Répartition par statut métier
        $projectsByStatus = $projects->groupBy('status')->map(fn ($group) => $group->count())->all();

        // Répartition par statut de validation
        $projectsByValidation = $projects->groupBy('validation_status')->map(fn ($group) => $group->count())->all();

        // Projets sous-financés (detail)
        $underfundedProjects = $projects->filter(function ($project) use ($budgetTotal) {
            $budget = ProjectFinancialService::getRevisedBudget($project);
            $confirmed = ProjectFinancialService::getConfirmedFunding($project);
            return $budget > 0 && $confirmed < $budget;
        })->values()->take(10);

        // Projets bloqués (validation changments_requested ou rejected)
        $blockedProjects = $projects->filter(function ($project) {
            return in_array($project->validation_status, ['changes_requested', 'rejected'], true);
        })->values()->take(10);

        // Évolution des soumissions dans le temps (12 derniers mois)
        $submissionsByMonth = $this->getSubmissionsByMonth($projects);

        /* ============================================================
           Vue Financements
           ============================================================ */
$montantsApprouves = $financements->where('validation_status', 'approved')->sum('amount');
$montantsRejetes = $financements->where('validation_status', 'rejected')->sum('amount');
$financementsPendingAmount = $financements->where('validation_status', 'pending_approval')->sum('amount');

        // Répartition des alertes financières (niveaus de couverture par financement pending)
        $alertDistribution = [
            'rouge' => 0,
            'orange' => 0,
            'bleu' => 0,
            'vert' => 0,
        ];

        foreach ($financements->where('validation_status', 'pending_approval') as $financement) {
            $coverage = FinancementCoverageService::calculate($financement);
            $alertDistribution[$coverage['alert_level']]++;
        }

        /* ============================================================
           Vue Validations
           ============================================================ */
        // Demandes en attente (projets + financements)
        $pendingValidations = [];
        foreach ($projects->where('validation_status', 'pending_approval') as $project) {
            $pendingValidations[] = [
                'type' => 'projet',
                'title' => $project->name,
                'resource_id' => $project->id,
                'submitted_at' => $this->getLastSubmitDate($project->decisionHistories),
                'url' => route('projects.show', $project),
            ];
        }
        foreach ($financements->where('validation_status', 'pending_approval') as $financement) {
            $pendingValidations[] = [
                'type' => 'financement',
                'title' => $financement->source,
                'resource_id' => $financement->id,
                'submitted_at' => $this->getLastSubmitDate($financement->decisionHistories),
                'url' => route('financements.show', $financement),
            ];
        }
        usort($pendingValidations, fn ($a, $b) => ($a['submitted_at'] ?? 0) <=> ($b['submitted_at'] ?? 0));

        // Demandes anciennes (plus de 7 jours en attente)
        $oldPendingValidations = array_filter($pendingValidations, function ($item) {
            if (!$item['submitted_at']) {
                return false;
            }
            return $item['submitted_at'] < now()->subDays(7);
        });

        // Durée moyenne de traitement des demandes terminées
        $avgProcessingTime = $this->getAverageProcessingTime($projects, $financements);

        // Taux d'approbation et de rejet
        $totalDecidedProjects = $projectsApproved + $projectsRejected;
        $totalDecidedFinancements = $financementsApproved + $financementsRejected;
        $totalDecided = $totalDecidedProjects + $totalDecidedFinancements;

        $approvalRate = $totalDecided > 0 ? round(($projectsApproved + $financementsApproved) / $totalDecided * 100, 2) : null;
        $rejectionRate = $totalDecided > 0 ? round(($projectsRejected + $financementsRejected) / $totalDecided * 100, 2) : null;

        /* ============================================================
           Alertes financières et activité récente
           ============================================================ */
        $financialAlerts = $this->getFinancialAlerts($projects);

        // Activité récente : 10 dernières décisions
        $recentDecisions = DecisionHistory::with(['user'])
            ->whereIn('resource_type', ['project', 'financement'])
            ->whereNotNull('old_status')
            ->latest('created_at')
            ->take(10)
            ->get();

        // Notifications non lues
        $unreadNotifications = $user->unreadNotifications()->take(5)->get();

        // Préparation des données pour la vue (éviter les appels app() et fn() dans Blade)
        $projectsByValidationLabels = array_map(fn ($k) => ucfirst(str_replace('_', ' ', $k)), array_keys($projectsByValidation));

        $projectsData = [];
        foreach ($projects as $project) {
            $revisedBudget = ProjectFinancialService::getRevisedBudget($project);
            $confirmed = ProjectFinancialService::getConfirmedFunding($project);
            $deficit = max(0, $revisedBudget - $confirmed);
            $status = $revisedBudget > 0 && $confirmed < $revisedBudget ? 'underfunded' : 'ok';
            $projectsData[] = [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'validation_status' => $project->validation_status,
                'validation_comment' => $project->validation_comment,
                'revised_budget' => $revisedBudget,
                'confirmed' => $confirmed,
                'deficit' => $deficit,
                'is_underfunded' => $status === 'underfunded',
                'url' => route('projects.show', $project),
            ];
        }

        return view('pilotage.index', [
            'user' => $user,
            // KPIs
            'totalProjects' => $totalProjects,
            'projectsPending' => $projectsPending,
            'projectsApproved' => $projectsApproved,
            'projectsRejected' => $projectsRejected,
            'totalFinancements' => $totalFinancements,
            'financementsPending' => $financementsPending,
            'financementsApproved' => $financementsApproved,
            'financementsRejected' => $financementsRejected,
            'financementsPendingAmount' => $financementsPendingAmount,
            'budgetTotal' => $budgetTotal,
            'confirmedFundingTotal' => $confirmedFundingTotal,
            'remainingToFund' => $remainingToFund,
            'globalCoverageRate' => $globalCoverageRate,
            'underfundedProjectsCount' => $underfundedProjectsCount,
            'blockedProjectsCount' => $blockedProjectsCount,
            // Raw collections for detail tables
            'projects' => $projects,
            'projectsData' => $projectsData,
            'financements' => $financements,
            // Vue projets
            'projectsByStatus' => $projectsByStatus,
            'projectsByValidation' => $projectsByValidation,
            'projectsByValidationLabels' => $projectsByValidationLabels,
            'underfundedProjects' => $underfundedProjects,
            'blockedProjects' => $blockedProjects,
            'submissionsByMonth' => $submissionsByMonth,
            // Vue financements
            'montantsApprouves' => $montantsApprouves,
            'montantsRejetes' => $montantsRejetes,
            'alertDistribution' => $alertDistribution,
            // Vue validations
            'pendingValidations' => array_slice($pendingValidations, 0, 10),
            'oldPendingValidations' => array_values($oldPendingValidations),
            'avgProcessingTime' => $avgProcessingTime,
            'approvalRate' => $approvalRate,
            'rejectionRate' => $rejectionRate,
            'totalDecided' => $totalDecided,
            // Alertes et activité
            'financialAlerts' => $financialAlerts,
            'recentDecisions' => $recentDecisions,
            'unreadNotifications' => $unreadNotifications,
        ]);
    }

    /**
     * Nombre de soumissions par mois (12 derniers mois), basé sur created_at des projets/financements.
     */
    private function getSubmissionsByMonth($projects): array
    {
        $months = [];
        $counts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $months[] = now()->subMonths($i)->format('M Y');
            $counts[] = 0;
        }

        foreach ($projects as $project) {
            $monthKey = $project->created_at?->format('Y-m');
            if ($monthKey) {
                $index = array_search($monthKey, array_map(fn ($m) => now()->parse($m)->format('Y-m'), $months), true);
                // Simplification: on utilise le mois de création directement
            }
        }

        // Recalcul correct: indexer par mois de création
        $monthCounts = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthCounts[now()->subMonths($i)->format('Y-m')] = 0;
        }

        foreach ($projects as $project) {
            $mk = $project->created_at?->format('Y-m');
            if ($mk && isset($monthCounts[$mk])) {
                $monthCounts[$mk]++;
            }
        }

        return [
            'labels' => array_values(array_map(fn ($m) => now()->parse($m)->format('M Y'), array_keys($monthCounts))),
            'data' => array_values($monthCounts),
        ];
    }

    /**
     * Date de la dernière soumission (action_type = 'submit') pour une ressource.
     */
    private function getLastSubmitDate($decisionHistories): ?string
    {
        $submit = $decisionHistories->where('action_type', 'submit')->last();
        return $submit ? $submit->created_at->toISOString() : null;
    }

    /**
     * Durée moyenne de traitement (en heures) des demandes terminées (approuvées ou rejetées).
     * Exclut les demandes toujours en attente.
     * Formule : différence entre la décision (approve/reject) et la soumission précédente (submit).
     */
    private function getAverageProcessingTime($projects, $financements): ?float
    {
        $durations = [];

        foreach ($projects as $project) {
            $duration = $this->computeProcessingDuration($project->decisionHistories);
            if ($duration !== null) {
                $durations[] = $duration;
            }
        }

        foreach ($financements as $financement) {
            $duration = $this->computeProcessingDuration($financement->decisionHistories);
            if ($duration !== null) {
                $durations[] = $duration;
            }
        }

        if (empty($durations)) {
            return null;
        }

        return round(array_sum($durations) / count($durations), 2);
    }

    private function computeProcessingDuration($decisionHistories): ?float
    {
        // Trouver la dernière décision (approve ou reject)
        $decision = $decisionHistories->whereIn('action_type', ['approve', 'reject'])->last();
        if (!$decision) {
            return null;
        }

        // Trouver la soumission précédente (submit) avant cette décision
        $submit = $decisionHistories
            ->where('action_type', 'submit')
            ->where('created_at', '<', $decision->created_at)
            ->last();

        if (!$submit) {
            return null;
        }

        return $decision->created_at->diffInHours($submit->created_at);
    }

    /**
     * Alertes financières pour les projets (sous-financement, dépassement, etc.)
     */
    private function getFinancialAlerts($projects): array
    {
        $alerts = [];

        foreach ($projects as $project) {
            $budget = ProjectFinancialService::getRevisedBudget($project);
            $confirmed = ProjectFinancialService::getConfirmedFunding($project);
            $engaged = ProjectFinancialService::getEngagedExpenses($project);

            if ($budget > 0 && $engaged > $budget) {
                $alerts[] = [
                    'type' => 'budget_exceeded',
                    'title' => "Dépassement budgétaire : {$project->name}",
                    'description' => "Dépenses engagées (" . number_format($engaged, 0, ',', ' ') . " €) supérieures au budget (" . number_format($budget, 0, ',', ' ') . " €).",
                    'url' => route('projects.show', $project),
                ];
            }

            if ($budget > 0 && $confirmed < $budget) {
                $deficit = $budget - $confirmed;
                $alerts[] = [
                    'type' => 'insufficient_funding',
                    'title' => "Sous-financement : {$project->name}",
                    'description' => "Financement confirmé (" . number_format($confirmed, 0, ',', ' ') . " €) inférieur au budget (" . number_format($budget, 0, ',', ' ') . " €). Reste : " . number_format($deficit, 0, ',', ' ') . " €.",
                    'url' => route('projects.show', $project),
                ];
            }
        }

        usort($alerts, fn ($a, $b) => strcmp($a['type'], $b['type']));

        return array_slice($alerts, 0, 8);
    }
}
