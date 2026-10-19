<?php

namespace App\Models;

use App\Observers\AlertObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AlertObserver::class)]
class Alert extends Model
{
    use HasFactory;

    public const SEVERITIES = [
        'low' => 'Faible',
        'medium' => 'Moyen',
        'high' => 'Élevé',
        'critical' => 'Critique',
    ];

    /** Gravités produites par l'IA (classification information / avertissement / critique). */
    public const AI_SEVERITIES = [
        'info' => 'Information',
        'warning' => 'Avertissement',
        'critical' => 'Critique',
    ];

    /** Statuts du workflow de traitement (ouvert / acquitté / résolu). */
    public const STATUSES = [
        'open' => 'Ouverte',
        'acknowledged' => 'Acquittée',
        'resolved' => 'Résolue',
    ];

    /** Poids de tri par gravité (priorité décroissante). */
    public const SEVERITY_RANK = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    protected $fillable = [
        'zone_id',
        'incident_id',
        'type',
        'severity',
        'message',
        'read_at',
        'acknowledged_at',
        'resolved_at',
        'ai_severity',
        'ai_explanation',
        'ai_probable_cause',
        'ai_recommended_actions',
        'ai_group_key',
        'ai_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'ai_recommended_actions' => 'array',
            'ai_analyzed_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function getSeverityLabelAttribute(): string
    {
        return self::SEVERITIES[$this->severity] ?? $this->severity;
    }

    public function getAiSeverityLabelAttribute(): string
    {
        return self::AI_SEVERITIES[$this->ai_severity] ?? ($this->ai_severity ?? '—');
    }

    /** Statut courant du workflow : open / acknowledged / resolved. */
    public function status(): string
    {
        if ($this->resolved_at) {
            return 'resolved';
        }

        if ($this->acknowledged_at) {
            return 'acknowledged';
        }

        return 'open';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status()] ?? $this->status();
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null && $this->resolved_at === null;
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null && $this->acknowledged_at === null;
    }

    /** Rang de gravité utilisé pour le tri par priorité (0 si inconnue). */
    public function severityRank(): int
    {
        return self::SEVERITY_RANK[$this->severity] ?? 0;
    }

    /** Âge de l'alerte en heures (depuis la création). */
    public function ageInHours(): float
    {
        return $this->created_at ? $this->created_at->diffInHours(now()) : 0.0;
    }

    /**
     * Gravité à retenir pour l'information citoyenne : la plus élevée entre la
     * gravité déclarée et celle estimée par l'IA.
     */
    public function citizenSeverity(): string
    {
        $rank = ['info' => 1, 'low' => 1, 'warning' => 2, 'medium' => 2, 'high' => 3, 'critical' => 4];

        return collect([$this->severity, $this->ai_severity])
            ->filter()
            ->sortByDesc(fn ($level) => $rank[$level] ?? 0)
            ->first() ?: 'medium';
    }

    /** Explication citoyenne, sans jargon technique (repli déterministe). */
    public function citizenExplanation(): string
    {
        if (filled($this->ai_explanation)) {
            return (string) $this->ai_explanation;
        }

        return match ($this->citizenSeverity()) {
            'critical' => "Une situation critique a été détectée sur cette zone. Par précaution, ne consommez pas l'eau du robinet et suivez les consignes officielles.",
            'high' => "Une anomalie importante a été détectée. Limitez l'usage de l'eau du robinet le temps que nos équipes vérifient la situation.",
            'medium' => "Une anomalie a été détectée et nos équipes la surveillent. Aucune restriction particulière n'est en vigueur pour le moment.",
            'low' => "Une vérification de routine a été déclenchée. La situation est suivie par nos équipes.",
            'warning' => "Une anomalie a été détectée. Nos équipes vérifient la situation.",
            'info' => "Une information de suivi a été publiée pour cette zone.",
            default => "Nos équipes suivent la situation sur cette zone.",
        };
    }

    /**
     * Actions recommandées aux citoyens (version publique).
     *
     * @return array<int, string>
     */
    public function citizenActions(): array
    {
        $actions = $this->ai_recommended_actions;

        if (is_array($actions)) {
            $actions = array_values(array_filter($actions, fn ($action) => is_string($action) && filled($action)));
        }

        if (empty($actions)) {
            $level = match ($this->citizenSeverity()) {
                'critical' => 'critical',
                'high' => 'high',
                'medium' => 'medium',
                'low' => 'low',
                'warning' => 'medium',
                'info' => 'low',
                default => 'medium',
            };

            $actions = (array) config('aquasecure.safety_tips.'.$level, []);
        }

        return array_slice($actions, 0, 5);
    }

    /** Frise de traitement publique. */
    public function publicTimeline(): array
    {
        return \App\Support\CitizenStatus::alertTimeline($this);
    }
}
