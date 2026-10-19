<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeAlertJob;
use App\Models\Alert;
use App\Services\AI\AlertAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints IA dédiés aux alertes (additifs : le CRUD Back\AlertController
 * existant reste inchangé).
 */
class AlertAiController extends Controller
{
    public function __construct(
        protected AlertAiService $service
    ) {}

    /**
     * POST /back/alerts/{alert}/ai-analyze
     * Analyse synchrone (défaut) ou mise en file d'attente (?async=1).
     */
    public function analyze(Request $request, Alert $alert): JsonResponse
    {
        if ($request->boolean('async')) {
            AnalyzeAlertJob::dispatch($alert->id, $request->boolean('force'));

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'Analyse IA planifiée pour cette alerte. Rechargez la page dans quelques instants.',
            ]);
        }

        try {
            $analysis = $this->service->analyze($alert, $request->boolean('force'));

            return response()->json([
                'success' => true,
                'queued' => false,
                'message' => $this->successMessage($analysis),
                'analysis' => $analysis,
            ]);
        } catch (\Throwable $e) {
            Log::error('Alert AI analysis endpoint error', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'queued' => false,
                'message' => "Une erreur est survenue lors de l'analyse IA de l'alerte. Veuillez réessayer.",
            ], 500);
        }
    }

    /**
     * GET /back/alerts/{alert}/ai-analysis — dernier résultat persisté
     * + doublons recalculés en direct.
     */
    public function analysis(Alert $alert): JsonResponse
    {
        return response()->json([
            'success' => true,
            'analysis' => $alert->ai_analyzed_at ? $this->persistedPayload($alert) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function successMessage(array $analysis): string
    {
        if (($analysis['source'] ?? null) === 'ai') {
            return 'Analyse IA réalisée pour cette alerte.';
        }

        return $analysis['notice']
            ?? 'Analyse locale réalisée pour cette alerte (service IA indisponible ou non configuré).';
    }

    /**
     * Reconstruction du payload à partir des colonnes persistées.
     *
     * @return array<string, mixed>|null
     */
    private function persistedPayload(Alert $alert): ?array
    {
        if ($alert->ai_analyzed_at === null) {
            return null;
        }

        $duplicates = $this->service->findDuplicates($alert);

        return [
            'alert_id' => $alert->id,
            'zone_id' => $alert->zone_id,
            'zone_name' => $alert->zone?->name,
            'type' => $alert->type,
            'declared_severity' => $alert->severity,
            'severity' => $alert->ai_severity,
            'severity_label' => $alert->ai_severity_label,
            'explanation' => $alert->ai_explanation,
            'probable_cause' => $alert->ai_probable_cause,
            'recommended_actions' => $alert->ai_recommended_actions ?? [],
            'confidence' => null,
            'group_key' => $alert->ai_group_key,
            'duplicates' => $duplicates,
            'duplicate_count' => count($duplicates),
            'source' => 'database',
            'is_ai_generated' => null,
            'notice' => null,
            'analyzed_at' => $alert->ai_analyzed_at->toIso8601String(),
            'analyzed_at_display' => $alert->ai_analyzed_at->format('d/m/Y à H:i'),
        ];
    }
}
