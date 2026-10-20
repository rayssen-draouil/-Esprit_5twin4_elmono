<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeZoneJob;
use App\Models\Zone;
use App\Services\AI\ZoneAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints IA dédiés aux zones (additifs : le CRUD Back\ZoneController
 * existant reste inchangé).
 */
class ZoneAiController extends Controller
{
    public function __construct(
        protected ZoneAiService $service
    ) {}

    /**
     * POST /back/zones/{zone}/ai-analyze
     * Analyse synchrone (défaut) ou mise en file d'attente (?async=1).
     */
    public function analyze(Request $request, Zone $zone): JsonResponse
    {
        if ($request->boolean('async')) {
            AnalyzeZoneJob::dispatch($zone->id, $request->boolean('force'));

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => "Analyse IA planifiée pour « {$zone->name} ». Rechargez la page dans quelques instants.",
            ]);
        }

        try {
            $analysis = $this->service->analyze($zone, $request->boolean('force'));

            return response()->json([
                'success' => true,
                'queued' => false,
                'message' => $this->successMessage($analysis, $zone),
                'analysis' => $analysis,
            ]);
        } catch (\Throwable $e) {
            Log::error('Zone AI analysis endpoint error', [
                'zone_id' => $zone->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'queued' => false,
                'message' => "Une erreur est survenue lors de l'analyse IA de la zone. Veuillez réessayer.",
            ], 500);
        }
    }

    /**
     * GET /back/zones/{zone}/ai-analysis — dernier résultat persisté.
     */
    public function analysis(Zone $zone): JsonResponse
    {
        return response()->json([
            'success' => true,
            'analysis' => $zone->ai_analyzed_at ? $this->persistedPayload($zone) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function successMessage(array $analysis, Zone $zone): string
    {
        if (($analysis['source'] ?? null) === 'ai') {
            return "Analyse IA réalisée pour « {$zone->name} ».";
        }

        return $analysis['notice']
            ?? "Analyse locale réalisée pour « {$zone->name} » (service IA indisponible ou non configuré).";
    }

    /**
     * Reconstruction du payload à partir des colonnes persistées.
     *
     * @return array<string, mixed>|null
     */
    private function persistedPayload(Zone $zone): ?array
    {
        if ($zone->ai_analyzed_at === null) {
            return null;
        }

        return [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'risk_score' => $zone->ai_risk_score,
            'risk_level' => $zone->ai_risk_level,
            'risk_level_label' => $zone->ai_risk_level_label,
            'summary' => $zone->ai_summary,
            'recommendations' => $zone->ai_recommendations ?? [],
            'source' => 'database',
            'is_ai_generated' => null,
            'notice' => null,
            'analyzed_at' => $zone->ai_analyzed_at->toIso8601String(),
            'analyzed_at_display' => $zone->ai_analyzed_at->format('d/m/Y à H:i'),
        ];
    }
}
