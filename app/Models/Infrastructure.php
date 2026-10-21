<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Infrastructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'reference_code',
        'name',
        'location',
        'latitude',
        'longitude',
        'type',
        'capacity',
        'description',
        'status',
        'condition',
        'criticality',
        'installation_date',
        'commissioning_date',
        'last_maintenance_date',
        'next_maintenance_date',
        'image_path',
        'ai_health_analysis',
        'ai_health_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'commissioning_date' => 'date',
            'last_maintenance_date' => 'date',
            'next_maintenance_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'ai_health_analysis' => 'array',
            'ai_health_analyzed_at' => 'datetime',
        ];
    }

    /* -------------------------------------------------------------------------
     * Relationships
     * ------------------------------------------------------------------------- */

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class)->orderByDesc('scheduled_at');
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
            $sub->where('name', 'like', "%{$term}%")
                ->orWhere('reference_code', 'like', "%{$term}%")
                ->orWhere('type', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('zone', fn ($z) => $z->where('name', 'like', "%{$term}%"));
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function scopeZone(Builder $query, $zoneId): Builder
    {
        return $zoneId ? $query->where('zone_id', $zoneId) : $query;
    }

    public function scopeCondition(Builder $query, ?string $condition): Builder
    {
        return $condition ? $query->where('condition', $condition) : $query;
    }

    public function scopeCriticality(Builder $query, ?string $criticality): Builder
    {
        return $criticality ? $query->where('criticality', $criticality) : $query;
    }

    public function scopeMaintenanceDue(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNotNull('next_maintenance_date')
                ->where('next_maintenance_date', '<=', now()->addDays(14)->format('Y-m-d'));
        });
    }

    /* -------------------------------------------------------------------------
     * Derived Attributes & Smart Logic
     * ------------------------------------------------------------------------- */

    public function getHealthScoreAttribute(): int
    {
        $base = match ($this->status) {
            'operational' => 90,
            'maintenance' => 65,
            'critical', 'offline', 'out_of_service' => 30,
            default => 75,
        };

        $conditionMod = match ($this->condition) {
            'excellent' => 10,
            'good' => 5,
            'fair' => -10,
            'poor' => -25,
            'critical' => -45,
            default => 0,
        };

        $overdueMod = 0;
        if ($this->next_maintenance_date && $this->next_maintenance_date->isPast()) {
            $overdueMod = -15;
        }

        $score = $base + $conditionMod + $overdueMod;

        return max(5, min(100, $score));
    }

    public function getHealthLabelAttribute(): string
    {
        $score = $this->health_score;
        if ($score >= 85) {
            return 'Excellent';
        }
        if ($score >= 70) {
            return 'Bon état';
        }
        if ($score >= 50) {
            return 'Surveillance';
        }

        return 'Critique';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'operational' => 'Opérationnel',
            'maintenance' => 'En maintenance',
            'critical' => 'Critique',
            'offline', 'out_of_service' => 'Hors service',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'operational' => 'badge-success',
            'maintenance' => 'badge-warning',
            'critical', 'offline', 'out_of_service' => 'badge-danger',
            default => 'badge-neutral',
        };
    }

    public function getConditionLabelAttribute(): string
    {
        return match ($this->condition) {
            'excellent' => 'Excellent',
            'good' => 'Bon état',
            'fair' => 'Passable',
            'poor' => 'Dégradé',
            'critical' => 'Critique',
            default => ucfirst((string) $this->condition),
        };
    }

    public function getConditionBadgeClassAttribute(): string
    {
        return match ($this->condition) {
            'excellent', 'good' => 'badge-success',
            'fair' => 'badge-info',
            'poor' => 'badge-warning',
            'critical' => 'badge-danger',
            default => 'badge-neutral',
        };
    }

    public function getCriticalityLabelAttribute(): string
    {
        return match ($this->criticality) {
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Élevée',
            'vital' => 'Vitale',
            default => ucfirst((string) $this->criticality),
        };
    }

    public function getIsMaintenanceDueSoonAttribute(): bool
    {
        if (! $this->next_maintenance_date) {
            return false;
        }

        return $this->next_maintenance_date->isFuture() && $this->next_maintenance_date->lte(now()->addDays(14));
    }

    public function getIsMaintenanceOverdueAttribute(): bool
    {
        if (! $this->next_maintenance_date) {
            return false;
        }

        return $this->next_maintenance_date->isPast();
    }

    public function getTotalMaintenanceCostAttribute(): float
    {
        return (float) $this->maintenances()->sum('cost');
    }
}
