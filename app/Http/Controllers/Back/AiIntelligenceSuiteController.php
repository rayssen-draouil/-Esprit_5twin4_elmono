<?php

namespace App\Http\Controllers\Back;

use App\Exceptions\GroqAiException;
use App\Http\Controllers\Controller;
use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\Signalement;
use App\Models\Technicien;
use App\Services\CitizenReportAnalysisService;
use App\Services\InfrastructureHealthAnalysisService;
use App\Services\TechnicianAssignmentInvitationService;
use App\Services\TechnicianRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AiIntelligenceSuiteController extends Controller
{
    public function __construct(
        protected InfrastructureHealthAnalysisService $healthService,
        protected CitizenReportAnalysisService $reportService,
        protected TechnicianRecommendationService $technicianService,
        protected TechnicianAssignmentInvitationService $invitationService
    ) {}

    /* -------------------------------------------------------------------------
     * Feature 1: AI Infrastructure Health Analyst
     * ------------------------------------------------------------------------- */

    public function analyzeInfrastructure(Infrastructure $infrastructure): JsonResponse
    {
        try {
            $analysis = $this->healthService->analyze($infrastructure, persist: true);

            return response()->json([
                'success' => true,
                'message' => "Diagnostic de santé réalisé avec succès pour « {$infrastructure->name} ».",
                'analysis' => $analysis,
            ]);
        } catch (GroqAiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getUserFriendlyMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Infrastructure health analysis error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors du diagnostic technique de l'infrastructure.",
            ], 500);
        }
    }

    public function getInfrastructureAnalysis(Infrastructure $infrastructure): JsonResponse
    {
        return response()->json([
            'success' => true,
            'analysis' => $infrastructure->ai_health_analysis,
            'analyzed_at' => $infrastructure->ai_health_analyzed_at?->format('d/m/Y à H:i'),
        ]);
    }

    /* -------------------------------------------------------------------------
     * Feature 2: Smart Citizen Report Analyzer
     * ------------------------------------------------------------------------- */

    public function analyzeReport(Signalement $signalement): JsonResponse
    {
        try {
            $analysis = $this->reportService->analyze($signalement, persist: true);

            return response()->json([
                'success' => true,
                'message' => "Analyse du signalement #{$signalement->id} réalisée avec succès.",
                'analysis' => $analysis,
            ]);
        } catch (GroqAiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getUserFriendlyMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Signalement analysis error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => "Impossible d'analyser le signalement citoyen.",
            ], 500);
        }
    }

    public function acceptReportSuggestions(Request $request, Signalement $signalement): JsonResponse
    {
        $analysis = $signalement->ai_analysis;
        if (empty($analysis)) {
            return response()->json([
                'success' => false,
                'message' => "Aucune recommandation IA n'est disponible pour ce signalement.",
            ], 422);
        }

        $category = $analysis['suggested_category'] ?? $signalement->type;
        $priority = $analysis['suggested_priority'] ?? $signalement->priority;

        $signalement->update([
            'type' => ucfirst($category),
            'priority' => $priority,
            'manager_category' => $category,
            'manager_severity' => $priority,
            'ai_reviewed_at' => now(),
            'ai_reviewed_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Les suggestions de l'IA ont été validées et appliquées au signalement #{$signalement->id}.",
            'signalement' => $signalement,
        ]);
    }

    public function editReportClassification(Request $request, Signalement $signalement): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'max:100'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();

        $signalement->update([
            'type' => $validated['type'],
            'priority' => $validated['priority'],
            'manager_category' => strtolower($validated['type']),
            'manager_severity' => $validated['priority'],
            'ai_reviewed_at' => now(),
            'ai_reviewed_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "La classification du signalement #{$signalement->id} a été mise à jour par le gestionnaire.",
            'signalement' => $signalement,
        ]);
    }

    /* -------------------------------------------------------------------------
     * Feature 3: AI Technician Matchmaker
     * ------------------------------------------------------------------------- */

    public function recommendTechnicians(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'maintenance_id' => ['nullable', 'integer', 'exists:maintenances,id'],
            'type' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'infrastructure_id' => ['nullable', 'integer', 'exists:infrastructures,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $details = $validator->validated();

        if (!empty($details['infrastructure_id'])) {
            $infra = Infrastructure::with('zone')->find($details['infrastructure_id']);
            if ($infra) {
                $details['infrastructure_name'] = $infra->name;
                $details['infrastructure_type'] = $infra->type;
                $details['zone_name'] = $infra->zone?->name;
            }
        }

        try {
            $recommendations = $this->technicianService->recommend($details);

            return response()->json([
                'success' => true,
                'recommendations' => $recommendations,
                'message' => "Recommandations de techniciens formulées avec succès.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Technician matchmaker error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la recherche des techniciens optimaux.",
            ], 500);
        }
    }

    public function assignTechnician(Request $request, Maintenance $maintenance): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'technician_id' => ['required', 'integer', 'exists:techniciens,id'],
        ], [
            'technician_id.required' => 'Un technicien doit être sélectionné.',
            'technician_id.exists' => 'Le technicien sélectionné est introuvable.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $techId = (int) $request->input('technician_id');
        $technician = Technicien::find($techId);
        if (!$technician) {
            return response()->json([
                'success' => false,
                'message' => 'Le technicien sélectionné est introuvable.',
            ], 422);
        }

        if ($technician->status === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => "Le technicien sélectionné est inactif et ne peut pas être affecté.",
            ], 422);
        }

        try {
            $result = $this->invitationService->createAndSendInvitation(
                maintenanceId: $maintenance->id,
                technicianId: $technician->id,
                managerId: Auth::id()
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'email_sent' => $result['email_sent'],
                'invitation' => $result['invitation'],
                'maintenance' => $maintenance->fresh(['technician', 'latestInvitation']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Technician assignment error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'enregistrement de l'affectation du technicien.",
            ], 500);
        }
    }
}
