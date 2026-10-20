<?php

namespace App\Console\Commands;

use App\Models\Intervention;
use App\Services\EquipmentReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteExpiredInterventions extends Command
{
    protected $signature = 'interventions:complete-expired';

    protected $description = 'Marque comme terminées les interventions dont le créneau est achevé';

    public function handle(): int
    {
        $now = now();
        $completedCount = 0;

        Intervention::query()
            ->where('status', 'in_progress')
            ->whereNull('completed_at')
            ->where('scheduled_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($interventions) use ($now, &$completedCount): void {
                foreach ($interventions as $intervention) {
                    $endAt = $intervention->scheduled_at
                        ->copy()
                        ->addMinutes($intervention->scheduled_duration_minutes ?: 60);

                    if ($endAt->greaterThan($now)) {
                        continue;
                    }

                    DB::transaction(function () use ($intervention, $endAt, &$completedCount): void {
                        $lockedIntervention = Intervention::query()
                            ->whereKey($intervention->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedIntervention || $lockedIntervention->status !== 'in_progress' || $lockedIntervention->completed_at) {
                            return;
                        }

                        $equipmentIds = $lockedIntervention->equipements()->pluck('equipements.id');
                        $lockedIntervention->update([
                            'status' => 'completed',
                            'completed_at' => $endAt,
                        ]);
                        app(EquipmentReservationService::class)->syncStatus($equipmentIds);
                        $completedCount++;
                    });
                }
            });

        $this->info("{$completedCount} intervention(s) terminée(s).");

        return self::SUCCESS;
    }
}
