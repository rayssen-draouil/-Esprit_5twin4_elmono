<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceIntelligenceScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'summary_metrics',
        'findings',
        'raw_risks_count',
        'overall_risk_score',
        'overall_risk_level',
    ];

    protected function casts(): array
    {
        return [
            'summary_metrics' => 'array',
            'findings' => 'array',
            'raw_risks_count' => 'integer',
            'overall_risk_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
