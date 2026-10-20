<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\AI\AlertAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Enrichissement IA d'une alerte en file d'attente :
 * explication, cause probable, actions recommandées, détection de doublons.
 * Déclenché automatiquement à la création (AlertObserver) ou par la commande
 * artisanales ai:analyze-alerts.
 */
class AnalyzeAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        public int $alertId,
        public bool $force = false
    ) {}

    public function handle(AlertAiService $service): void
    {
        $alert = Alert::find($this->alertId);

        if (! $alert) {
            return;
        }

        try {
            $service->analyze($alert, force: $this->force);
        } catch (\Throwable $e) {
            Log::error('Alert AI analysis job failed', [
                'alert_id' => $this->alertId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
