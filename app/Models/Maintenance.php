<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Maintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'infrastructure_id',
        'reference_code',
        'technician_id',
        'technician_assignment_locked',
        'assignment_accepted_at',
        'team',
        'type',
        'description',
        'result',
        'completion_submitted_at',
        'completion_report',
        'completion_validated_at',
        'completion_validated_by',
        'manager_validation_notes',
        'manager_correction_notes',
        'status',
        'priority',
        'cost',
        'duration_hours',
        'scheduled_at',
        'started_at',
        'completed_at',
        'performed_at',
        'next_maintenance_date',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'performed_at' => 'datetime',
            'assignment_accepted_at' => 'datetime',
            'completion_submitted_at' => 'datetime',
            'completion_validated_at' => 'datetime',
            'completion_report' => 'array',
            'technician_assignment_locked' => 'boolean',
            'next_maintenance_date' => 'date',
            'cost' => 'float',
            'duration_hours' => 'float',
        ];
    }

    /* -------------------------------------------------------------------------
     * Relationships
     * ------------------------------------------------------------------------- */

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completion_validated_by');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MaintenanceAssignmentInvitation::class);
    }

    public function latestInvitation(): HasOne
    {
        return $this->hasOne(MaintenanceAssignmentInvitation::class)->latestOfMany();
    }

    public function pendingInvitation(): HasOne
    {
        return $this->hasOne(MaintenanceAssignmentInvitation::class)->where('status', 'pending')->latestOfMany();
    }

    /* -------------------------------------------------------------------------
     * Scopes
     * ------------------------------------------------------------------------- */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($term) {
            $sub->where('reference_code', 'like', "%{$term}%")
                ->orWhere('type', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('team', 'like', "%{$term}%")
                ->orWhere('result', 'like', "%{$term}%")
                ->orWhereHas('infrastructure', function (Builder $infra) use ($term) {
                    $infra->where('name', 'like', "%{$term}%")
                        ->orWhere('reference_code', 'like', "%{$term}%");
                })
                ->orWhereHas('technician', function (Builder $tech) use ($term) {
                    $tech->where('name', 'like', "%{$term}%");
                });
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        if ($status === 'overdue') {
            return $query->where('scheduled_at', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled']);
        }

        return $query->where('status', $status);
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function scopePriority(Builder $query, ?string $priority): Builder
    {
        return $priority ? $query->where('priority', $priority) : $query;
    }

    public function scopeInfrastructure(Builder $query, $infraId): Builder
    {
        return $infraId ? $query->where('infrastructure_id', $infraId) : $query;
    }

    public function scopeTechnician(Builder $query, $techId): Builder
    {
        return $techId ? $query->where('technician_id', $techId) : $query;
    }

    public function scopePeriod(Builder $query, ?string $period): Builder
    {
        return match ($period) {
            'today' => $query->whereDate('scheduled_at', today()),
            'this_week' => $query->whereBetween('scheduled_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'this_month' => $query->whereBetween('scheduled_at', [now()->startOfMonth(), now()->endOfMonth()]),
            default => $query,
        };
    }

    /* -------------------------------------------------------------------------
     * Derived Attributes & Smart Logic
     * ------------------------------------------------------------------------- */

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->scheduled_at || $this->status === 'reported') {
            return false;
        }

        return $this->scheduled_at->isPast() && !in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'reported') {
            return 'reported';
        }

        if ($this->is_overdue) {
            return 'overdue';
        }

        return $this->status ?? 'planned';
    }

    public function getDisplayStatusLabelAttribute(): string
    {
        return match ($this->display_status) {
            'reported' => 'Signalée (En attente)',
            'overdue' => 'En retard',
            'planned' => 'Planifiée',
            'in_progress' => 'En cours d\'exécution',
            'awaiting_validation' => 'En attente de validation',
            'completed' => 'Terminée & Validée',
            'cancelled' => 'Annulée',
            default => ucfirst($this->display_status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->display_status) {
            'reported' => 'badge-danger',
            'overdue' => 'badge-danger',
            'completed' => 'badge-success',
            'in_progress' => 'badge-info',
            'awaiting_validation' => 'badge-warning',
            'planned' => 'badge-warning',
            'cancelled' => 'badge-neutral',
            default => 'badge-neutral',
        };
    }

    public function isLocked(): bool
    {
        return (bool) $this->technician_assignment_locked;
    }

    public function canBeAccepted(): bool
    {
        return $this->status === 'planned' && !$this->isLocked();
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isAwaitingValidation(): bool
    {
        return $this->status === 'awaiting_validation';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
            default => ucfirst((string) $this->priority),
        };
    }

    public function getPriorityBadgeClassAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'badge-neutral',
            'medium' => 'badge-info',
            'high' => 'badge-warning',
            'critical' => 'badge-danger',
            default => 'badge-neutral',
        };
    }

    public function getRelativeTimeMessageAttribute(): string
    {
        if ($this->status === 'completed') {
            $date = $this->completed_at ?? $this->performed_at ?? $this->updated_at;
            return 'Terminée le ' . $date->format('d/m/Y');
        }

        if (!$this->scheduled_at) {
            return 'Date non définie';
        }

        if ($this->is_overdue) {
            $diffDays = (int) abs($this->scheduled_at->diffInDays(now()));
            return $diffDays === 0 ? 'En retard (prévue plus tôt)' : "En retard de {$diffDays} j.";
        }

        if ($this->scheduled_at->isToday()) {
            return 'Aujourd’hui à ' . $this->scheduled_at->format('H:i');
        }

        if ($this->scheduled_at->isTomorrow()) {
            return 'Demain à ' . $this->scheduled_at->format('H:i');
        }

        $diffDays = (int) now()->diffInDays($this->scheduled_at, false);
        return "Dans {$diffDays} jours";
    }
}
