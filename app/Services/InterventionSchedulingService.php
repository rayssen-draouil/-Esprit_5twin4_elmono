<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InterventionSchedulingService
{
    public function assertAvailable(
        int $technicianId,
        string $scheduledAt,
        int $durationMinutes,
        ?int $ignoreInterventionId = null
    ): void {
        User::technicians()->whereKey($technicianId)->lockForUpdate()->firstOrFail();

        $conflicts = $this->conflicts($technicianId, $scheduledAt, $durationMinutes, $ignoreInterventionId);
        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'scheduled_at' => $this->conflictMessage($conflicts),
            ]);
        }
    }

    public function conflictMessage(Collection $conflicts): string
    {
        if ($conflicts->contains(fn (Intervention $intervention): bool => $intervention->status === 'in_progress')) {
            return 'Ce technicien est déjà affecté à une autre intervention en cours. Il doit la terminer avant d’en recevoir une autre.';
        }

        return 'Ce technicien est déjà affecté à une autre intervention sur ce créneau. Choisissez un autre horaire ou un autre technicien.';
    }

    public function conflicts(
        int $technicianId,
        ?string $scheduledAt,
        ?int $durationMinutes,
        ?int $ignoreInterventionId = null
    ): Collection
    {
        $hasSchedule = $scheduledAt !== null && $durationMinutes !== null;
        $start = $hasSchedule ? Carbon::parse($scheduledAt) : null;
        $end = $start?->copy()->addMinutes($durationMinutes ?? 0);

        return Intervention::query()
            ->where('technician_id', $technicianId)
            ->where(function ($query) use ($hasSchedule, $end): void {
                $query->where('status', 'in_progress');
                if ($hasSchedule) {
                    $query->orWhere(function ($query) use ($end): void {
                        $query->where('status', 'planned')
                            ->where('scheduled_at', '<', $end);
                    });
                }
            })
            ->where(function ($query): void {
                $query->whereNull('technician_response')
                    ->orWhere('technician_response', '!=', 'refused');
            })
            ->when($ignoreInterventionId, fn ($query) => $query->where('id', '!=', $ignoreInterventionId))
            ->orderBy('scheduled_at')
            ->get(['id', 'scheduled_at', 'scheduled_duration_minutes', 'team', 'status'])
            ->filter(function (Intervention $existing) use ($start): bool {
                if ($existing->status === 'in_progress') {
                    return true;
                }

                if ($start === null) {
                    return false;
                }

                $existingEnd = $existing->scheduled_at
                    ->copy()
                    ->addMinutes($existing->scheduled_duration_minutes ?: 60);

                return $existingEnd->greaterThan($start);
            })
            ->values();
    }
}
