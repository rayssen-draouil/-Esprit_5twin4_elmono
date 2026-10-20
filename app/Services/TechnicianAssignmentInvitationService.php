<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\MaintenanceAssignmentInvitation;
use App\Models\Technicien;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TechnicianAssignmentInvitationService
{
    protected BrevoEmailService $brevoService;

    public function __construct(BrevoEmailService $brevoService)
    {
        $this->brevoService = $brevoService;
    }

    /**
     * Create a pending assignment invitation and send transactional email via Brevo.
     *
     * @return array{
     *     success: bool,
     *     invitation: ?MaintenanceAssignmentInvitation,
     *     email_sent: bool,
     *     message: string,
     *     email_error: ?string
     * }
     */
    public function createAndSendInvitation(
        int $maintenanceId,
        int $technicianId,
        ?int $managerId = null,
        int $expiresInHours = 72
    ): array {
        return DB::transaction(function () use ($maintenanceId, $technicianId, $managerId, $expiresInHours) {
            $maintenance = Maintenance::with(['infrastructure.zone', 'technician'])->lockForUpdate()->findOrFail($maintenanceId);
            $technician = Technicien::lockForUpdate()->findOrFail($technicianId);

            // 1. Validate maintenance state
            if ($maintenance->technician_assignment_locked) {
                return [
                    'success' => false,
                    'invitation' => null,
                    'email_sent' => false,
                    'message' => "L'intervention {$maintenance->reference_code} est déjà verrouillée avec le technicien {$maintenance->technician?->name}.",
                    'email_error' => null,
                ];
            }

            if (in_array($maintenance->status, ['completed', 'cancelled'], true)) {
                return [
                    'success' => false,
                    'invitation' => null,
                    'email_sent' => false,
                    'message' => "Impossible d'affecter un technicien à une intervention au statut {$maintenance->display_status_label}.",
                    'email_error' => null,
                ];
            }

            // 2. Validate technician eligibility
            if ($technician->status === 'inactive') {
                return [
                    'success' => false,
                    'invitation' => null,
                    'email_sent' => false,
                    'message' => "Le technicien {$technician->name} est inactif et ne peut recevoir d'invitation.",
                    'email_error' => null,
                ];
            }

            if (empty($technician->email) || !filter_var($technician->email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'invitation' => null,
                    'email_sent' => false,
                    'message' => "Le technicien {$technician->name} ne possède pas d'adresse email valide.",
                    'email_error' => null,
                ];
            }

            // 3. Cancel any previous pending invitations for a DIFFERENT technician on this maintenance
            MaintenanceAssignmentInvitation::where('maintenance_id', $maintenance->id)
                ->where('technician_id', '!=', $technician->id)
                ->where('status', MaintenanceAssignmentInvitation::STATUS_PENDING)
                ->update(['status' => MaintenanceAssignmentInvitation::STATUS_CANCELLED]);

            // 4. Check for existing active pending invitation for this EXACT technician (idempotency)
            $existing = MaintenanceAssignmentInvitation::where('maintenance_id', $maintenance->id)
                ->where('technician_id', $technician->id)
                ->where('status', MaintenanceAssignmentInvitation::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest()
                ->first();

            // If an active invitation already exists and was delivered, return without duplicate
            if ($existing && $existing->email_delivery_status === 'sent') {
                return [
                    'success' => true,
                    'invitation' => $existing,
                    'email_sent' => true,
                    'message' => "Une invitation est déjà en attente de réponse pour {$technician->name}.",
                    'email_error' => null,
                ];
            }

            // 5. Generate secure high-entropy token and hash
            $rawToken = MaintenanceAssignmentInvitation::generateRawToken();
            $tokenHash = MaintenanceAssignmentInvitation::hashToken($rawToken);

            $invitation = MaintenanceAssignmentInvitation::create([
                'maintenance_id' => $maintenance->id,
                'technician_id' => $technician->id,
                'manager_id' => $managerId,
                'token_hash' => $tokenHash,
                'status' => MaintenanceAssignmentInvitation::STATUS_PENDING,
                'invited_at' => now(),
                'expires_at' => now()->addHours($expiresInHours),
                'email_delivery_status' => 'pending',
            ]);

            // Temporarily assign technician field on maintenance as proposed (unlocked)
            $maintenance->technician_id = $technician->id;
            $maintenance->technician_assignment_locked = false;
            $maintenance->save();

            // 6. Send transactional email via Brevo
            $emailResult = $this->brevoService->sendAssignmentInvitation($invitation, $rawToken);

            if ($emailResult['success']) {
                $invitation->update([
                    'email_delivery_status' => 'sent',
                    'brevo_message_id' => $emailResult['message_id'],
                    'email_error' => null,
                ]);

                Log::info('Assignment invitation created and sent via Brevo', [
                    'invitation_id' => $invitation->id,
                    'maintenance_id' => $maintenance->id,
                    'technician_id' => $technician->id,
                    'message_id' => $emailResult['message_id'],
                ]);

                return [
                    'success' => true,
                    'invitation' => $invitation,
                    'email_sent' => true,
                    'message' => "Invitation transmise avec succès à {$technician->name} ({$technician->email}). L'affectation sera verrouillée dès acceptation.",
                    'email_error' => null,
                ];
            }

            // Safe error handling on email failure without losing invitation record
            $invitation->update([
                'email_delivery_status' => 'failed',
                'email_error' => $emailResult['error'],
            ]);

            Log::warning('Assignment invitation created but Brevo delivery failed', [
                'invitation_id' => $invitation->id,
                'maintenance_id' => $maintenance->id,
                'technician_id' => $technician->id,
                'error' => $emailResult['error'],
            ]);

            return [
                'success' => true, // Invitation recorded, but email delivery flagged
                'invitation' => $invitation,
                'email_sent' => false,
                'message' => "L'invitation a été enregistrée dans AquaSecure mais l'envoi de l'email via Brevo a échoué : {$emailResult['error']}. Vous pouvez retenter l'envoi.",
                'email_error' => $emailResult['error'],
            ];
        });
    }

    /**
     * Locate an invitation by its raw token and check its validity.
     */
    public function findByRawToken(string $rawToken): ?MaintenanceAssignmentInvitation
    {
        $hash = MaintenanceAssignmentInvitation::hashToken($rawToken);

        return MaintenanceAssignmentInvitation::with(['maintenance.infrastructure.zone', 'technician'])
            ->where('token_hash', $hash)
            ->first();
    }

    /**
     * Explicit acceptance action by the technician (POST protected).
     *
     * @return array{success: bool, message: string, maintenance: ?Maintenance}
     */
    public function acceptInvitation(string $rawToken): array
    {
        return DB::transaction(function () use ($rawToken) {
            $hash = MaintenanceAssignmentInvitation::hashToken($rawToken);

            $invitation = MaintenanceAssignmentInvitation::where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => "Jeton d'invitation introuvable ou invalide.",
                    'maintenance' => null,
                ];
            }

            if ($invitation->hasExpired()) {
                $invitation->update(['status' => MaintenanceAssignmentInvitation::STATUS_EXPIRED]);
                return [
                    'success' => false,
                    'message' => "Cette invitation d'intervention a expiré.",
                    'maintenance' => null,
                ];
            }

            if ($invitation->status === MaintenanceAssignmentInvitation::STATUS_ACCEPTED) {
                // Idempotent: already accepted by this technician
                return [
                    'success' => true,
                    'message' => "Vous avez déjà accepté cette intervention auparavant.",
                    'maintenance' => $invitation->maintenance,
                ];
            }

            if ($invitation->status !== MaintenanceAssignmentInvitation::STATUS_PENDING) {
                return [
                    'success' => false,
                    'message' => "Cette invitation n'est plus active (statut : {$invitation->display_status_label}).",
                    'maintenance' => null,
                ];
            }

            $maintenance = Maintenance::with('infrastructure')->lockForUpdate()->findOrFail($invitation->maintenance_id);
            $technician = Technicien::lockForUpdate()->findOrFail($invitation->technician_id);

            // Verify maintenance is not already locked with another technician
            if ($maintenance->technician_assignment_locked && (int) $maintenance->technician_id !== (int) $technician->id) {
                return [
                    'success' => false,
                    'message' => "Cette intervention a déjà été acceptée et affectée à un autre technicien.",
                    'maintenance' => null,
                ];
            }

            // Apply acceptance updates
            $now = now();
            $invitation->status = MaintenanceAssignmentInvitation::STATUS_ACCEPTED;
            $invitation->responded_at = $now;
            $invitation->save();

            // Lock the assignment and transition status to in_progress
            $maintenance->technician_id = $technician->id;
            $maintenance->technician_assignment_locked = true;
            $maintenance->assignment_accepted_at = $now;
            $maintenance->status = 'in_progress';
            if (empty($maintenance->started_at)) {
                $maintenance->started_at = $now;
            }
            $maintenance->save();

            // Synchronize infrastructure status
            $infra = $maintenance->infrastructure;
            if ($infra && $infra->status === 'operational') {
                $infra->status = 'maintenance';
                $infra->save();
            }

            // Set technician to busy
            if ($technician->status === 'available') {
                $technician->status = 'busy';
                $technician->save();
            }

            // Cancel any other remaining pending invitations on this maintenance
            MaintenanceAssignmentInvitation::where('maintenance_id', $maintenance->id)
                ->where('id', '!=', $invitation->id)
                ->where('status', MaintenanceAssignmentInvitation::STATUS_PENDING)
                ->update(['status' => MaintenanceAssignmentInvitation::STATUS_CANCELLED]);

            // Dispatch in-app notification & transactional email to the manager
            $managers = collect();
            if ($invitation->manager_id && ($m = User::find($invitation->manager_id))) {
                $managers->push($m);
            }
            if ($managers->isEmpty()) {
                $managers = User::whereIn('role', ['manager', 'gestionnaire', 'admin'])->get();
            }

            foreach ($managers as $mgr) {
                try {
                    $mgr->notify(new \App\Notifications\MaintenanceStatusNotification($maintenance, 'accepted', $technician));
                } catch (\Throwable $e) {
                    Log::warning('Failed sending in-app notification for accepted maintenance: ' . $e->getMessage());
                }

                try {
                    $this->brevoService->sendTechnicianAcceptedNotificationToManager($maintenance, $technician, $mgr);
                } catch (\Throwable $e) {
                    Log::warning('Failed sending email to manager on technician acceptance: ' . $e->getMessage());
                }
            }

            Log::info('Technician accepted maintenance invitation', [
                'invitation_id' => $invitation->id,
                'maintenance_id' => $maintenance->id,
                'technician_id' => $technician->id,
            ]);

            return [
                'success' => true,
                'message' => "Intervention « {$maintenance->reference_code} » acceptée avec succès ! Le statut est désormais « En cours d'exécution ».",
                'maintenance' => $maintenance,
            ];
        });
    }

    /**
     * Explicit decline action by the technician.
     *
     * @return array{success: bool, message: string}
     */
    public function declineInvitation(string $rawToken, ?string $reason = null): array
    {
        return DB::transaction(function () use ($rawToken, $reason) {
            $hash = MaintenanceAssignmentInvitation::hashToken($rawToken);

            $invitation = MaintenanceAssignmentInvitation::where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => "Jeton d'invitation introuvable.",
                ];
            }

            if ($invitation->status !== MaintenanceAssignmentInvitation::STATUS_PENDING) {
                return [
                    'success' => false,
                    'message' => "Cette invitation ne peut plus être déclinée (statut : {$invitation->display_status_label}).",
                ];
            }

            $invitation->status = MaintenanceAssignmentInvitation::STATUS_DECLINED;
            $invitation->responded_at = now();
            $invitation->decline_reason = $reason;
            $invitation->save();

            // Clear proposed technician if assignment was not locked
            $maintenance = Maintenance::find($invitation->maintenance_id);
            if ($maintenance && !$maintenance->technician_assignment_locked) {
                $maintenance->technician_id = null;
                $maintenance->save();
            }

            Log::info('Technician declined maintenance invitation', [
                'invitation_id' => $invitation->id,
                'maintenance_id' => $invitation->maintenance_id,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => "Vous avez décliné l'intervention « {$maintenance?->reference_code} ». Le gestionnaire en a été informé.",
            ];
        });
    }
}
