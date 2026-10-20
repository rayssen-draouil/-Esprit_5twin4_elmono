<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const TECHNICIAN_APPROVAL_PENDING = 'pending';

    public const TECHNICIAN_APPROVAL_APPROVED = 'approved';

    public const TECHNICIAN_APPROVAL_REJECTED = 'rejected';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        'technician_approval_status',
        'telegram_chat_id',
        'telegram_linked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'technician_approval_status' => self::TECHNICIAN_APPROVAL_APPROVED,
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

    public function technicien(): HasOne
    {
        return $this->hasOne(Technicien::class);
    }

    public function isTechnicianApproved(): bool
    {
        return $this->role !== 'technician'
            || $this->technician_approval_status === self::TECHNICIAN_APPROVAL_APPROVED;
    }
}
