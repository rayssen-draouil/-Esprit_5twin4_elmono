<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Intervention extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'technician_id',
        'team',
        'scheduled_at',
        'scheduled_duration_minutes',
        'priority',
        'work_plan',
        'safety_precautions',
        'status',
        'result',
        'started_at',
        'completed_at',
        'technician_response',
        'technician_response_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'technician_response_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function equipements(): BelongsToMany
    {
        return $this->belongsToMany(Equipement::class, 'equipement_intervention');
    }
}
