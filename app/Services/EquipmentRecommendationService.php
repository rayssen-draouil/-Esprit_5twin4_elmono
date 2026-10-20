<?php

namespace App\Services;

use App\Exceptions\GroqAiException;
use App\Models\Equipement;
use App\Models\Incident;
use App\Models\Technicien;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EquipmentRecommendationService
{
    public function __construct(private readonly AnthropicAiService $anthropicService) {}

    public function recommend(Technicien $technician, Incident $incident): array
    {
        $availableEquipment = Equipement::query()
            ->where('status', 'available')
            ->whereDoesntHave('interventions', fn ($query) => $query
                ->whereNotIn('interventions.status', ['completed', 'cancelled']))
            ->orderBy('name')
            ->get();

        if ($availableEquipment->isEmpty()) {
            return [
                'recommendations' => [],
                'is_ai_generated' => false,
                'notice' => 'Aucun équipement suffisamment pertinent trouvé',
            ];
        }

        if ($this->anthropicService->isConfigured()) {
            try {
                $raw = $this->anthropicService->messageJson(
                    'Vous êtes un assistant de maintenance du réseau d’eau. À partir du type d’incident et des types d’équipements disponibles, recommandez uniquement les équipements réellement pertinents. Ne déduisez pas la pertinence du seul fait qu’un équipement est disponible. N’inventez jamais d’équipement ni d’identifiant; utilisez uniquement les identifiants fournis. Retournez au maximum trois recommandations. Si aucun équipement ne convient, retournez {"recommendations":[]}. Répondez exclusivement en JSON.',
                    json_encode([
                        'incident_type' => $incident->type,
                        'available_equipment' => $availableEquipment->map(fn (Equipement $equipment): array => [
                            'id' => $equipment->id,
                            'name' => $equipment->name,
                            'type' => $equipment->type,
                        ])->all(),
                        'required_output' => [
                            'recommendations' => 'tableau de zéro à trois objets {equipment_id: id fourni, reason: justification courte basée sur la compatibilité avec incident_type}',
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                );

                $recommendations = $this->normalize($raw, $availableEquipment);

                return [
                    'recommendations' => $recommendations,
                    'is_ai_generated' => true,
                    'notice' => $recommendations === []
                        ? 'Aucun équipement suffisamment pertinent trouvé'
                        : 'Suggestions IA basées sur le type de l’incident. Le choix final vous appartient.',
                ];
            } catch (\Throwable $exception) {
                Log::warning('Anthropic equipment recommendation failed; deterministic recommendations used.', [
                    'technician_id' => $technician->id,
                    'incident_id' => $incident->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $recommendations = $this->deterministicRecommendations($availableEquipment, $incident);

        return [
            'recommendations' => $recommendations,
            'is_ai_generated' => false,
            'notice' => $recommendations === []
                ? 'Aucun équipement suffisamment pertinent trouvé'
                : 'Suggestions locales indicatives basées sur le type de l’incident. Configurez Anthropic avec ANTHROPIC_API_KEY pour obtenir des recommandations générées par l’IA.',
        ];
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(\Illuminate\Support\Str::ascii($text));
    }

    private function terms(string $text): array
    {
        $terms = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $terms,
            fn (string $term): bool => mb_strlen($term) >= 3 && ! in_array($term, ['des', 'une', 'les', 'avec', 'dans', 'pour'], true)
        ));
    }

    private function normalize(array $raw, Collection $candidates): array
    {
        if (! is_array($raw['recommendations'] ?? null)) {
            throw new GroqAiException(
                'AI returned a malformed equipment recommendation list.',
                500,
                'La liste de recommandations IA reçue est invalide.'
            );
        }

        $byId = $candidates->keyBy('id');
        $seen = [];
        $recommendations = [];

        $items = $raw['recommendations'] ?? [];
        if ($items === []) {
            return [];
        }

        foreach (array_slice($items, 0, 3) as $item) {
            if (! is_array($item) || ! isset($item['equipment_id'])) {
                continue;
            }

            $id = (int) $item['equipment_id'];
            $equipment = $byId->get($id);
            if (! $equipment || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $recommendations[] = [
                'id' => $equipment->id,
                'name' => $equipment->name,
                'type' => $equipment->type,
                'reason' => mb_substr(trim((string) ($item['reason'] ?? 'Type d’équipement compatible avec l’incident.')), 0, 500),
            ];
        }

        if ($recommendations === []) {
            throw new GroqAiException(
                'AI returned no valid equipment identifiers.',
                500,
                'Aucune recommandation IA valide n’a été reçue.'
            );
        }

        return $recommendations;
    }

    private function deterministicRecommendations(Collection $candidates, Incident $incident): array
    {
        $terms = $this->terms($this->normalizeText($incident->type));

        return $candidates
            ->map(function (Equipement $equipment) use ($terms): array {
                $equipmentTerms = $this->terms($this->normalizeText((string) $equipment->type));
                $score = count(array_intersect($terms, $equipmentTerms));

                return [
                    'equipment' => $equipment,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->take(3)
            ->map(fn (array $candidate): array => [
                'id' => $candidate['equipment']->id,
                'name' => $candidate['equipment']->name,
                'type' => $candidate['equipment']->type,
                'reason' => 'Le type de cet équipement est compatible avec le type de l’incident.',
            ])
            ->values()
            ->all();
    }
}
