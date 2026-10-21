<?php

namespace App\Services;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AiMaintenancePlannerService
{
    public function __construct(
        protected GroqAiService $groqService
    ) {}

    /**
     * Generate an AI-assisted maintenance proposal for a specific infrastructure and optional finding context.
     *
     * @return array Structured plan data
     */
    public function generatePlan(int $infrastructureId, ?array $findingContext = null): array
    {
        // 1. Authoritative server-side verification of infrastructure
        $infrastructure = Infrastructure::with([
            'zone',
            'incidents' => fn ($q) => $q->whereNotIn('status', ['resolved', 'closed'])->orderByDesc('reported_at'),
            'maintenances' => fn ($q) => $q->orderByDesc('scheduled_at')->limit(8),
        ])->findOrFail($infrastructureId);

        // 2. Fetch technicians and existing workload
        $technicians = User::technicians()->get(['id', 'name', 'speciality', 'status']);
        $specialities = $technicians->pluck('speciality')->filter()->unique()->values()->toArray();

        // Check active operations on this infrastructure
        $activeOperations = $infrastructure->maintenances->filter(function ($m) {
            return in_array($m->status, ['planned', 'in_progress'], true);
        });

        // 3. Prepare server-side context
        $context = [
            'infrastructure' => [
                'id' => $infrastructure->id,
                'name' => $infrastructure->name,
                'reference_code' => $infrastructure->reference_code ?? ('INF-'.$infrastructure->id),
                'type' => $infrastructure->type,
                'zone' => $infrastructure->zone?->name,
                'location' => $infrastructure->location,
                'capacity' => $infrastructure->capacity,
                'status' => $infrastructure->status,
                'status_label' => $infrastructure->status_label,
                'condition' => $infrastructure->condition,
                'condition_label' => $infrastructure->condition_label,
                'criticality' => $infrastructure->criticality,
                'criticality_label' => $infrastructure->criticality_label,
                'health_score' => $infrastructure->health_score,
                'last_maintenance_date' => $infrastructure->last_maintenance_date?->format('Y-m-d'),
                'next_maintenance_date' => $infrastructure->next_maintenance_date?->format('Y-m-d'),
            ],
            'finding_context' => $findingContext,
            'recent_history' => $infrastructure->maintenances->take(5)->map(fn ($m) => [
                'reference_code' => $m->reference_code,
                'type' => $m->type,
                'status' => $m->status,
                'priority' => $m->priority,
                'cost' => $m->cost,
                'duration_hours' => $m->duration_hours,
                'result' => substr((string) $m->result, 0, 100),
                'scheduled_at' => $m->scheduled_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'open_incidents' => $infrastructure->incidents->map(fn ($inc) => [
                'type' => $inc->type,
                'severity' => $inc->severity,
                'description' => substr((string) $inc->description, 0, 150),
                'reported_at' => $inc->reported_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'active_operations_count' => $activeOperations->count(),
            'available_technician_specialities' => $specialities,
            'today' => Carbon::now()->format('Y-m-d'),
            'now' => Carbon::now()->format('Y-m-d H:i'),
        ];

        // 4. Call Groq AI or fallback to deterministic plan
        if ($this->groqService->isConfigured()) {
            try {
                $rawPlan = $this->callGroqPlanner($context);

                return $this->validateAndNormalizePlan($rawPlan, $infrastructure, $findingContext);
            } catch (\Throwable $e) {
                Log::warning('Groq plan generation failed, falling back to deterministic template', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->generateDeterministicPlan($infrastructure, $findingContext, $specialities);
    }

    /**
     * Send planning context to Groq AI.
     */
    protected function callGroqPlanner(array $context): array
    {
        $systemPrompt = <<<'PROMPT'
Vous êtes le planificateur technique principal de la régie des eaux AquaSecure.
Votre mission est d'élaborer une proposition de plan d'intervention de maintenance préventive ou corrective adaptée à une infrastructure hydraulique spécifique.

RÈGLES D'EXCELLENCE ET DE SÉCURITÉ :
1. Vous proposez un plan de travail technique rigoureux et structuré qui sera soumis à la validation humaine du responsable d'exploitation.
2. Tout créneau ou durée est une PROPOSITION (estimation), non un ordre définitif.
3. Ne prétendez pas qu'une pièce est en stock ou qu'un technicien spécifique est réservé sans mentionner que c'est une recommandation.
4. Le type de maintenance doit obligatoirement être l'un des suivants : 'Préventive', 'Corrective', 'Inspection', 'Réglementaire', 'Urgence'.
5. La priorité doit être l'un des suivants : 'low', 'medium', 'high', 'critical'.
6. La date prévisionnelle de début 'proposed_start_date' doit se situer dans le futur (ou aujourd'hui) au format 'Y-m-d H:i'.
7. Restituez STRICTEMENT un objet JSON valide conforme aux champs demandés.
PROMPT;

        $userPrompt = "Voici le contexte complet de l'infrastructure et du risque identifié :\n\n"
            .json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\n\nGénérez la proposition de plan de maintenance sous forme d'un objet JSON contenant exactement :\n"
            ."- plan_title : Titre professionnel de la gamme d'intervention\n"
            ."- infrastructure_id : {$context['infrastructure']['id']}\n"
            ."- maintenance_type : 'Préventive' | 'Corrective' | 'Inspection' | 'Réglementaire' | 'Urgence'\n"
            ."- priority : 'low' | 'medium' | 'high' | 'critical'\n"
            ."- justification : Explication argumentée basée sur l'état et l'historique de l'infrastructure\n"
            ."- proposed_start_date : Date et heure suggérées (format 'Y-m-d H:i')\n"
            ."- proposed_completion_window : Fenêtre d'exécution préconisée (ex: '24 heures', 'Demi-journée', '3 jours')\n"
            ."- estimated_duration_hours : Nombre d'heures estimées (décimal, ex: 3.5)\n"
            ."- estimated_cost : Coût financier indicatif estimé en dinars/euros (décimal, ex: 450.00)\n"
            ."- required_skills : Tableau de compétences requises\n"
            ."- recommended_technician_speciality : Spécialité technique recommandée\n"
            ."- work_order_description : Descriptif détaillé des opérations à consigner sur l'ordre de travail\n"
            ."- checklist : Tableau d'étapes techniques précises à cocher sur site\n"
            ."- safety_considerations : Mesures de sécurité (EPI, consignation, balisage)\n"
            ."- required_tools_or_materials : Outillage spécifique, pièces d'usure ou réactifs nécessaires\n"
            ."- verification_steps : Contrôles d'étanchéité, essais en charge et mesures après intervention\n"
            ."- expected_result : Résultat technique attendu à la clôture de la mission\n"
            ."- follow_up_recommendations : Recommandations de surveillance après remise en service\n"
            ."- suggested_next_maintenance_date : Date de prochaine échéance préconisée (format 'Y-m-d')\n"
            ."- assumptions : Hypothèses formulées par l'IA\n"
            ."- missing_information : Données manquantes nécessaires pour affiner la préparation\n";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        return $this->groqService->chatCompletionJson($messages, 0.2);
    }

    /**
     * Validate and normalize the returned AI plan.
     */
    protected function validateAndNormalizePlan(array $rawPlan, Infrastructure $infrastructure, ?array $findingContext): array
    {
        $allowedTypes = ['Préventive', 'Corrective', 'Inspection', 'Réglementaire', 'Urgence'];
        $type = (string) ($rawPlan['maintenance_type'] ?? 'Corrective');
        if (! in_array($type, $allowedTypes, true)) {
            $type = ($infrastructure->condition === 'critical' || $infrastructure->status === 'critical') ? 'Urgence' : 'Corrective';
        }

        $allowedPriorities = ['low', 'medium', 'high', 'critical'];
        $priority = strtolower((string) ($rawPlan['priority'] ?? 'medium'));
        if (! in_array($priority, $allowedPriorities, true)) {
            $priority = match ($infrastructure->criticality) {
                'vital' => 'critical',
                'high' => 'high',
                default => 'medium',
            };
        }

        // Validate scheduled start date: must be today or future
        $startDateStr = (string) ($rawPlan['proposed_start_date'] ?? '');
        $startDate = null;
        try {
            if (! empty($startDateStr)) {
                $parsed = Carbon::parse($startDateStr);
                if ($parsed->isPast()) {
                    $startDate = Carbon::tomorrow()->setTime(9, 0);
                } else {
                    $startDate = $parsed;
                }
            }
        } catch (\Throwable) {
            $startDate = Carbon::tomorrow()->setTime(9, 0);
        }
        if (! $startDate) {
            $startDate = Carbon::tomorrow()->setTime(9, 0);
        }

        // Suggested next date
        $nextDateStr = (string) ($rawPlan['suggested_next_maintenance_date'] ?? '');
        $nextDate = null;
        try {
            if (! empty($nextDateStr)) {
                $parsedNext = Carbon::parse($nextDateStr);
                if ($parsedNext->isAfter($startDate)) {
                    $nextDate = $parsedNext->format('Y-m-d');
                }
            }
        } catch (\Throwable) {
            $nextDate = null;
        }
        if (! $nextDate) {
            $monthsToAdd = ($type === 'Inspection') ? 3 : 6;
            $nextDate = $startDate->copy()->addMonths($monthsToAdd)->format('Y-m-d');
        }

        $duration = (float) ($rawPlan['estimated_duration_hours'] ?? 3.0);
        if ($duration <= 0.1 || $duration > 500) {
            $duration = 3.0;
        }

        $cost = isset($rawPlan['estimated_cost']) ? (float) $rawPlan['estimated_cost'] : null;
        if ($cost !== null && ($cost < 0 || $cost > 1000000)) {
            $cost = null;
        }

        $checklist = is_array($rawPlan['checklist'] ?? null) && ! empty($rawPlan['checklist'])
            ? array_map('strval', $rawPlan['checklist'])
            : [
                "Balisage et sécurisation du périmètre d'intervention",
                "Consignation hydraulique et vérification de l'absence de pression",
                "Inspection visuelle et démontage des organes d'usure",
                'Nettoyage, remplacement des joints et graissage',
                "Mise sous pression d'épreuve et contrôle d'étanchéité",
                'Rédaction du compte-rendu technique et restitution',
            ];

        return [
            'plan_title' => (string) ($rawPlan['plan_title'] ?? "Plan d'intervention - {$infrastructure->name}"),
            'infrastructure_id' => $infrastructure->id,
            'infrastructure_name' => $infrastructure->name,
            'infrastructure_code' => $infrastructure->reference_code ?? ('INF-'.$infrastructure->id),
            'infrastructure_type' => $infrastructure->type,
            'zone_name' => $infrastructure->zone?->name ?? 'Zone non assignée',
            'current_condition' => $infrastructure->condition_label,
            'current_status' => $infrastructure->status_label,
            'maintenance_type' => $type,
            'priority' => $priority,
            'justification' => (string) ($rawPlan['justification'] ?? "Intervention technique requise suite au diagnostic de risque sur {$infrastructure->name}."),
            'proposed_start_date' => $startDate->format('Y-m-d\TH:i'),
            'proposed_start_date_display' => $startDate->format('d/m/Y H:i'),
            'proposed_completion_window' => (string) ($rawPlan['proposed_completion_window'] ?? '1 jour ouvré'),
            'estimated_duration_hours' => $duration,
            'estimated_cost' => $cost,
            'required_skills' => is_array($rawPlan['required_skills'] ?? null) ? array_map('strval', $rawPlan['required_skills']) : ['Hydraulique', 'Mécanique industrielle'],
            'recommended_technician_speciality' => (string) ($rawPlan['recommended_technician_speciality'] ?? 'Maintenance hydraulique'),
            'work_order_description' => (string) ($rawPlan['work_order_description'] ?? "Intervention de maintenance sur {$infrastructure->name}. Procéder aux opérations de révision et consigner les mesures d'étanchéité."),
            'checklist' => $checklist,
            'safety_considerations' => is_array($rawPlan['safety_considerations'] ?? null) ? array_map('strval', $rawPlan['safety_considerations']) : ['Port des EPI obligatoires', 'Vérification de l\'absence de pression résiduelle'],
            'required_tools_or_materials' => is_array($rawPlan['required_tools_or_materials'] ?? null) ? array_map('strval', $rawPlan['required_tools_or_materials']) : ['Jeu de clés dynamométriques', 'Kit de joints de rechange', 'Manomètre de contrôle'],
            'verification_steps' => is_array($rawPlan['verification_steps'] ?? null) ? array_map('strval', $rawPlan['verification_steps']) : ['Test d\'étanchéité à la pression nominale', 'Vérification de l\'absence de vibration anormale'],
            'expected_result' => (string) ($rawPlan['expected_result'] ?? 'Remise en service opérationnel sans fuite ni dysfonctionnement.'),
            'follow_up_recommendations' => (string) ($rawPlan['follow_up_recommendations'] ?? "Contrôle visuel sous 15 jours par l'équipe de secteur."),
            'suggested_next_maintenance_date' => $nextDate,
            'assumptions' => is_array($rawPlan['assumptions'] ?? null) ? array_map('strval', $rawPlan['assumptions']) : ['Accès au site disponible sans contrainte météo'],
            'missing_information' => is_array($rawPlan['missing_information'] ?? null) ? array_map('strval', $rawPlan['missing_information']) : [],
            'is_ai_generated' => true,
        ];
    }

    /**
     * Deterministic plan fallback generator.
     */
    public function generateDeterministicPlan(Infrastructure $infrastructure, ?array $findingContext, array $specialities): array
    {
        $priority = match ($infrastructure->criticality) {
            'vital' => 'critical',
            'high' => 'high',
            default => 'medium',
        };

        $type = ($infrastructure->status === 'critical' || $infrastructure->condition === 'critical')
            ? 'Urgence'
            : ($infrastructure->condition === 'poor' ? 'Corrective' : 'Préventive');

        $startDate = Carbon::tomorrow()->setTime(9, 0);
        $nextDate = $startDate->copy()->addMonths(6)->format('Y-m-d');

        $checklist = [
            'Prise de contact avec le gestionnaire de zone et vérification des accès',
            "Consignation hydraulique et isolement de la section d'ouvrage",
            "Contrôle d'intégrité structurelle et démontage des composants ciblés",
            "Remplacement des pièces d'usure, nettoyage et étanchéité",
            'Essais de remise en eau et validation des pressions de service',
            "Clôture de l'ordre de travail et consignation du compte-rendu",
        ];

        $speciality = ! empty($specialities) ? $specialities[0] : 'Hydraulique';

        return [
            'plan_title' => "Plan de maintenance opérationnelle - {$infrastructure->name}",
            'infrastructure_id' => $infrastructure->id,
            'infrastructure_name' => $infrastructure->name,
            'infrastructure_code' => $infrastructure->reference_code ?? ('INF-'.$infrastructure->id),
            'infrastructure_type' => $infrastructure->type,
            'zone_name' => $infrastructure->zone?->name ?? 'Zone non assignée',
            'current_condition' => $infrastructure->condition_label,
            'current_status' => $infrastructure->status_label,
            'maintenance_type' => $type,
            'priority' => $priority,
            'justification' => "Plan d'intervention proposé pour traiter les alertes et maintenir l'intégrité opérationnelle de {$infrastructure->name}.",
            'proposed_start_date' => $startDate->format('Y-m-d\TH:i'),
            'proposed_start_date_display' => $startDate->format('d/m/Y H:i'),
            'proposed_completion_window' => '1 à 2 jours ouvrés',
            'estimated_duration_hours' => 4.0,
            'estimated_cost' => 350.00,
            'required_skills' => ['Hydraulique', 'Maintenance préventive'],
            'recommended_technician_speciality' => $speciality,
            'work_order_description' => "Intervention planifiée sur l'infrastructure {$infrastructure->name} ({$infrastructure->reference_code}). Réaliser le contrôle d'usure, l'entretien réglementaire et consigner les relevés.",
            'checklist' => $checklist,
            'safety_considerations' => ['Port des EPI obligatoires', 'Respect des protocoles de consignation réseau'],
            'required_tools_or_materials' => ['Outillage standard de serrage', 'Kit joints d\'étanchéité', 'Manomètre portatif'],
            'verification_steps' => ['Mesure de pression en régime établi', 'Inspection visuelle d\'étanchéité à 100% de charge'],
            'expected_result' => "Rétablissement ou consolidation des performances nominales de l'ouvrage.",
            'follow_up_recommendations' => 'Surveillance de la pression lors des 48 heures suivant la réouverture.',
            'suggested_next_maintenance_date' => $nextDate,
            'assumptions' => ['Estimation basée sur les caractéristiques moyennes des équipements similaires'],
            'missing_information' => [],
            'is_ai_generated' => false,
        ];
    }
}
