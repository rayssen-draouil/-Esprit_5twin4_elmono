<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Rôles applicatifs et libellés associés.
     *
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        'admin' => 'Administrateur',
        'manager' => 'Gestionnaire',
        'technician' => 'Technicien',
        'citizen' => 'Citoyen',
    ];

    /**
     * Rôles affectables depuis l'administration.
     *
     * @var list<string>
     */
    public const ASSIGNABLE_ROLES = ['admin', 'manager', 'technician', 'citizen'];

    /**
     * Mot de passe par défaut des comptes utilisateurs créés par l'application.
     */
    public const DEFAULT_PASSWORD = 'password123';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'speciality',
        'skills',
        'status',
        'telegram_chat_id',
        'telegram_linked_at',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'telegram_linked_at' => 'datetime',
        ];
    }

    public function signalements(): HasMany
    {
        return $this->hasMany(Signalement::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function financements(): HasMany
    {
        return $this->hasMany(Financement::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'technician_id');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class, 'technician_id');
    }

    public function assignmentInvitations(): HasMany
    {
        return $this->hasMany(MaintenanceAssignmentInvitation::class, 'technician_id');
    }

    /**
     * Restreint la requête aux utilisateurs portant le rôle technicien.
     */
    public function scopeTechnicians(Builder $query): Builder
    {
        return $query->where('role', 'technician');
    }

    /**
     * L'utilisateur est-il un technicien ?
     */
    public function isTechnician(): bool
    {
        return $this->role === 'technician';
    }

    /**
     * Send password reset links through the configured Brevo transactional service.
     */
    public function sendPasswordResetNotification($token): void
    {
        $result = app(\App\Services\BrevoEmailService::class)->sendPasswordResetNotification($this, $token);

        if (! $result['success']) {
            throw new \RuntimeException('Password reset email could not be delivered.');
        }
    }

    /** Send a signed verification link through the Brevo transactional service. */
    public function sendEmailVerificationNotification(): void
    {
        $result = app(\App\Services\BrevoEmailService::class)->sendEmailVerificationNotification($this);

        if (! $result['success']) {
            throw new \RuntimeException('Email verification message could not be delivered.');
        }
    }

    /**
     * Libellé lisible du statut opérationnel (techniciens).
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->display_status) {
            'busy' => 'Occupé',
            'inactive' => 'Inactif',
            default => 'Disponible',
        };
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->status !== 'inactive' && ($this->is_on_intervention ?? false)
            ? 'busy'
            : ($this->status ?? 'available');
    }

    /**
     * Libellés des rôles (source unique pour les vues).
     *
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return self::ROLE_LABELS;
    }

    /**
     * Libellé lisible du rôle de l'utilisateur.
     */
    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }
}
