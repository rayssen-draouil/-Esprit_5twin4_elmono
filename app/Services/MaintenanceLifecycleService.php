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
    public function submitCompletion(Maintenance $maintenance, Technicien $technician, array $reportData): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $technician, $reportData) {
            $locked = Maintenance::lockForUpdate()->findOrFail($maintenance->id);

            // 1. Authorization & assignment verification
            if ((int) $locked->technician_id !== (int) $technician->id) {
                throw new \DomainException("Vous n'êtes pas le technicien affecté à cette opération de maintenance.");
            }

            // 2. Lifecycle state verification
            if ($locked->status !== 'in_progress') {
                throw new \DomainException("L'intervention doit être en cours d'exécution pour être signalée comme terminée (statut actuel : {$locked->display_status_label}).");
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
            $locked->completion_report = $report;
            $locked->status = 'awaiting_validation';
            $locked->manager_correction_notes = null; // Clear previous correction notes

            if (!empty($reportData['duration_hours']) && is_numeric($reportData['duration_hours'])) {
                $locked->duration_hours = (float) $reportData['duration_hours'];
            }

            $locked->save();

            Log::info('Technician submitted completion for maintenance', [
                'maintenance_id' => $locked->id,
                'reference_code' => $locked->reference_code,
                'technician_id' => $technician->id,
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
