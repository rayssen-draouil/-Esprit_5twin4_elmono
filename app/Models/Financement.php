<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Financement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'source',
        'amount',
        'status',
        'funded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'funded_at' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
