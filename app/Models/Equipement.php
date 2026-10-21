<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipement extends Model
{
    use HasFactory;

    protected $table = 'equipements';

    protected $fillable = [
        'name',
        'type',
        'serial_number',
        'status',
        'description',
        'image_path',
    ];

    public function interventions(): BelongsToMany
    {
        return $this->belongsToMany(Intervention::class, 'equipement_intervention');
    }
}
