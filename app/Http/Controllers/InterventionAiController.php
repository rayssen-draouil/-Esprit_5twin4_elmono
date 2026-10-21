<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckInterventionScheduleRequest;
use App\Models\Intervention;
use App\Models\User;
use App\Services\EquipmentRecommendationService;
use App\Services\InterventionSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterventionAiController extends Controller
{
    public function checkSchedule(
        CheckInterventionScheduleRequest $request,
        InterventionSchedulingService $schedulingService,
        ?Intervention $intervention = null,
    ): JsonResponse {
        $data = $request->validated();
        if (empty($data['technician_id'])) {
            return response()->json(['success' => true, 'conflicts' => []]);
        }

        $conflicts = $schedulingService->conflicts(
            (int) $data['technician_id'],
            $data['scheduled_at'] ?? null,
            isset($data['scheduled_duration_minutes']) ? (int) $data['scheduled_duration_minutes'] : null,
            $intervention?->id,
        );

        return response()->json([
            'success' => true,
            'message' => $conflicts->isNotEmpty()
                ? $schedulingService->conflictMessage($conflicts)
                : null,
            'conflicts' => $conflicts->map(fn (Intervention $conflict): array => [
                'id' => $conflict->id,
                'team' => $conflict->team,
                'scheduled_at' => $conflict->scheduled_at?->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    public function recommendEquipment(
        Request $request,
        Intervention $intervention,
        EquipmentRecommendationService $recommendationService
    ): JsonResponse {
        $user = $request->user();
        $technician = $user instanceof User && $user->isTechnician() ? $user : null;

        abort_unless(
            $technician && (int) $intervention->technician_id === (int) $technician->id,
            403,
            'Vous ne pouvez demander des suggestions que pour vos propres interventions.'
        );

        abort_unless(
            $intervention->status === 'planned' && $intervention->technician_response === 'pending',
            422,
            'Les équipements ne peuvent être suggérés que pour une intervention en attente de réponse.'
        );

        $intervention->loadMissing('incident');
        abort_unless($intervention->incident, 422, 'L’incident associé à cette intervention est introuvable.');

        return response()->json([
            'success' => true,
            ...$recommendationService->recommend($technician, $intervention->incident),
        ]);
    }
}
