<?php

namespace App\Models;

use App\Observers\ZoneObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ZoneObserver::class)]
class Zone extends Model
{
    use HasFactory;

    public const RISK_LEVELS = [
        'low' => 'Faible',
        'medium' => 'Moyen',
        'high' => 'Élevé',
    ];

    /** Niveaux de risque calculés par l'IA (0-100 -> niveau). */
    public const AI_RISK_LEVELS = [
        'low' => 'Faible',
        'medium' => 'Moyen',
        'high' => 'Élevé',
        'critical' => 'Critique',
    ];

    protected $fillable = [
        'name',
        'address',
        'description',
        'image_path',
        'risk_level',
        'latitude',
        'longitude',
        'radius_m',
        'geojson',
        'ai_risk_score',
        'ai_risk_level',
        'ai_summary',
        'ai_recommendations',
        'ai_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
            'geojson' => 'array',
            'ai_risk_score' => 'integer',
            'ai_recommendations' => 'array',
            'ai_analyzed_at' => 'datetime',
        ];
    }

    public function infrastructures(): HasMany
    {
        return $this->hasMany(Infrastructure::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function getRiskLabelAttribute(): string
    {
        return self::RISK_LEVELS[$this->risk_level] ?? $this->risk_level;
    }

    /** URL publique de la photo de la zone (null si aucune photo). */
    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    /**
     * La zone dispose-t-elle d'une position complète (latitude + longitude) ?
     *
     * Une position partielle est considérée comme absente : les cartes
     * affichent alors l'état vide au lieu d'un marqueur erroné.
     */
    public function hasCoordinates(): bool
    {
        return is_numeric($this->latitude) && is_numeric($this->longitude);
    }

    /**
     * Centre géographique de la zone.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function coordinates(): ?array
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        return [
            'latitude' => round((float) $this->latitude, 7),
            'longitude' => round((float) $this->longitude, 7),
        ];
    }

    public function getAiRiskLevelLabelAttribute(): string
    {
        return self::AI_RISK_LEVELS[$this->ai_risk_level] ?? ($this->ai_risk_level ?? '—');
    }

    /** Poids numérique d'un niveau de risque (pour KPI, tri et tendance). */
    public static function riskWeight(?string $level): int
    {
        return ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4][(string) $level] ?? 0;
    }

    /** Niveau de risque à afficher : celui calculé par l'IA prime sur le niveau manuel. */
    public function effectiveRiskLevel(): string
    {
        return $this->ai_risk_level ?: $this->risk_level;
    }

    /**
     * Tendance IA par rapport au niveau manuel :
     *  1 = l'IA estime le risque plus élevé, -1 = plus faible, 0 = identique/indisponible.
     */
    public function aiRiskTrend(): int
    {
        if (! $this->ai_risk_level) {
            return 0;
        }

        return self::riskWeight($this->ai_risk_level) <=> self::riskWeight($this->risk_level);
    }

    /** Une analyse IA a-t-elle été réalisée récemment ? */
    public function hasRecentAnalysis(int $days = 7): bool
    {
        return $this->ai_analyzed_at !== null && $this->ai_analyzed_at->gt(now()->subDays($days));
    }
}
