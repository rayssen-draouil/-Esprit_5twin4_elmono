<?php

namespace App\Services;

use App\Models\Equipement;
use Illuminate\Support\Collection;

class EquipmentReservationService
{
    public function syncStatus(Collection|array $equipmentIds): void
    {
        $ids = collect($equipmentIds)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        Equipement::query()
            ->whereIn('id', $ids)
            ->whereIn('status', ['available', 'in_use'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(function (Equipement $equipment): void {
                $isReserved = $equipment->interventions()
                    ->whereNotIn('interventions.status', ['completed', 'cancelled'])
                    ->exists();

                $status = $isReserved ? 'in_use' : 'available';
                if ($equipment->status !== $status) {
                    $equipment->update(['status' => $status]);
                }
            });
    }
}
