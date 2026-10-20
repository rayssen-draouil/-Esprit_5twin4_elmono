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

    protected $fillable = [
        'name',
        'address',
        'description',
        'risk_level',
    ];

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
}
