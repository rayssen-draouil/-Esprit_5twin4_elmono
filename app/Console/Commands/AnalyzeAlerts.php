<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeAlertJob;
use App\Models\Alert;
use App\Services\AI\AlertAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analyse IA des alertes : classification (info/warning/critical), explication,
 * cause probable, actions recommandées et détection de doublons.
 *
 * Exemples :
 *   php artisan ai:analyze-alerts                # analyse les alertes jamais analysées (rétrofit)
 *   php artisan ai:analyze-alerts --force        # réanalyse toutes les alertes
 *   php artisan ai:analyze-alerts --queue        # pousse chaque analyse en file d'attente
 *   php artisan ai:analyze-alerts --limit=50
 */
class AnalyzeAlerts extends Command
{
    protected $signature = 'ai:analyze-alerts
        {--force : Réanalyse aussi les alertes déjà analysées}
        {--queue : Envoie chaque analyse en file d\'attente au lieu de traiter en ligne}
        {--limit=0 : Nombre maximal d\'alertes à analyser (0 = toutes)}
        {--sleep=1 : Pause en secondes entre deux appels IA (évite le rate limit)}';

    protected $description = 'Analyse les alertes avec l\'IA (gravité, explication, actions recommandées, doublons)';

    public function handle(AlertAiService $service): int
    {
        if (! config('ai.key')) {
            $this->warn('AI_API_KEY non configuré dans .env : analyses locales de repli utilisées.');
        }

        $query = Alert::query()->orderBy('id');

        if (! $this->option('force')) {
            $query->whereNull('ai_analyzed_at');
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $alerts = $query->get();

        if ($alerts->isEmpty()) {
            $this->info('Aucune alerte à analyser.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d alerte(s) à analyser%s…', $alerts->count(), $this->option('queue') ? ' (file d\'attente)' : ''));

        $ai = 0;
        $fallback = 0;
        $failed = 0;
        $queued = 0;

        foreach ($alerts as $alert) {
            try {
                if ($this->option('queue')) {
                    AnalyzeAlertJob::dispatch($alert->id, (bool) $this->option('force'));
                    $queued++;
                } else {
                    $analysis = $service->analyze($alert, force: (bool) $this->option('force'));

                    if (($analysis['source'] ?? null) === 'ai') {
                        $ai++;
                    } else {
                        $fallback++;
                    }

                    $this->line(sprintf(
                        '  [%3d] %-20s gravité IA : %-8s doublons : %d',
                        $alert->id,
                        mb_substr($alert->type, 0, 20),
                        $analysis['severity'],
                        $analysis['duplicate_count']
                    ));
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error('ai:analyze-alerts failed', ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
                $this->error("  [{$alert->id}] échec : {$e->getMessage()}");
            }

            $sleep = (float) $this->option('sleep');
            if ($sleep > 0) {
                usleep((int) ($sleep * 1_000_000));
            }
        }

        if ($this->option('queue')) {
            $this->info("{$queued} analyse(s) envoyée(s) en file d'attente.");
        } else {
            $this->newLine();
            $this->info("Terminé : {$ai} analyse(s) IA, {$fallback} repli(s) déterministe(s), {$failed} échec(s).");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
