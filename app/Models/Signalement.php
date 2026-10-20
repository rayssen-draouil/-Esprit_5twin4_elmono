<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Signalement extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'user_id',
        'reporter_name',
        'reporter_email',
        'type',
        'description',
        'location',
        'photo_path',
        'priority',
        'status',
        'tracking_token',
        'reported_at',
        'ai_analysis',
        'ai_status',
        'ai_reviewed_at',
        'ai_reviewed_by',
        'manager_category',
        'manager_severity',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $signalement): void {
            if (blank($signalement->tracking_token)) {
                do {
                    $token = 'AQ-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
                } while (self::where('tracking_token', $token)->exists());

                $signalement->tracking_token = $token;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'ai_analysis' => 'array',
            'ai_reviewed_at' => 'datetime',
        ];
    }

    /** Référence lisible du dossier (ex. SIG-0012). */
    public function publicReference(): string
    {
        return 'SIG-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Libellé citoyen du statut courant. */
    public function statusLabel(): string
    {
        return \App\Support\CitizenStatus::signalementLabel($this->status);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_reviewed_by');
    }
}
