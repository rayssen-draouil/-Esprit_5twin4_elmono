<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TechnicianRecommendationService
{
    public function __construct(
        protected GroqAiService $groqService
    ) {}

    /**
     * Recommend the best technicians for a maintenance operation.
     *
     * L'IA classe les meilleurs candidats ; l'ensemble de l'effectif
     * technicien est néanmoins restitué afin que le manager puisse affecter
     * n'importe quel technicien de l'équipe.
     */
    public function recommend(array $taskDetails): array
    {
        // 1. Resolve task context
        $maintenance = null;
        if (! empty($taskDetails['maintenance_id'])) {
            $maintenance = Maintenance::with('infrastructure.zone')->find($taskDetails['maintenance_id']);
        }

        $type = $taskDetails['type'] ?? ($maintenance?->type ?? 'Préventive');
        $priority = $taskDetails['priority'] ?? ($maintenance?->priority ?? 'medium');
        $description = $taskDetails['description'] ?? ($maintenance?->description ?? '');
        $infraName = $taskDetails['infrastructure_name'] ?? ($maintenance?->infrastructure?->name ?? 'Équipement');
        $infraType = $taskDetails['infrastructure_type'] ?? ($maintenance?->infrastructure?->type ?? 'Réseau');
        $zoneName = $taskDetails['zone_name'] ?? ($maintenance?->infrastructure?->zone?->name ?? 'Zone d\'exploitation');

        // 2. Fetch the whole technician workforce
        $candidates = $this->getEligibleCandidates($type, $infraType);
        $assignable = $candidates->where('selectable', true)->values();

        if ($assignable->isEmpty()) {
            return [
                'maintenance_id' => $maintenance?->id,
                'candidate_rankings' => [],
                'all_candidates' => $candidates->toArray(),
                'no_suitable_candidate_reason' => 'Aucun technicien actif n\'est actuellement disponible pour une affectation.',
                'task_context' => [
                    'type' => $type,
                    'priority' => $priority,
                    'infrastructure' => $infraName,
                    'zone' => $zoneName,
                ],
                'is_ai_generated' => false,
            ];
        }

        // 3. Call Groq AI or fallback
        if ($this->groqService->isConfigured()) {
            try {
                $rawAi = $this->callGroqMatchmaker($assignable, [
                    'type' => $type,
                    'priority' => $priority,
                    'infrastructure' => $infraName,
                    'infrastructure_type' => $infraType,
                    'zone' => $zoneName,
                    'description' => substr((string) $description, 0, 300),
                ]);

                return $this->validateAndNormalizeRankings($rawAi, $assignable, $maintenance?->id, $candidates);
            } catch (\Throwable $e) {
                Log::warning('Groq technician recommendation failed, falling back to deterministic ranking', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->generateDeterministicRankings($assignable, $type, $maintenance?->id, $candidates);
    }

    /**
     * Retrieve the technician workforce with workload and compatibility data.
     *
     * Tous les techniciens sont retournés : l'IA ne classe qu'une sélection,
     * mais le manager doit pouvoir affecter n'importe quel membre de l'équipe.
     */
    public function getEligibleCandidates(string $maintType, string $infraType): Collection
    {
        $technicians = User::technicians()->withCount([
            'maintenances as active_maintenances_count' => fn ($q) => $q->whereIn('status', ['planned', 'in_progress']),
        ])
            ->get();

        return $technicians->map(function ($tech) use ($maintType, $infraType) {
            $speciality = (string) $tech->speciality;
            $matchScore = $this->calculateSpecialtyCompatibility($speciality, $maintType, $infraType);
            $status = $tech->status ?: 'available';

            return [
                'id' => $tech->id,
                'name' => $tech->name,
                'speciality' => $speciality ?: 'Maintenance générale',
                'status' => $status,
                'active_workload' => $tech->active_maintenances_count,
                'compatibility_score' => $matchScore,
                'selectable' => $status !== 'inactive',
            ];
        })
            ->sortByDesc('compatibility_score')
            ->sortBy('active_workload')
            ->values();
    }

    /**
     * Compute rule-based specialty compatibility score (0 to 100).
     */
    protected function calculateSpecialtyCompatibility(string $speciality, string $maintType, string $infraType): int
    {
        $spec = mb_strtolower($speciality);
        $type = mb_strtolower($maintType);
        $infra = mb_strtolower($infraType);

        $score = 50; // base score for general maintenance

        $matches = function (array $needles) use ($spec) {
            foreach ($needles as $needle) {
                if (str_contains($spec, $needle)) {
                    return true;
                }
            }

            return false;
        };

        // Électromécanique, pompage, groupes motopompes
        if (str_contains($infra, 'pompage') || str_contains($infra, 'moteur') || str_contains($infra, 'station')) {
            if ($matches(['électromécanique', 'pompage', 'moteur', 'motopompe'])) {
                $score += 25;
            } elseif ($matches(['hydraulique', 'mécanique'])) {
                $score += 15;
            }
        }

        // Réseaux de distribution, canalisations, détection de fuites
        if (str_contains($infra, 'réseau') || str_contains($infra, 'distribution') || str_contains($infra, 'canalisation') || str_contains($infra, 'conduite') || str_contains($infra, 'fuite')) {
            if ($matches(['réseau', 'distribution', 'canalisation', 'fuite'])) {
                $score += 25;
            } elseif ($matches(['hydraulique'])) {
                $score += 15;
            }
        }

        // Traitement, filtration, potabilisation, qualité de l'eau
        if (str_contains($infra, 'traitement') || str_contains($infra, 'filtration') || str_contains($infra, 'potable') || str_contains($infra, 'qualité') || str_contains($infra, 'chloration')) {
            if ($matches(['traitement', 'filtration', 'qualité', 'chimie', 'potabilisation'])) {
                $score += 25;
            } elseif ($matches(['automatisme', 'scada', 'télémétrie', 'instrumentation'])) {
                $score += 18;
            }
        }

        // Dessalement et osmose inverse
        if (str_contains($infra, 'dessalement') || str_contains($infra, 'osmose') || str_contains($infra, 'membrane')) {
            if ($matches(['dessalement', 'osmose', 'membrane'])) {
                $score += 30;
            }
        }

        // Automatisation et supervision (transversal aux sites de production)
        if ($matches(['scada', 'automatisme', 'télémétrie', 'supervision', 'instrumentation'])) {
            if (str_contains($infra, 'station') || str_contains($infra, 'traitement') || str_contains($infra, 'réservoir')) {
                $score += 10;
            }
        }

        // Génie civil et auscultation d'ouvrages
        if (str_contains($infra, 'barrage') || str_contains($infra, 'ouvrage') || str_contains($infra, 'génie civil') || str_contains($infra, 'réservoir')) {
            if ($matches(['génie civil', 'auscultation', 'barrage', 'ouvrage', 'fondation'])) {
                $score += 30;
            }
        }

        if ($type === 'urgence') {
            if ($matches(['urgence', 'dépannage', 'astreinte'])) {
                $score += 10;
            }
        }

        return min(100, $score);
    }

    /**
     * Send prompt to Groq AI.
     */
    protected function callGroqMatchmaker(Collection $candidates, array $task): array
    {
        $systemPrompt = <<<'PROMPT'
Vous êtes le coordinateur d'affectation des équipes d'intervention hydraulique pour la plateforme AquaSecure.
Votre rôle est de classer jusqu'à 3 techniciens les plus adaptés pour une intervention de maintenance donnée en vous basant STRICTEMENT sur les candidats réels fournis.

RÈGLES D'INTEGRITÉ :
1. N'inventez jamais de techniciens, ni de qualifications ou disponibilités non mentionnées.
2. Tout 'technician_id' renvoyé DOIT appartenir à la liste exacte des candidats fournis.
3. Classez au maximum 3 techniciens (Rangs 1, 2, 3) avec des justifications transparentes et factuelles.
4. Restituez STRICTEMENT un objet JSON valide conforme au schéma demandé.
PROMPT;

        $userPrompt = "Voici les détails de la mission de maintenance et les techniciens éligibles :\n\n"
            .json_encode([
                'mission' => $task,
                'eligible_candidates' => $candidates->toArray(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\n\nClassez les 3 meilleurs techniciens sous forme d'un objet JSON contenant :\n"
            ."- maintenance_id : ID de la maintenance ou null\n"
            ."- candidate_rankings : Tableau de 1 à 3 objets classés contenant :\n"
            ."    * technician_id : ID numérique exact\n"
            ."    * rank : 1, 2 ou 3\n"
            ."    * specialty_match : 'Excellente' | 'Bonne' | 'Partielle'\n"
            ."    * availability_summary : Synthèse de disponibilité\n"
            ."    * workload_summary : Évaluation de la charge actuelle\n"
            ."    * reasoning : Justification technique du choix\n"
            ."    * advantages : Tableau des points forts de ce profil pour la mission\n"
            ."    * limitations : Tableau des contraintes ou points de vigilance\n"
            ."    * requires_manager_review : true ou false\n"
            ."- no_suitable_candidate_reason : null ou motif si aucun profil n'est adéquat\n";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        return $this->groqService->chatCompletionJson($messages, 0.1);
    }

    /**
     * Validate and normalize the rankings returned by AI.
     */
    protected function validateAndNormalizeRankings(array $raw, Collection $candidates, ?int $maintenanceId, ?Collection $allCandidates = null): array
    {
        $rawRankings = is_array($raw['candidate_rankings'] ?? null) ? $raw['candidate_rankings'] : [];
        $candidatesById = $candidates->keyBy('id');
        $validRankings = [];

        foreach ($rawRankings as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $techId = (int) ($item['technician_id'] ?? 0);
            $cand = $candidatesById->get($techId);

            // Security: Strictly reject unknown technician IDs
            if (! $cand) {
                continue;
            }

            $rank = count($validRankings) + 1;
            if ($rank > 3) {
                break;
            }

            $validRankings[] = [
                'technician_id' => $cand['id'],
                'name' => $cand['name'],
                'speciality' => $cand['speciality'],
                'status' => $cand['status'],
                'active_workload' => $cand['active_workload'],
                'rank' => $rank,
                'specialty_match' => (string) ($item['specialty_match'] ?? ($cand['compatibility_score'] >= 80 ? 'Excellente' : 'Bonne')),
                'availability_summary' => (string) ($item['availability_summary'] ?? ($cand['status'] === 'available' ? 'Disponible pour affectation' : 'Actuellement mobilisé')),
                'workload_summary' => (string) ($item['workload_summary'] ?? "{$cand['active_workload']} intervention(s) en cours"),
                'reasoning' => (string) ($item['reasoning'] ?? "Profil adapté à la spécialité {$cand['speciality']}."),
                'advantages' => is_array($item['advantages'] ?? null) ? array_map('strval', $item['advantages']) : ["Expertise ciblée en {$cand['speciality']}"],
                'limitations' => is_array($item['limitations'] ?? null) ? array_map('strval', $item['limitations']) : [],
                'requires_manager_review' => (bool) ($item['requires_manager_review'] ?? false),
            ];
        }

        if (empty($validRankings)) {
            return $this->generateDeterministicRankings($candidates, 'Générale', $maintenanceId, $allCandidates);
        }

        return [
            'maintenance_id' => $maintenanceId,
            'candidate_rankings' => $validRankings,
            'all_candidates' => ($allCandidates ?? $candidates)->values()->toArray(),
            'no_suitable_candidate_reason' => null,
            'is_ai_generated' => true,
        ];
    }

    /**
     * Deterministic rankings fallback.
     */
    public function generateDeterministicRankings(Collection $candidates, string $maintType, ?int $maintenanceId, ?Collection $allCandidates = null): array
    {
        $rankings = [];
        $rank = 1;

        foreach ($candidates->take(3) as $cand) {
            $matchLabel = $cand['compatibility_score'] >= 80 ? 'Excellente' : ($cand['compatibility_score'] >= 60 ? 'Bonne' : 'Partielle');

            $rankings[] = [
                'technician_id' => $cand['id'],
                'name' => $cand['name'],
                'speciality' => $cand['speciality'],
                'status' => $cand['status'],
                'active_workload' => $cand['active_workload'],
                'rank' => $rank++,
                'specialty_match' => $matchLabel,
                'availability_summary' => $cand['status'] === 'available' ? 'Disponible pour mission' : 'En intervention active',
                'workload_summary' => "{$cand['active_workload']} tâche(s) planifiée(s) ou en cours",
                'reasoning' => "Sélectionné par compatibilité de spécialité ({$cand['speciality']}) et disponibilité.",
                'advantages' => [
                    "Spécialité {$cand['speciality']}",
                    "Charge de travail actuelle : {$cand['active_workload']} tâche(s)",
                ],
                'limitations' => $cand['status'] !== 'available' ? ['Statut opérationnel en cours d\'intervention'] : [],
                'requires_manager_review' => false,
            ];
        }

        return [
            'maintenance_id' => $maintenanceId,
            'candidate_rankings' => $rankings,
            'all_candidates' => ($allCandidates ?? $candidates)->values()->toArray(),
            'no_suitable_candidate_reason' => empty($rankings) ? 'Aucun technicien compatible n\'a été trouvé.' : null,
            'is_ai_generated' => false,
        ];
    }

    /**
     * Safely assign an approved technician to a maintenance.
     */
    public function assignTechnician(int $maintenanceId, int $technicianId): Maintenance
    {
        return DB::transaction(function () use ($maintenanceId, $technicianId) {
            $maintenance = Maintenance::lockForUpdate()->findOrFail($maintenanceId);
            $technician = User::technicians()->lockForUpdate()->findOrFail($technicianId);

            if ($technician->status === 'inactive') {
                throw new \InvalidArgumentException('Le technicien sélectionné est inactif et ne peut pas être affecté.');
            }

            $maintenance->technician_id = $technician->id;
            $maintenance->save();

            return $maintenance;
        });
    }
}
