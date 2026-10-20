<?php

namespace App\Jobs;

use App\Models\Zone;
use App\Services\AI\ZoneAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Analyse IA d'une zone en file d'attente (appelée par les commandes
 * artisanales ai:analyze-zones et l'endpoint asynchrone back/zones/{zone}/ai-analyze).
 */
class AnalyzeZoneJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        public int $zoneId,
        public bool $force = false
    ) {}

    public function handle(ZoneAiService $service): void
    {
        $zone = Zone::find($this->zoneId);

        if (! $zone) {
            return;
        }

        try {
            $service->analyze($zone, force: $this->force);
        } catch (\Throwable $e) {
            Log::error('Zone AI analysis job failed', [
                'zone_id' => $this->zoneId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
