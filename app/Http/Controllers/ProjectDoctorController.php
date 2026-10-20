<?php

namespace App\Http\Controllers;

use App\Services\ProjectDoctorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectDoctorController extends Controller
{
    public const ALLOWED_INTENTS = [
        'cost_estimation',
        'delay_risk',
        'funding_forecast',
        'project_overview',
        'general_question',
    ];

    public function __construct(protected ProjectDoctorService $doctor) {}

    /**
     * Traite un message du chatbot Project Doctor.
     */
    public function message(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentification requise.',
            ], 401);
        }

        $input = $request->all();
        Log::debug('project.doctor.request', [
            'user_id' => $user->id,
            'input' => $input,
        ]);

        $intent = $input['intent'] ?? null;
        if (! is_string($intent) || ! in_array($intent, self::ALLOWED_INTENTS, true)) {
            $intent = 'general_question';
        }

        $message = isset($input['message']) && is_string($input['message'])
            ? trim($input['message'])
            : '';
        if (strlen($message) > 2000) {
            $message = substr($message, 0, 2000);
        }

        $projectId = null;
        if (isset($input['project_id']) && is_numeric($input['project_id'])) {
            $projectId = (int) $input['project_id'];
        }

        // Vérifier l'accès au projet ciblé.
        if ($projectId !== null) {
            $accessibleIds = $this->doctor->accessibleProjects($user)->pluck('id')->toArray();
            if (! in_array($projectId, $accessibleIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à ce projet.',
                    'intent' => $intent,
                ], 403);
            }
        }
        if ($projectId !== null) {
            $accessibleIds = $this->doctor->accessibleProjects($user)->pluck('id')->toArray();
            if (! in_array($projectId, $accessibleIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à ce projet.',
                    'intent' => $intent,
                ], 403);
            }
        }

        try {
            $result = $this->doctor->handle($user, [
                'intent' => $intent,
                'message' => $message,
                'project_id' => $projectId,
            ]);

            $status = $result['success'] ?? false
                ? 200
                : (($result['code'] ?? null) === 'forbidden' ? 403 : 200);

            return response()->json($result, $status);
        } catch (\Throwable $e) {
            Log::error('Project Doctor controller error', [
                'user_id' => $user->id,
                'intent' => $intent,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'analyse. Veuillez réessayer ultérieurement.',
                'intent' => $intent,
            ], 500);
        }
    }

    /**
     * Indique si le service Project Doctor est disponible (configuré).
     * Ne divulgue jamais la clé API.
     */
    public function status(Request $request): JsonResponse
    {
        $configured = $this->doctor->aiIsConfigured();

        return response()->json([
            'available' => $configured,
        ]);
    }
}
