<?php

namespace App\Services;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaintenanceLifecycleService
{
    protected BrevoEmailService $brevoService;

    public function __construct(BrevoEmailService $brevoService)
    {
        $this->brevoService = $brevoService;
    }

    /**
     * Submit completion by the assigned technician.
     * Transitions: in_progress -> awaiting_validation
     *
     * @param array{
     *     result: string,
     *     duration_hours?: ?float,
     *     completed_at?: ?string,
     *     parts_replaced?: ?string,
     *     tests_verification?: ?string,
     *     remaining_issues?: ?string,
     *     recommendations?: ?string
     * } $reportData
     */
    /**
     * Submit completion by the assigned technician.
     * Transitions: in_progress -> completed (moves from step 2 to step 3)
     * Automatically updates infrastructure health to 100% if all maintenances done,
     * and sends push/in-app notifications and email alerts to the manager.
     */
    public function submitCompletion(Maintenance $maintenance, User $technician, array $reportData): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $technician, $reportData) {
            $locked = Maintenance::with(['infrastructure', 'technician'])->lockForUpdate()->findOrFail($maintenance->id);

            // 1. Authorization & assignment verification
            if ((int) $locked->technician_id !== (int) $technician->id) {
                throw new \DomainException("Vous n'êtes pas le technicien affecté à cette opération de maintenance.");
            }

            // 2. Lifecycle state verification
            if (!in_array($locked->status, ['in_progress', 'awaiting_validation'])) {
                throw new \DomainException("L'intervention doit être en cours d'exécution pour être confirmée comme terminée (statut actuel : {$locked->display_status_label}).");
            }

            // 3. Technical report requirement
            $resultText = trim($reportData['result'] ?? '');
            if (empty($resultText)) {
                throw new \InvalidArgumentException("Le compte-rendu technique détaillé des travaux est obligatoire.");
            }

            $submittedAt = now();
            if (!empty($reportData['completed_at'])) {
                $parsed = strtotime($reportData['completed_at']);
                if ($parsed && $parsed <= time()) {
                    $submittedAt = now()->setTimestamp($parsed);
                }
            }

            $report = [
                'work_performed' => $resultText,
                'findings' => $reportData['findings'] ?? null,
                'parts_replaced' => $reportData['parts_replaced'] ?? null,
                'tests_verification' => $reportData['tests_verification'] ?? null,
                'remaining_issues' => $reportData['remaining_issues'] ?? null,
                'recommendations' => $reportData['recommendations'] ?? null,
                'submitted_by_technician' => $technician->name,
                'submitted_at' => $submittedAt->toIso8601String(),
            ];

            $locked->result = $resultText;
            $locked->completion_submitted_at = $submittedAt;
            $locked->completed_at = $submittedAt;
            $locked->performed_at = $submittedAt;
            $locked->completion_report = $report;
            $locked->status = 'completed'; // Step 2 to Step 3
            $locked->manager_correction_notes = null;

            if (!empty($reportData['duration_hours']) && is_numeric($reportData['duration_hours'])) {
                $locked->duration_hours = (float) $reportData['duration_hours'];
            }

            $locked->save();

            // 4. Update Infrastructure health and condition
            $healthScore = 100;
            $infra = $locked->infrastructure;
            if ($infra) {
                $infra->last_maintenance_date = $submittedAt->format('Y-m-d');
                if (!$infra->next_maintenance_date || $infra->next_maintenance_date->isPast()) {
                    $infra->next_maintenance_date = now()->addMonths(6)->format('Y-m-d');
                }

                // Check if any other active/pending maintenances remain on this infrastructure
                $remainingActive = Maintenance::where('infrastructure_id', $infra->id)
                    ->whereIn('status', ['in_progress', 'planned', 'reported', 'awaiting_validation'])
                    ->where('id', '!=', $locked->id)
                    ->count();

                if ($remainingActive === 0) {
                    $infra->status = 'operational';
                    $infra->condition = 'excellent';

                    // Update AI health analysis cache to 100%
                    if (!empty($infra->ai_health_analysis) && is_array($infra->ai_health_analysis)) {
                        $aiData = $infra->ai_health_analysis;
                        $aiData['health_score'] = 100;
                        $aiData['overall_risk_level'] = 'low';
                        $aiData['condition_summary'] = "L'ensemble des travaux de maintenance sur cet ouvrage ont été réalisés et validés. Intégrité opérationnelle à 100%.";
                        $aiData['top_risk_factors'] = [];
                        $aiData['analyzed_at'] = now()->toIso8601String();
                        $aiData['analyzed_at_display'] = now()->format('d/m/Y à H:i');
                        $infra->ai_health_analysis = $aiData;
                    }
                    $infra->save();
                    $healthScore = 100;
                } else {
                    if ($infra->status === 'maintenance') {
                        $infra->status = 'operational';
                    }
                    $infra->save();
                    $healthScore = $infra->health_score;
                }
            }

            // 5. Free technician status if no other active assignments
            if ($technician->status === 'busy') {
                $otherActive = Maintenance::where('technician_id', $technician->id)
                    ->whereIn('status', ['in_progress', 'awaiting_validation'])
                    ->where('id', '!=', $locked->id)
                    ->count();

                if ($otherActive === 0) {
                    $technician->status = 'available';
                    $technician->save();
                }
            }

            // 6. Notify Manager(s) via In-App (Notifications Table) & Brevo Email
            $managers = User::whereIn('role', ['manager', 'gestionnaire', 'admin'])->get();
            foreach ($managers as $mgr) {
                try {
                    $mgr->notify(new \App\Notifications\MaintenanceStatusNotification($locked, 'completed', $technician, ['health_score' => $healthScore]));
                } catch (\Throwable $e) {
                    Log::warning('Failed sending in-app notification for completed maintenance: ' . $e->getMessage());
                }

                try {
                    $this->brevoService->sendTechnicianCompletedNotificationToManager($locked, $technician, $mgr, $healthScore);
                } catch (\Throwable $e) {
                    Log::warning('Failed sending completion email to manager: ' . $e->getMessage());
                }
            }

            Log::info('Technician completed maintenance and notified manager', [
                'maintenance_id' => $locked->id,
                'reference_code' => $locked->reference_code,
                'technician_id' => $technician->id,
                'health_score' => $healthScore,
            ]);

            return $locked;
        });
    }

    /**
     * Technician requests a prolongation / postponement of the maintenance.
     */
    public function prolongIntervention(Maintenance $maintenance, User $technician, int $days, string $reason): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $technician, $days, $reason) {
            $locked = Maintenance::with(['infrastructure', 'technician'])->lockForUpdate()->findOrFail($maintenance->id);

            // 1. Authorization & assignment verification
            if ((int) $locked->technician_id !== (int) $technician->id) {
                throw new \DomainException("Vous n'êtes pas le technicien affecté à cette opération de maintenance.");
            }

            // 2. State verification
            if (!in_array($locked->status, ['in_progress', 'planned'])) {
                throw new \DomainException("Seules les opérations en cours ou planifiées peuvent faire l'objet d'une prolongation.");
            }

            if ($days < 1 || $days > 60) {
                throw new \InvalidArgumentException("La durée de prolongation doit être comprise entre 1 et 60 jours.");
            }

            $reason = trim($reason);
            if (empty($reason)) {
                throw new \InvalidArgumentException("Veuillez préciser le motif de prolongation pour le gestionnaire.");
            }

            // 3. Calculate new scheduled date
            $baseDate = $locked->scheduled_at ? $locked->scheduled_at->copy() : now();
            if ($baseDate->isPast()) {
                $baseDate = now();
            }
            $newScheduledAt = $baseDate->addDays($days);

            $locked->scheduled_at = $newScheduledAt;
            $locked->prolonged_at = now();
            $locked->prolongation_days = ($locked->prolongation_days ?? 0) + $days;
            $locked->prolongation_reason = $reason;

            // Maintain history
            $history = $locked->prolongation_history ?? [];
            $history[] = [
                'days' => $days,
                'reason' => $reason,
                'prolonged_at' => now()->toIso8601String(),
                'new_scheduled_at' => $newScheduledAt->toIso8601String(),
                'technician_name' => $technician->name,
            ];
            $locked->prolongation_history = $history;
            $locked->save();

            // 4. Notify Manager(s) via In-App (Notifications Table) & Brevo Email
            $managers = User::whereIn('role', ['manager', 'gestionnaire', 'admin'])->get();
            foreach ($managers as $mgr) {
                try {
                    $mgr->notify(new \App\Notifications\MaintenanceStatusNotification($locked, 'prolonged', $technician, [
                        'days' => $days,
                        'reason' => $reason,
                    ]));
                } catch (\Throwable $e) {
                    Log::warning('Failed sending in-app notification for prolonged maintenance: ' . $e->getMessage());
                }

                try {
                    $this->brevoService->sendProlongationNotificationToManager($locked, $technician, $mgr, $days, $reason);
                } catch (\Throwable $e) {
                    Log::warning('Failed sending prolongation email to manager: ' . $e->getMessage());
                }
            }

            Log::info('Technician prolonged maintenance', [
                'maintenance_id' => $locked->id,
                'reference_code' => $locked->reference_code,
                'days' => $days,
                'new_scheduled_at' => $newScheduledAt->toIso8601String(),
            ]);

            return $locked;
        });
    }

    /**
     * Manager approves and validates technician completion.
     * Transitions: awaiting_validation -> completed
     */
    public function validateAndClose(Maintenance $maintenance, User $manager, ?string $validationNotes = null): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $manager, $validationNotes) {
            $locked = Maintenance::with(['technician', 'infrastructure'])->lockForUpdate()->findOrFail($maintenance->id);

            if ($locked->status !== 'awaiting_validation') {
                throw new \DomainException("Seules les opérations en attente de validation peuvent être clôturées définitivement (statut actuel : {$locked->display_status_label}).");
            }

            $now = now();
            $locked->status = 'completed';
            $locked->completed_at = $locked->completion_submitted_at ?? $now;
            $locked->performed_at = $locked->completion_submitted_at ?? $now;
            $locked->completion_validated_at = $now;
            $locked->completion_validated_by = $manager->id;
            $locked->manager_validation_notes = $validationNotes;
            $locked->save();

            // Synchronize Infrastructure status and dates
            $infra = $locked->infrastructure;
            if ($infra) {
                $infra->last_maintenance_date = $locked->completed_at->format('Y-m-d');
                if ($infra->status === 'maintenance') {
                    $infra->status = 'operational';
                }
                if ($locked->next_maintenance_date) {
                    $infra->next_maintenance_date = $locked->next_maintenance_date;
                }
                $infra->save();
            }

            // Free technician status if busy
            if ($locked->technician && $locked->technician->status === 'busy') {
                $activeCount = Maintenance::where('technician_id', $locked->technician->id)
                    ->whereIn('status', ['in_progress', 'awaiting_validation'])
                    ->where('id', '!=', $locked->id)
                    ->count();

                if ($activeCount === 0) {
                    $locked->technician->status = 'available';
                    $locked->technician->save();
                }
            }

            // Notify technician via Brevo transactional email
            if ($locked->technician) {
                try {
                    $this->brevoService->sendClosureNotification($locked, $locked->technician);
                } catch (\Throwable $e) {
                    Log::warning('Failed sending closure notification email', ['error' => $e->getMessage()]);
                }
            }

            Log::info('Maintenance validated and closed by manager', [
                'maintenance_id' => $locked->id,
                'manager_id' => $manager->id,
            ]);

            return $locked;
        });
    }

    /**
     * Manager requests corrections from the technician.
     * Transitions: awaiting_validation -> in_progress
     */
    public function requestCorrections(Maintenance $maintenance, User $manager, string $correctionNotes): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $manager, $correctionNotes) {
            $locked = Maintenance::with('technician')->lockForUpdate()->findOrFail($maintenance->id);

            if ($locked->status !== 'awaiting_validation') {
                throw new \DomainException("Une demande de correction ne peut être faite que sur une opération en attente de validation.");
            }

            if (empty(trim($correctionNotes))) {
                throw new \InvalidArgumentException("Veuillez préciser les motifs de correction ou les compléments demandés.");
            }

            $locked->status = 'in_progress';
            $locked->manager_correction_notes = $correctionNotes;
            $locked->save();

            if ($locked->technician) {
                try {
                    $this->brevoService->sendCorrectionsRequestedNotification($locked, $locked->technician, $correctionNotes);
                } catch (\Throwable $e) {
                    Log::warning('Failed sending corrections requested email', ['error' => $e->getMessage()]);
                }
            }

            Log::info('Manager requested corrections for maintenance', [
                'maintenance_id' => $locked->id,
                'manager_id' => $manager->id,
            ]);

            return $locked;
        });
    }

    /**
     * Guard against unauthorized technician reassignment when locked.
     */
    public function assertTechnicianCanBeChanged(Maintenance $maintenance, ?int $newTechnicianId): void
    {
        if ($maintenance->technician_assignment_locked && (int) $maintenance->technician_id !== (int) $newTechnicianId) {
            $techName = $maintenance->technician?->name ?? 'Le technicien';
            throw new \DomainException("L'affectation de cette intervention est verrouillée suite à l'acceptation par {$techName}. La réattribution ordinaire est bloquée.");
        }
    }
}
