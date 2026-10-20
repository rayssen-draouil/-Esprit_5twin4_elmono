<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Financement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'source',
        'amount',
        'status',
        'funded_at',
        'validation_status',
        'validation_comment',
        'validated_by',
        'validated_at',
        'resubmitted_after_changes_requested',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'funded_at' => 'date',
            'validated_at' => 'datetime',
            'resubmitted_after_changes_requested' => 'boolean',
        ];
    }

    public function decisionHistories(): HasMany
    {
        return $this->hasMany(DecisionHistory::class, 'resource_id', 'id')->where('resource_type', 'financement');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
