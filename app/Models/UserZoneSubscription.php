<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement d'un citoyen connecté à une zone (« Suivre ma zone »).
 *
 * Le modèle User n'est pas modifié : la relation est portée par cette
 * table dédiée, ce qui garde l'entité User intacte.
 */
class UserZoneSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'zone_id',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
