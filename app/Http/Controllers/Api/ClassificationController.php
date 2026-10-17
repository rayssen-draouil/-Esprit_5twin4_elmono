<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIClassificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClassificationController extends Controller
{
    protected AIClassificationService $aiService;

    public function __construct(AIClassificationService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Classifie une description de signalement
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function classify(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|min:5|max:1000',
        ]);

        $result = $this->aiService->classify($request->description);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 503);
        }

        // Mapper la catégorie AI vers le type du système
        $mappedType = $this->aiService->mapCategoryToType($result['categorie']);

        return response()->json([
            'success' => true,
            'data' => [
                'categorie' => $result['categorie'],
                'type' => $mappedType,
                'confidence' => $result['confidence'],
                'categories_probables' => $result['categories_probables'],
            ],
        ]);
    }

    /**
     * Vérifie si le service de classification est disponible
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'available' => $this->aiService->isAvailable(),
        ]);
    }
}
