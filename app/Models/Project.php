<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'budget',
        'start_date',
        'end_date',
        'status',
        'progress',
        'user_id',
        'validation_status',
        'validation_comment',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    public function decisionHistories(): HasMany
    {
        return $this->hasMany(DecisionHistory::class, 'resource_id', 'id')->where('resource_type', 'project');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function financements(): HasMany
    {
        return $this->hasMany(Financement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function budgetRevisions(): HasMany
    {
        return $this->hasMany(ProjectBudgetRevision::class, 'project_id', 'id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class, 'project_id', 'id');
    }
}
