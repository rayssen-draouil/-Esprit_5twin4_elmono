<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeZoneJob;
use App\Models\Zone;
use App\Services\AI\ZoneAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analyse IA des zones : score de risque, synthèse, recommandations de prévention.
 *
 * Exemples :
 *   php artisan ai:analyze-zones                 # analyse les zones jamais analysées
 *   php artisan ai:analyze-zones --force         # réanalyse toutes les zones
 *   php artisan ai:analyze-zones --queue         # pousse chaque analyse en file d'attente
 *   php artisan ai:analyze-zones --limit=10
 */
class AnalyzeZones extends Command
{
    protected $signature = 'ai:analyze-zones
        {--force : Réanalyse aussi les zones déjà analysées}
        {--queue : Envoie chaque analyse en file d\'attente au lieu de traiter en ligne}
        {--limit=0 : Nombre maximal de zones à analyser (0 = toutes)}
        {--sleep=1 : Pause en secondes entre deux appels IA (évite le rate limit)}';

    protected $description = 'Analyse les zones avec l\'IA (score de risque 0-100, synthèse, recommandations de prévention)';

    public function handle(ZoneAiService $service): int
    {
        if (! config('ai.key')) {
            $this->warn('AI_API_KEY non configuré dans .env : analyses locales de repli utilisées.');
        }

        $query = Zone::query()->orderBy('id');

        if (! $this->option('force')) {
            $query->whereNull('ai_analyzed_at');
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $zones = $query->get();

        if ($zones->isEmpty()) {
            $this->info('Aucune zone à analyser.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d zone(s) à analyser%s…', $zones->count(), $this->option('queue') ? ' (file d\'attente)' : ''));

        $ai = 0;
        $fallback = 0;
        $failed = 0;
        $queued = 0;

        foreach ($zones as $zone) {
            try {
                if ($this->option('queue')) {
                    AnalyzeZoneJob::dispatch($zone->id, (bool) $this->option('force'));
                    $queued++;
                } else {
                    $analysis = $service->analyze($zone, force: (bool) $this->option('force'));

                    if (($analysis['source'] ?? null) === 'ai') {
                        $ai++;
                    } else {
                        $fallback++;
                    }

                    $this->line(sprintf(
                        '  [%3d] %-30s score %3d/100 (%s)',
                        $zone->id,
                        mb_substr($zone->name, 0, 30),
                        $analysis['risk_score'],
                        $analysis['risk_level']
                    ));
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error('ai:analyze-zones failed', ['zone_id' => $zone->id, 'error' => $e->getMessage()]);
                $this->error("  [{$zone->id}] échec : {$e->getMessage()}");
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
