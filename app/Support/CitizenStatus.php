<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Signalement;

/**
 * Libellés et frises chronologiques destinés au grand public.
 *
 * Ce helper ne contient que des informations citoyennes : aucun identifiant
 * interne, aucune note de gestion, aucune donnée personnelle.
 */
final class CitizenStatus
{
    public const SIGNALEMENT_LABELS = [
        'new' => 'Signalement reçu',
        'received' => 'Signalement reçu',
        'reviewed' => 'En vérification',
        'under_review' => 'En vérification',
        'in_progress' => 'Intervention en cours',
        'resolved' => 'Résolu',
        'closed' => 'Clôturé',
    ];

    public const INCIDENT_LABELS = [
        'reported' => 'Ouvert',
        'in_progress' => 'En cours',
        'resolved' => 'Résolu',
        'closed' => 'Clôturé',
    ];

    public static function signalementLabel(?string $status): string
    {
        return self::SIGNALEMENT_LABELS[(string) $status] ?? 'En cours de traitement';
    }

    public static function incidentLabel(?string $status): string
    {
        return self::INCIDENT_LABELS[(string) $status] ?? 'En cours de traitement';
    }

    /**
     * Frise de suivi d'un signalement citoyen.
     *
     * @return array<int, array{label: string, state: string, date: ?string}>
     */
    public static function signalementTimeline(Signalement $signalement): array
    {
        $status = (string) $signalement->status;
        $incident = $signalement->incident;
        $incidentStatus = (string) ($incident?->status ?? '');

        $reviewed = in_array($status, ['reviewed', 'under_review', 'in_progress', 'resolved', 'closed'], true)
            || $incident !== null;
        $inProgress = in_array($incidentStatus, ['in_progress', 'resolved', 'closed'], true)
            || in_array($status, ['in_progress', 'resolved', 'closed'], true);
        $resolved = $incident?->resolved_at !== null
            || in_array($incidentStatus, ['resolved', 'closed'], true)
            || in_array($status, ['resolved', 'closed'], true);

        $steps = [
            [
                'label' => 'Signalement reçu',
                'state' => 'done',
                'date' => $signalement->reported_at?->format('d/m/Y à H:i'),
            ],
            [
                'label' => 'Vérification par nos équipes',
                'state' => $resolved || $inProgress ? 'done' : ($reviewed ? 'current' : 'pending'),
                'date' => null,
            ],
            [
                'label' => 'Intervention sur le terrain',
                'state' => $resolved ? 'done' : ($inProgress ? 'current' : 'pending'),
                'date' => $incident?->reported_at?->format('d/m/Y à H:i'),
            ],
            [
                'label' => 'Dossier résolu',
                'state' => $resolved ? 'done' : 'pending',
                'date' => $incident?->resolved_at?->format('d/m/Y à H:i')
                    ?? ($resolved && $signalement->reported_at ? $signalement->reported_at->format('d/m/Y') : null),
            ],
        ];

        return $steps;
    }

    /**
     * Frise de traitement d'une alerte publique.
     *
     * @return array<int, array{label: string, state: string, date: ?string}>
     */
    public static function alertTimeline(Alert $alert): array
    {
        $status = $alert->status();

        return [
            [
                'label' => 'Alerte signalée',
                'state' => 'done',
                'date' => $alert->created_at?->format('d/m/Y à H:i'),
            ],
            [
                'label' => 'Prise en charge',
                'state' => $status === 'open' ? 'current' : 'done',
                'date' => $alert->acknowledged_at?->format('d/m/Y à H:i'),
            ],
            [
                'label' => 'Situation rétablie',
                'state' => $status === 'resolved' ? 'done' : ($status === 'acknowledged' ? 'current' : 'pending'),
                'date' => $alert->resolved_at?->format('d/m/Y à H:i'),
            ],
        ];
    }
}
