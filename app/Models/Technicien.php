<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Technicien extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'speciality',
        'skills',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWithTechnicianAccount(Builder $query): Builder
    {
        return $query->whereHas('user', fn (Builder $userQuery) => $userQuery
            ->where('role', 'technician')
            ->where('technician_approval_status', User::TECHNICIAN_APPROVAL_APPROVED));
    }

    public function scopeEligibleForAssignment(Builder $query): Builder
    {
        return $query->where(function (Builder $technicians): void {
            $technicians->whereDoesntHave('user')
                ->orWhereHas('user', fn (Builder $userQuery) => $userQuery
                    ->where('role', 'technician')
                    ->where('technician_approval_status', User::TECHNICIAN_APPROVAL_APPROVED));
        });
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MaintenanceAssignmentInvitation::class, 'technician_id');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class, 'technician_id');
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->status !== 'inactive' && ($this->is_on_intervention ?? false)
            ? 'busy'
            : $this->status;
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'technician_id');
    }

}
