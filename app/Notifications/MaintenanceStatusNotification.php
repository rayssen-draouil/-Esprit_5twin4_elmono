<?php

namespace App\Notifications;

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class MaintenanceStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Maintenance $maintenance,
        public string $event, // 'accepted', 'prolonged', 'completed'
        public ?User $technician = null,
        public array $extraData = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $techName = $this->technician?->name ?? ($this->maintenance->technician?->name ?? 'Le technicien');
        $ref = $this->maintenance->reference_code ?? ('MNT-#' . $this->maintenance->id);
        $infraName = $this->maintenance->infrastructure?->name ?? 'l\'ouvrage';

        [$title, $message] = match ($this->event) {
            'accepted' => [
                "⚡ Intervention acceptée : {$ref}",
                "Le technicien {$techName} a accepté l'ordre d'intervention sur {$infraName}. L'opération passe en cours d'exécution (Étape 2/3).",
            ],
            'prolonged' => [
                "⏳ Prolongation d'intervention : {$ref}",
                "Le technicien {$techName} a reporté l'échéance de " . ($this->extraData['days'] ?? 1) . " jour(s) pour {$infraName}. Motif : " . ($this->extraData['reason'] ?? 'Non spécifié'),
            ],
            'completed' => [
                "✓ Intervention terminée : {$ref}",
                "Le technicien {$techName} a achevé les travaux sur {$infraName}. Santé de l'infrastructure rétablie à " . ($this->extraData['health_score'] ?? 100) . "%. (Étape 3/3).",
            ],
            default => [
                "Mise à jour d'intervention : {$ref}",
                "Mise à jour enregistrée pour l'opération {$ref} par {$techName}.",
            ],
        };

        return new DatabaseMessage([
            'title' => $title,
            'message' => $message,
            'event' => $this->event,
            'type' => 'maintenance_' . $this->event,
            'resource_type' => 'maintenance',
            'resource_id' => $this->maintenance->id,
            'resource_url' => route('maintenances.show', $this->maintenance),
            'actioned_by' => $techName,
            'status' => $this->maintenance->status,
            'priority' => $this->maintenance->priority,
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
