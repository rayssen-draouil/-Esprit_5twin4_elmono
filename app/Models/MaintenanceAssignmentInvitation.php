<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceAssignmentInvitation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'maintenance_id',
        'technician_id',
        'manager_id',
        'token_hash',
        'status',
        'invited_at',
        'responded_at',
        'expires_at',
        'decline_reason',
        'email_delivery_status',
        'brevo_message_id',
        'email_error',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function generateRawToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technicien::class, 'technician_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->hasExpired();
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isDeclined(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getDisplayStatusLabelAttribute(): string
    {
        if ($this->hasExpired() && $this->status === self::STATUS_PENDING) {
            return 'Expirée';
        }

        return match ($this->status) {
            self::STATUS_PENDING => 'En attente de réponse',
            self::STATUS_ACCEPTED => 'Acceptée',
            self::STATUS_DECLINED => 'Déclinée',
            self::STATUS_EXPIRED => 'Expirée',
            self::STATUS_CANCELLED => 'Annulée',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        if ($this->hasExpired() && $this->status === self::STATUS_PENDING) {
            return 'badge-neutral';
        }

        return match ($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_ACCEPTED => 'badge-success',
            self::STATUS_DECLINED => 'badge-danger',
            self::STATUS_CANCELLED, self::STATUS_EXPIRED => 'badge-neutral',
            default => 'badge-neutral',
        };
    }
}
