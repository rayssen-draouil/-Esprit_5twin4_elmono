<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIClassificationService
{
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.ai_classification.url', 'http://127.0.0.1:5000');
    }

    /**
     * Classifie une description de signalement via l'API Flask
     *
     * @param string $description
     * @return array
     */
    public function classify(string $description): array
    {
        try {
            $response = Http::timeout(5)->post("{$this->apiUrl}/predict", [
                'description' => $description,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('AI Classification API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'error' => 'Erreur de communication avec l\'API de classification',
            ];
        } catch (\Exception $e) {
            Log::error('AI Classification service exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'error' => 'Impossible de contacter le service de classification IA',
            ];
        }
    }

    /**
     * Vérifie si le service de classification est disponible
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(2)->get("{$this->apiUrl}/health");
            return $response->successful() && $response->json('model_loaded', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mappe les catégories de l'IA vers les types du système
     *
     * @param string $aiCategory
     * @return string
     */
    public function mapCategoryToType(string $aiCategory): string
    {
        return match ($aiCategory) {
            'FUITE_EAU' => 'fuite',
            'CONTAMINATION_SUSPECTEE' => 'contamination',
            'COUPURE_EAU' => 'coupure',
            'AUTRE' => 'autre',
            default => 'autre',
        };
    }
}
