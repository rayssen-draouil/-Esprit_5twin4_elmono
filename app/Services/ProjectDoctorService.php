<?php

namespace App\Services;

use App\Exceptions\GeminiAiException;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProjectDoctorService
{
    public const INTENT_COST_ESTIMATION = 'cost_estimation';

    public const INTENT_DELAY_RISK = 'delay_risk';

    public const INTENT_FUNDING_FORECAST = 'funding_forecast';

    public const INTENT_PROJECT_OVERVIEW = 'project_overview';

    public const INTENT_GENERAL_QUESTION = 'general_question';

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const RISK_UNKNOWN = 'unknown';

    protected const CACHE_TTL_MINUTES = 30;

    protected const MAX_MESSAGE_LENGTH = 2000;

    /**
     * Champs narratifs (texte) acceptés dans la charge utile IA.
     *
     * @var list<string>
     */
    protected const AI_TEXT_FIELDS = ['synthese', 'reponse', 'limite'];

    /**
     * Champs narratifs (liste) acceptés dans la charge utile IA.
     *
     * @var list<string>
     */
    protected const AI_LIST_FIELDS = [
        'hypotheses',
        'recommandations',
        'actions_recommandees',
        'causes_possibles',
        'informations_manquantes',
        'limites',
        'alertes',
    ];

    /**
     * Longueur maximale d'un champ narratif unique.
     */
    protected const AI_TEXT_MAX_LENGTH = 1500;

    /**
     * Longueur maximale d'un élément de liste narrative.
     */
    protected const AI_ITEM_MAX_LENGTH = 400;

    /**
     * Nombre maximal d'éléments par liste narrative.
     */
    protected const AI_LIST_MAX_ITEMS = 8;

    public function __construct(protected GeminiAiService $ai) {}

    public function aiIsConfigured(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * Main entry point: handle a Project Doctor request.
     *
     * @return array{
     *   success: bool,
     *   intent: string,
     *   answer: string,
     *   data: array,
     *   sources: list<int>,
     *   error?: string,
     *   code?: string,
     *   disclaimer: string
     * }
     */
    public function handle(User $user, array $input): array
    {
        $intent = $input['intent'] ?? self::INTENT_GENERAL_QUESTION;
        $message = trim((string) ($input['message'] ?? ''));
        $projectId = $input['project_id'] ?? null;

        if (! in_array($intent, [
            self::INTENT_COST_ESTIMATION,
            self::INTENT_DELAY_RISK,
            self::INTENT_FUNDING_FORECAST,
            self::INTENT_PROJECT_OVERVIEW,
            self::INTENT_GENERAL_QUESTION,
        ], true)) {
            $intent = self::INTENT_GENERAL_QUESTION;
        }

        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MAX_MESSAGE_LENGTH);
        }

        $accessible = $this->accessibleProjects($user);
        $focusProject = null;

        // Détection automatique de l'intention à partir du texte libre quand aucune intention explicite n'est fournie.
        if ($intent === self::INTENT_GENERAL_QUESTION && $message !== '') {
            try {
                $detected = $this->detectIntentFromMessage($message);
                if ($detected !== self::INTENT_GENERAL_QUESTION) {
                    $intent = $detected;
                }
            } catch (\Throwable $e) {
                Log::warning('Project Doctor intent detection failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($projectId !== null) {
            $focusProject = $accessible->firstWhere('id', (int) $projectId);
            if (! $focusProject) {
                return $this->errorResponse($intent, 'Ce projet est introuvable ou vous n\'y avez pas accès.', 'forbidden');
            }
        }

        // Détection automatique du projet mentionné quand aucun project_id n'est précisé.
        if ($projectId === null && $focusProject === null && $message !== '') {
            try {
                $focusProject = $this->detectProjectFromMessage($user, $message);
            } catch (\Throwable $e) {
                Log::warning('Project Doctor project detection failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $cacheKey = 'project_doctor:'.$user->id.':'.md5(json_encode([
            'intent' => $intent,
            'message' => $message,
            'project_id' => $projectId,
            'detected_project_id' => $focusProject?->id,
        ], JSON_UNESCAPED_UNICODE));

        return Cache::remember($cacheKey, self::CACHE_TTL_MINUTES, function () use ($user, $intent, $message, $focusProject, $accessible) {
            try {
                return match ($intent) {
                    self::INTENT_COST_ESTIMATION => $this->analyzeCost($focusProject, $accessible),
                    self::INTENT_DELAY_RISK => $this->analyzeDelayRisk($focusProject, $accessible),
                    self::INTENT_FUNDING_FORECAST => $this->forecastFunding($focusProject, $accessible),
                    self::INTENT_PROJECT_OVERVIEW => $this->projectOverview($accessible, $focusProject),
                    default => $this->generalQuestion($user, $message, $focusProject, $accessible),
                };
            } catch (\Throwable $e) {
                Log::error('Project Doctor analysis error', [
                    'user_id' => $user->id,
                    'intent' => $intent,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse($intent, 'Une erreur est survenue lors de l\'analyse. Veuillez réessayer ultérieurement.', 'analysis');
            }
        });
    }

    /**
     * Détecte l'intention à partir des mots-clés d'un message libre.
     */
    protected function detectIntentFromMessage(string $message): string
    {
        $lower = mb_strtolower(trim($message));

        $costKeywords = ['coût', 'cout', 'prix', 'budget final', 'estimation', 'chiffre d affaires', 'ca'];
        $delayKeywords = ['retard', 'délai', 'date de fin', 'échéance', 'planning', 'calendrier'];
        $fundingKeywords = ['financement', 'besoin de financement', 'couvrir', 'apport', 'fonds', 'sous-financ'];

        $hasCost = $this->containsAny($lower, $costKeywords);
        $hasDelay = $this->containsAny($lower, $delayKeywords);
        $hasFunding = $this->containsAny($lower, $fundingKeywords);

        if ($hasDelay) {
            return self::INTENT_DELAY_RISK;
        }
        if ($hasCost) {
            return self::INTENT_COST_ESTIMATION;
        }
        if ($hasFunding) {
            return self::INTENT_FUNDING_FORECAST;
        }

        return self::INTENT_GENERAL_QUESTION;
    }

    protected function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (mb_strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Projects the connected user is allowed to consult.
     */
    public function accessibleProjects(User $user): Collection
    {
        $role = strtolower((string) ($user->role ?? ''));
        $query = Project::with(['financements', 'budgetRevisions']);

        if (! in_array($role, ['admin', 'manager', 'gestionnaire'], true)) {
            $query->where('user_id', $user->id);
        }

        return $query->orderByDesc('id')->get();
    }

    /**
     * Build a minimal, safe context for a single project (no users, secrets, or unnecessary data).
     *
     * @return array<string,mixed>
     */
    public function buildProjectContext(Project $project): array
    {
        $confirmed = (float) $project->financements()
            ->where('status', 'received')
            ->where('validation_status', 'approved')
            ->sum('amount');

        $pending = (float) $project->financements()
            ->where('validation_status', 'pending_approval')
            ->sum('amount');

        $rejected = (float) $project->financements()
            ->where('validation_status', 'rejected')
            ->sum('amount');

        $engagedExpenses = (float) $project->expenses()
            ->where('status', 'approved')
            ->sum('amount');

        $paid = 0.0;
        if (Schema::hasTable('expense_payments')) {
            $paid = (float) $project->expenses()
                ->where('project_expenses.status', 'approved')
                ->join('expense_payments', 'project_expenses.id', '=', 'expense_payments.expense_id')
                ->where('expense_payments.status', 'confirmed')
                ->sum('expense_payments.amount');
        }

        $revisions = $project->budgetRevisions()
            ->where('status', 'approved')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->take(3)
            ->get();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'type' => $project->type,
            'description' => $project->description,
            'budget_initial' => (float) $project->budget,
            'budget_revise' => ProjectFinancialService::getRevisedBudget($project),
            'start_date' => $project->start_date?->format('Y-m-d'),
            'end_date' => $project->end_date?->format('Y-m-d'),
            'status' => $project->status,
            'progress' => (int) $project->progress,
            'funding' => [
                'confirmed' => $confirmed,
                'pending' => $pending,
                'rejected' => $rejected,
            ],
            'expenses' => [
                'engaged' => $engagedExpenses,
                'paid' => $paid,
            ],
            'budget_revisions' => $revisions->map(fn ($r) => [
                'previous_amount' => (float) $r->previous_amount,
                'proposed_amount' => (float) $r->proposed_amount,
                'reason' => $r->reason,
                'status' => $r->status,
                'approved_at' => $r->approved_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'has_expense_data' => $project->expenses()->exists(),
            'has_dates' => ! is_null($project->start_date) && ! is_null($project->end_date),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Deterministic calculations */
    /* ------------------------------------------------------------------ */

    public function deterministicCost(array $context): array
    {
        $budget = (float) ($context['budget_revise'] ?? $context['budget_initial'] ?? 0);
        $initial = (float) ($context['budget_initial'] ?? 0);
        $expenses = (float) (($context['expenses']['engaged'] ?? 0));
        $progress = (int) ($context['progress'] ?? 0);
        $hasExpenses = (bool) ($context['has_expense_data'] ?? false);

        $estimatedFinal = null;
        $method = 'aucun';
        $confidence = 'low';
        $missing = [];

        if ($hasExpenses && $expenses > 0 && $progress > 0) {
            $estimatedFinal = round($expenses / ($progress / 100), 2);
            $method = 'extrapolation_lineaire';
            $confidence = $progress >= 50 ? 'high' : 'medium';
        } elseif ($hasExpenses && ! $progress) {
            $missing[] = 'Progression non renseignée : impossible d\'extraire le coût final à partir des dépenses.';
        } elseif (! $hasExpenses) {
            $missing[] = 'Aucune donnée de dépenses réelles enregistrée pour ce projet.';
        }

        $gap = $estimatedFinal !== null ? round($estimatedFinal - $budget, 2) : null;

        return [
            'budget_initial' => $initial,
            'budget_revise' => $budget,
            'depenses_reelles' => $expenses,
            'progression' => $progress,
            'cout_final_estime' => $estimatedFinal,
            'ecart_estime' => $gap,
            'methode' => $method,
            'niveau_confiance' => $confidence,
            'informations_manquantes' => $missing,
        ];
    }

    public function deterministicDelayRisk(array $context): array
    {
        $progress = (int) ($context['progress'] ?? 0);
        $hasDates = (bool) ($context['has_dates'] ?? false);
        $start = $context['start_date'] ? Carbon::parse($context['start_date']) : null;
        $end = $context['end_date'] ? Carbon::parse($context['end_date']) : null;
        $now = Carbon::now();

        $elapsedDays = null;
        $totalDays = null;
        $temporalProgress = null;
        $estimatedEnd = null;
        $risk = self::RISK_UNKNOWN;
        $missing = [];

        if (! $hasDates) {
            $missing[] = 'Dates de début et/ou de fin non renseignées : le risque de retard ne peut pas être calculé.';
        } else {
            $elapsedDays = (int) max(0, $start->diffInDays($now));
            $totalDays = (int) max(1, $start->diffInDays($end));
            $temporalProgress = round(min(100, ($elapsedDays / $totalDays) * 100), 1);

            $gap = round($progress - $temporalProgress, 1);

            if ($gap <= -20) {
                $risk = self::RISK_HIGH;
            } elseif ($gap <= -10) {
                $risk = self::RISK_MEDIUM;
            } else {
                $risk = self::RISK_LOW;
            }

            if ($progress > 0 && $elapsedDays > 0) {
                $estimatedTotalDays = (int) ($elapsedDays / ($progress / 100));
                $estimatedEnd = $start->copy()->addDays($estimatedTotalDays)->format('Y-m-d');
            }
        }

        return [
            'progression_actuelle' => $progress,
            'progression_temporelle_attendue' => $temporalProgress,
            'ecart' => $hasDates ? round($progress - ($temporalProgress ?? 0), 1) : null,
            'duree_ecoule_jours' => $elapsedDays,
            'duree_totale_jours' => $totalDays,
            'date_fin_prevue' => $end?->format('Y-m-d'),
            'date_estimee' => $estimatedEnd,
            'niveau_risque' => $risk,
            'informations_manquantes' => $missing,
        ];
    }

    public function deterministicFunding(array $context, array $cost): array
    {
        $budget = (float) ($context['budget_revise'] ?? $context['budget_initial'] ?? 0);
        $initial = (float) ($context['budget_initial'] ?? 0);
        $confirmed = (float) ($context['funding']['confirmed'] ?? 0);
        $pending = (float) ($context['funding']['pending'] ?? 0);
        $rejected = (float) ($context['funding']['rejected'] ?? 0);
        $paid = (float) ($context['expenses']['paid'] ?? 0);
        $engaged = (float) ($context['expenses']['engaged'] ?? 0);

        $estimatedFinal = $cost['cout_final_estime'] ?? null;
        $reference = $estimatedFinal ?? $budget;
        $need = max(0, round($reference - $confirmed, 2));
        $coverage = $reference > 0 ? round(min(100, ($confirmed / $reference) * 100), 2) : null;

        return [
            'budget_initial' => $initial,
            'budget_reference' => $reference,
            'cout_final_estime' => $estimatedFinal,
            'financements_confirmes' => $confirmed,
            'financements_en_attente' => $pending,
            'financements_rejetes' => $rejected,
            'besoin_financement_estime' => $need,
            'pourcentage_couverture' => $coverage,
            'depenses_realisees' => $engaged,
            'encaissements_reels' => $paid,
            'cout_final_disponible' => $estimatedFinal !== null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Intent analyses */
    /* ------------------------------------------------------------------ */

    public function analyzeCost(?Project $focusProject, Collection $accessible): array
    {
        $target = $focusProject ?? ($accessible->first() ?? null);
        if (! $target) {
            return $this->emptyResponse(self::INTENT_COST_ESTIMATION, 'Aucun projet accessible n\'a été trouvé.');
        }

        $context = $this->buildProjectContext($target);
        $deterministic = $this->deterministicCost($context);
        $sources = [$target->id];

        $answer = $this->askAi(self::INTENT_COST_ESTIMATION, $context, $deterministic, null);

        return [
            'success' => true,
            'intent' => self::INTENT_COST_ESTIMATION,
            'answer' => $answer['text'],
            'data' => array_merge($deterministic, [
                'projet' => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'type' => $target->type,
                ],
                'est_pi_ia' => $answer['ai_generated'],
                'ia' => $answer['ia'] ?? [],
                'disclaimer' => 'Ces estimations sont indicatives et ne constituent pas une source de vérité financière.',
            ]),
            'sources' => $sources,
            'disclaimer' => 'Estimation indicative fournie par Project Doctor (IA). À valider avec les données comptables réelles.',
        ];
    }

    public function analyzeDelayRisk(?Project $focusProject, Collection $accessible): array
    {
        $target = $focusProject ?? ($accessible->first() ?? null);
        if (! $target) {
            return $this->emptyResponse(self::INTENT_DELAY_RISK, 'Aucun projet accessible n\'a été trouvé.');
        }

        $context = $this->buildProjectContext($target);
        $deterministic = $this->deterministicDelayRisk($context);
        $sources = [$target->id];

        $answer = $this->askAi(self::INTENT_DELAY_RISK, $context, $deterministic, null);

        return [
            'success' => true,
            'intent' => self::INTENT_DELAY_RISK,
            'answer' => $answer['text'],
            'data' => array_merge($deterministic, [
                'projet' => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'type' => $target->type,
                ],
                'est_pi_ia' => $answer['ai_generated'],
                'ia' => $answer['ia'] ?? [],
                'disclaimer' => 'L\'estimation de retard est indicative et fondée sur les dates et la progression déclarées.',
            ]),
            'sources' => $sources,
            'disclaimer' => 'Prévision indicative de risque de retard.',
        ];
    }

    public function forecastFunding(?Project $focusProject, Collection $accessible): array
    {
        $target = $focusProject ?? ($accessible->first() ?? null);
        if (! $target) {
            return $this->emptyResponse(self::INTENT_FUNDING_FORECAST, 'Aucun projet accessible n\'a été trouvé.');
        }

        $context = $this->buildProjectContext($target);
        $cost = $this->deterministicCost($context);
        $deterministic = $this->deterministicFunding($context, $cost);
        $sources = [$target->id];

        $answer = $this->askAi(self::INTENT_FUNDING_FORECAST, $context, array_merge($deterministic, $cost), null);

        return [
            'success' => true,
            'intent' => self::INTENT_FUNDING_FORECAST,
            'answer' => $answer['text'],
            'data' => array_merge($deterministic, [
                'projet' => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'type' => $target->type,
                ],
                'est_pi_ia' => $answer['ai_generated'],
                'ia' => $answer['ia'] ?? [],
                'disclaimer' => 'Le besoin de financement est une estimation basée sur les financements confirmés uniquement.',
            ]),
            'sources' => $sources,
            'disclaimer' => 'Prévision de besoin de financement (financements confirmés uniquement, hors demandes en attente).',
        ];
    }

    public function projectOverview(Collection $accessible, ?Project $focusProject): array
    {
        $projects = $accessible->take(10);
        $items = [];
        $totalBudget = 0.0;
        $totalConfirmed = 0.0;
        $totalNeed = 0.0;

        foreach ($projects as $p) {
            $ctx = $this->buildProjectContext($p);
            $cost = $this->deterministicCost($ctx);
            $fund = $this->deterministicFunding($ctx, $cost);
            $totalBudget += (float) $ctx['budget_initial'];
            $totalConfirmed += $fund['financements_confirmes'];
            $totalNeed += $fund['besoin_financement_estime'];

            $items[] = [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'status' => $p->status,
                'progress' => (int) $p->progress,
                'budget_initial' => (float) $ctx['budget_initial'],
                'financements_confirmes' => $fund['financements_confirmes'],
                'besoin_financement' => $fund['besoin_financement_estime'],
                'risque' => $this->deterministicDelayRisk($ctx)['niveau_risque'],
            ];
        }

        $context = [
            'focus_project' => $focusProject ? $this->buildProjectContext($focusProject) : null,
            'projects' => $items,
            'totals' => [
                'budget_initial' => round($totalBudget, 2),
                'financements_confirmes' => round($totalConfirmed, 2),
                'besoin_financement_total' => round($totalNeed, 2),
                'count' => count($items),
            ],
        ];

        $answer = $this->askAi(self::INTENT_PROJECT_OVERVIEW, $context, $context, $focusProject);

        return [
            'success' => true,
            'intent' => self::INTENT_PROJECT_OVERVIEW,
            'answer' => $answer['text'],
            'data' => array_merge($context, ['est_pi_ia' => $answer['ai_generated'], 'ia' => $answer['ia'] ?? []]),
            'sources' => $items ? array_column($items, 'id') : [],
            'disclaimer' => 'Vue d\'automne indicative des projets auxquels vous avez accès.',
        ];
    }

    public function generalQuestion(User $user, string $message, ?Project $focusProject, Collection $accessible): array
    {
        // Détecte le projet mentionné dans le texte libre si aucun projet n'est précisé.
        if (! $focusProject) {
            $focusProject = $this->detectProjectFromMessage($user, $message);
        }

        $context = [
            'user_role' => $user->role,
            'focus_project' => $focusProject ? $this->buildProjectContext($focusProject) : null,
            'accessible_projects' => $accessible->take(8)->map(fn ($p) => $this->buildProjectContext($p))->toArray(),
            'question' => $message,
        ];

        $answer = $this->askAi(self::INTENT_GENERAL_QUESTION, $context, $context, $focusProject);

        return [
            'success' => true,
            'intent' => self::INTENT_GENERAL_QUESTION,
            'answer' => $answer['text'],
            'data' => [
                'est_pi_ia' => $answer['ai_generated'],
                'focus_project_id' => $focusProject?->id,
                'ia' => $answer['ia'] ?? [],
            ],
            'sources' => $focusProject ? [$focusProject->id] : array_column($context['accessible_projects'], 'id'),
            'disclaimer' => 'Réponse générée par l\'IA à partir des données projets autorisées.',
        ];
    }

    /**
     * Détecte le projet mentionné dans un texte libre parmi les projets accessibles.
     */
    public function detectProjectFromMessage(User $user, string $message): ?Project
    {
        if (trim($message) === '') {
            return null;
        }

        $messageLower = mb_strtolower(trim($message));
        $accessible = $this->accessibleProjects($user);

        // Correspondance exacte sur le nom (insensible à la casse).
        foreach ($accessible as $project) {
            if ($project->name && mb_strtolower($project->name) === $messageLower) {
                return $project;
            }
        }

        // Correspondance partiale : le nom du projet apparaît dans le message.
        foreach ($accessible as $project) {
            if ($project->name && mb_strpos($messageLower, mb_strtolower($project->name)) !== false) {
                return $project;
            }
        }

        // Correspondance sur un mot significatif du nom (au moins 4 caractères).
        foreach ($accessible as $project) {
            if (! $project->name) {
                continue;
            }
            foreach (explode(' ', trim($project->name)) as $word) {
                $word = trim($word);
                if (mb_strlen($word) >= 4 && mb_strpos($messageLower, mb_strtolower($word)) !== false) {
                    return $project;
                }
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Gemini interaction */
    /* ------------------------------------------------------------------ */

    protected function askAi(string $intent, array $context, array $deterministic, ?Project $focusProject): array
    {
        $disclaimerBase = 'Réponse générée par une intelligence artificielle (Google Gemini) à partir de données réelles. '
            .'Les estimations sont indicatives et ne remplacent pas une analyse financière ou comptable officielle.';

        if (! $this->ai->isConfigured()) {
            return [
                'text' => $this->deterministicText($intent, $deterministic, $context),
                'ai_generated' => false,
                'ia' => $this->deterministicNarrative($intent, $deterministic),
                'disclaimer' => $disclaimerBase.' Service Gemini non configuré : analyse déterministe uniquement.',
            ];
        }

        $systemPrompt = $this->systemPrompt($intent);
        $userPrompt = $this->userPrompt($intent, $context, $deterministic, $focusProject);

        try {
            $raw = $this->ai->generateText(
                [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                $systemPrompt,
                0.2,
                2048
            );

            $decoded = $this->safeJsonDecode($raw);
            if ($decoded !== null) {
                $normalized = $this->normalizeAiAnswer($intent, $decoded, $deterministic);

                return [
                    'text' => $normalized['text'],
                    'ai_generated' => true,
                    'ia' => $normalized['ia'],
                    'disclaimer' => $disclaimerBase,
                ];
            }

            // Non-JSON response: treat as plain text but keep deterministic data available.
            return [
                'text' => $raw."\n\n".$this->deterministicText($intent, $deterministic, $context),
                'ai_generated' => true,
                'ia' => [],
                'disclaimer' => $disclaimerBase,
            ];
        } catch (GeminiAiException $e) {
            Log::warning('Project Doctor Gemini call failed, using deterministic fallback', [
                'intent' => $intent,
                'code' => $e->getStatusCode(),
                'message' => $e->getUserFriendlyMessage(),
            ]);

            return [
                'text' => $this->deterministicText($intent, $deterministic, $context),
                'ai_generated' => false,
                'ia' => $this->deterministicNarrative($intent, $deterministic),
                'disclaimer' => $disclaimerBase.' '.$e->getUserFriendlyMessage(),
            ];
        }
    }

    protected function systemPrompt(string $intent): string
    {
        $common = <<<'PROMPT'
Vous êtes Project Doctor, l'assistant d'analyse de projets d'AquaSecure.
Vous répondez en français, de manière claire, structurée et adaptée à un gestionnaire de projets.

RÈGLES STRICTES :
1. Fondez votre réponse UNIQUEMENT sur les données fournies dans le contexte. Ne jamais inventer de projets, de dates, de montants, de dépendances, de jalons ou de causes de retard.
2. Ne considérez jamais une réponse IA comme une source de vérité financière. Présentez toujours les estimations comme indicatives.
3. Ne retournez jamais d'informations sur les utilisateurs, mots de passe, tokens ou secrets.
4. Distinguez clairement les financements confirmés (statut reçu + validation approuvée) des demandes en attente (validation en attente).
5. Ne confondez pas un financement approuvé avec de l'argent effectivement reçu sans donnée de décaissement.
6. Si des données sont manquantes, signalez-le explicitement et fournissez une estimation indicative avec ses limites.
7. N'essayez jamais d'exécuter du SQL, des commandes ou des opérations administratives. Vous analysez uniquement.
8. Ne citez AUCUN montant, pourcentage, date, durée ou compteur dans la synthèse et les commentaires : ces valeurs sont calculées par le serveur et affichées séparément. Rédigez une analyse qualitative qui ne contredit pas les données déterministes fournies. Les champs numériques du schéma peuvent rester à null : le serveur les remplace par ses propres calculs.
9. Retournez STRICTEMENT un objet JSON valide, sans balisage markdown, sans texte avant ou après.
PROMPT;

        $cost = <<<'PROMPT'

TÂCHE : Estimation du coût final d'un projet.
Champs JSON requis :
- synthese : texte clair en français
- budget_initial : nombre
- budget_revise : nombre|null
- depenses_reelles : nombre
- progression : nombre
- cout_final_estime : nombre|null
- ecart_estime : nombre|null
- methode : chaîne ("extrapolation_lineaire" ou "indicatif")
- hypotheses : tableau de chaînes
- niveau_confiance : "low" | "medium" | "high"
- informations_manquantes : tableau de chaînes
- recommandations : tableau de chaînes
PROMPT;

        $delay = <<<'PROMPT'

TÂCHE : Estimation du risque de retard d'un projet.
Champs JSON requis :
- synthese : texte clair en français
- niveau_risque : "low" | "medium" | "high" | "unknown"
- progression_actuelle : nombre
- progression_temporelle_attendue : nombre|null
- ecart : nombre|null
- duree_ecoule_jours : nombre|null
- duree_totale_jours : nombre|null
- date_fin_prevue : chaîne|null
- date_estimee : chaîne|null
- causes_possibles : tableau de chaînes (seulement si justifiées par les données)
- actions_recommandees : tableau de chaînes
- limites : tableau de chaînes
PROMPT;

        $funding = <<<'PROMPT'

TÂCHE : Prévision du besoin de financement.
Champs JSON requis :
- synthese : texte clair en français
- budget_initial : nombre
- budget_reference : nombre
- cout_final_estime : nombre|null
- financements_confirmes : nombre
- financements_en_attente : nombre
- financements_rejetes : nombre
- besoin_financement_estime : nombre (max(0, coût final estimé - financements confirmés))
- pourcentage_couverture : nombre|null
- depenses_realisees : nombre|null
- encaissements_reels : nombre|null
- hypotheses : tableau de chaînes
- recommandations : tableau de chaînes
- informations_manquantes : tableau de chaînes
PROMPT;

        $overview = <<<'PROMPT'

TÂCHE : Vue d'ensemble des projets autorisés.
Champs JSON requis :
- synthese : texte clair en français
- projets : tableau d'objets (id, name, type, status, progress, budget_initial, financements_confirmes, besoin_financement, risque)
- totaux : objet (budget_initial, financements_confirmes, besoin_financement_total, count)
- alertes : tableau de chaînes (projets à risque élevé ou sous-financés)
PROMPT;

        $general = <<<'PROMPT'

TÂCHE : Répondre à une question libre sur les projets autorisés.
Champs JSON requis :
- synthese : texte clair en français
- reponse : texte de réponse détaillé
- projets_concernes : tableau d'ids de projets concernés (peut être vide)
- limite : chaîne ou null si aucune
PROMPT;

        $specific = match ($intent) {
            self::INTENT_COST_ESTIMATION => $common.$cost,
            self::INTENT_DELAY_RISK => $common.$delay,
            self::INTENT_FUNDING_FORECAST => $common.$funding,
            self::INTENT_PROJECT_OVERVIEW => $common.$overview,
            default => $common.$general,
        };

        return $common.$specific;
    }

    protected function userPrompt(string $intent, array $context, array $deterministic, ?Project $focusProject): string
    {
        $focus = $focusProject ? 'Projet prioritaire (contexte) : '.json_encode($context['focus_project'] ?? null, JSON_UNESCAPED_UNICODE) : '';

        return "CONTEXTE PROJETS (données réelles AquaSecure, ne pas divulguer en dehors) :\n"
            .json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\n\nDONNÉES DÉTERMINISTES CALCULÉES CÔTÉ SERVEUR :\n"
            .json_encode($deterministic, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\n\n"
            .$focus
            ."\n\nProduisez la réponse JSON demandée pour l'intention {$intent}.";
    }

    protected function safeJsonDecode(string $raw): ?array
    {
        $clean = trim($raw);
        if (str_starts_with($clean, '```json')) {
            $clean = substr($clean, 7);
        } elseif (str_starts_with($clean, '```')) {
            $clean = substr($clean, 3);
        }
        if (str_ends_with($clean, '```')) {
            $clean = substr($clean, 0, -3);
        }
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Ne conserve que les champs narratifs utiles et les assainit.
     *
     * Les valeurs numériques produites par l'IA sont volontairement ignorées :
     * les chiffres affichés proviennent exclusivement des calculs déterministes
     * effectués côté serveur, afin d'éviter toute hallucination chiffrée.
     */
    protected function normalizeAiAnswer(string $intent, array $raw, array $deterministic): array
    {
        $narrative = $this->buildAiNarrative($intent, $raw);

        $text = $narrative['synthese'] ?? null;

        if ($text === null || $text === '') {
            $text = $this->deterministicText($intent, $deterministic, []);
        }

        // Pour une question libre, la réponse détaillée complète la synthèse.
        if ($intent === self::INTENT_GENERAL_QUESTION && ! empty($narrative['reponse'])) {
            $text = $narrative['synthese'] !== null
                ? $narrative['synthese']."\n\n".$narrative['reponse']
                : $narrative['reponse'];
        }

        return [
            'text' => $text,
            'ai_generated' => true,
            'ia' => $narrative,
        ];
    }

    /**
     * Champs narratifs retenus pour chaque intention.
     *
     * @return list<string>
     */
    protected function aiNarrativeFields(string $intent): array
    {
        return match ($intent) {
            self::INTENT_COST_ESTIMATION => ['synthese', 'hypotheses', 'recommandations', 'informations_manquantes'],
            self::INTENT_DELAY_RISK => ['synthese', 'causes_possibles', 'actions_recommandees', 'limites'],
            self::INTENT_FUNDING_FORECAST => ['synthese', 'hypotheses', 'recommandations', 'informations_manquantes'],
            self::INTENT_PROJECT_OVERVIEW => ['synthese', 'alertes'],
            default => ['synthese', 'reponse', 'limite'],
        };
    }

    /**
     * Construit la charge utile narrative IA, filtrée et assainie.
     *
     * @return array<string, string|list<string>>
     */
    protected function buildAiNarrative(string $intent, array $raw): array
    {
        $narrative = [];

        foreach ($this->aiNarrativeFields($intent) as $field) {
            $value = $raw[$field] ?? null;

            if (in_array($field, self::AI_LIST_FIELDS, true)) {
                $items = $this->sanitizeNarrativeList($value);
                if ($items) {
                    $narrative[$field] = $items;
                }

                continue;
            }

            if (! in_array($field, self::AI_TEXT_FIELDS, true)) {
                continue;
            }

            $text = $this->sanitizeNarrativeText(is_string($value) ? $value : null);
            if ($text !== null) {
                $narrative[$field] = $text;
            }
        }

        // « limite » (texte) est exposé sous forme de liste pour un rendu homogène.
        if (! empty($narrative['limite'])) {
            $limites = $narrative['limites'] ?? [];
            $limites[] = $narrative['limite'];
            $narrative['limites'] = array_values(array_unique($limites));
            unset($narrative['limite']);
        }

        return $narrative;
    }

    /**
     * Assainit un texte narratif : suppression du HTML, des caractères de
     * contrôle et troncature.
     */
    protected function sanitizeNarrativeText(?string $value, ?int $maxLength = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = (string) $value;

        // Blocs script/style et balises HTML (sans toucher aux comparateurs « < »).
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $text) ?? $text;
        $text = preg_replace('#</?[a-z][^>]*>#i', ' ', $text) ?? $text;

        // Caractères de contrôle et caractères de largeur nulle.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = preg_replace('/[\x{200B}-\x{200F}\x{FEFF}]/u', '', $text) ?? $text;

        $text = trim(preg_replace('/[ \t]+/u', ' ', $text) ?? $text);

        if ($text === '') {
            return null;
        }

        $max = $maxLength ?? self::AI_TEXT_MAX_LENGTH;

        if (mb_strlen($text) > $max) {
            $text = rtrim(mb_substr($text, 0, $max - 1)).'…';
        }

        return $text;
    }

    /**
     * Assainit une liste de textes narratifs.
     *
     * @param  mixed  $value
     * @return list<string>
     */
    protected function sanitizeNarrativeList($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['texte'] ?? $item['text'] ?? null;
            }

            if (! is_scalar($item)) {
                continue;
            }

            $text = $this->sanitizeNarrativeText((string) $item, self::AI_ITEM_MAX_LENGTH);
            if ($text === null) {
                continue;
            }

            $items[] = $text;

            if (count($items) >= self::AI_LIST_MAX_ITEMS) {
                break;
            }
        }

        return $items;
    }

    /**
     * Notes narratives déterministes (utilisées quand l'IA est indisponible).
     *
     * @return array<string, list<string>>
     */
    protected function deterministicNarrative(string $intent, array $deterministic): array
    {
        $narrative = [];

        foreach (['informations_manquantes', 'limites'] as $field) {
            $items = $this->sanitizeNarrativeList($deterministic[$field] ?? null);
            if ($items) {
                $narrative[$field] = $items;
            }
        }

        if ($intent === self::INTENT_DELAY_RISK && ! empty($narrative['informations_manquantes'])) {
            $narrative['limites'] = $narrative['limites'] ?? $narrative['informations_manquantes'];
        }

        if ($intent === self::INTENT_PROJECT_OVERVIEW) {
            $alerts = $this->deterministicOverviewAlerts($deterministic);
            if ($alerts) {
                $narrative['alertes'] = $alerts;
            }
        }

        return $narrative;
    }

    /**
     * Alertes déterministes de la vue d'ensemble (projets à risque ou sous-financés).
     *
     * @return list<string>
     */
    protected function deterministicOverviewAlerts(array $deterministic): array
    {
        $projects = $deterministic['projects'] ?? [];
        if (! is_array($projects)) {
            return [];
        }

        $alerts = [];

        foreach ($projects as $project) {
            if (! is_array($project)) {
                continue;
            }

            $name = (string) ($project['name'] ?? 'Projet');
            $risque = (string) ($project['risque'] ?? self::RISK_UNKNOWN);
            $need = (float) ($project['besoin_financement'] ?? 0);

            if ($risque === self::RISK_HIGH) {
                $alerts[] = "{$name} : niveau de risque de retard élevé.";
            } elseif ($risque === self::RISK_MEDIUM) {
                $alerts[] = "{$name} : niveau de risque de retard moyen à surveiller.";
            }

            if ($need > 0) {
                $alerts[] = "{$name} : besoin de financement non couvert à ce jour.";
            }

            if (count($alerts) >= self::AI_LIST_MAX_ITEMS) {
                break;
            }
        }

        return $alerts;
    }

    /**
     * Deterministic French text fallback when Gemini is unavailable or returns invalid output.
     */
    protected function deterministicText(string $intent, array $deterministic, array $context): string
    {
        $lines = [];

        switch ($intent) {
            case self::INTENT_COST_ESTIMATION:
                $lines[] = 'Estimation du coût final — Analyse déterministe.';
                $lines[] = '- Budget initial : '.number_format($deterministic['budget_initial'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Dépenses réelles engagées : '.number_format($deterministic['depenses_reelles'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Progression : '.($deterministic['progression'] ?? 0).' %';
                if ($deterministic['cout_final_estime'] !== null) {
                    $lines[] = '- Coût final estimé (extrapolation linéaire) : '.number_format($deterministic['cout_final_estime'], 2, ',', ' ').' DT';
                    $lines[] = '- Écart vs budget : '.number_format($deterministic['ecart_estime'] ?? 0, 2, ',', ' ').' DT';
                    $lines[] = "- Méthode : extrapolation linéaire (dépenses / progression). C'est une hypothèse simplificatrice, non une prédiction certaine.";
                    $lines[] = '- Niveau de confiance : '.ucfirst($deterministic['niveau_confiance'] ?? 'low');
                } else {
                    $lines[] = '- Coût final estimé : non calculable (données de dépenses ou progression manquantes).';
                    $lines[] = '- Niveau de confiance : faible.';
                }
                if (! empty($deterministic['informations_manquantes'])) {
                    $lines[] = '- Informations manquantes : '.implode('; ', $deterministic['informations_manquantes']);
                }
                $lines[] = "- Recommandation : consolider le suivi des dépenses réelles pour affiner l'estimation.";
                break;

            case self::INTENT_DELAY_RISK:
                $lines[] = 'Risque de retard — Analyse déterministe.';
                $lines[] = '- Progression actuelle : '.($deterministic['progression_actuelle'] ?? 0).' %';
                if ($deterministic['progression_temporelle_attendue'] !== null) {
                    $lines[] = '- Progression temporelle attendue : '.$deterministic['progression_temporelle_attendue'].' %';
                    $lines[] = '- Écart : '.$deterministic['ecart'].' points de pourcentage';
                    $lines[] = '- Durée écoulée : '.($deterministic['duree_ecoule_jours'] ?? 0).' jour(s)';
                    $lines[] = '- Durée totale : '.($deterministic['duree_totale_jours'] ?? 0).' jour(s)';
                    $lines[] = '- Date de fin prévue : '.($deterministic['date_fin_prevue'] ?? '—');
                    $lines[] = '- Date estimée : '.($deterministic['date_estimee'] ?? 'non estimable');
                } else {
                    $lines[] = '- Dates insuffisantes pour calculer la progression temporelle.';
                }
                $lines[] = '- Niveau de risque : '.strtoupper($deterministic['niveau_risque'] ?? 'unknown');
                break;

            case self::INTENT_FUNDING_FORECAST:
                $lines[] = 'Besoin de financement — Analyse déterministe.';
                $lines[] = '- Budget initial : '.number_format($deterministic['budget_initial'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Référence budgétaire : '.number_format($deterministic['budget_reference'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Financements confirmés : '.number_format($deterministic['financements_confirmes'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Financements en attente : '.number_format($deterministic['financements_en_attente'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Besoin de financement estimé : '.number_format($deterministic['besoin_financement_estime'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Pourcentage de couverture : '.($deterministic['pourcentage_couverture'] ?? 0).' %';
                $lines[] = '- Formule : max(0, coût final estimé - financements confirmés). Les demandes en attente ne sont pas comptées comme confirmées.';
                break;

            case self::INTENT_PROJECT_OVERVIEW:
                $lines[] = "Vue d'ensemble des projets autorisés.";
                $lines[] = '- Projets analysés : '.(($context['totals']['count'] ?? 0));
                $lines[] = '- Budget total initial : '.number_format($context['totals']['budget_initial'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Financements confirmés totaux : '.number_format($context['totals']['financements_confirmes'] ?? 0, 2, ',', ' ').' DT';
                $lines[] = '- Besoin de financement total estimé : '.number_format($context['totals']['besoin_financement_total'] ?? 0, 2, ',', ' ').' DT';
                break;

            default:
                $lines[] = 'Réponse à votre question.';
                $lines[] = 'Aucune analyse automatique disponible pour cette demande sans données supplémentaires.';
                break;
        }

        return implode("\n", $lines);
    }

    protected function errorResponse(string $intent, string $message, string $code): array
    {
        return [
            'success' => false,
            'intent' => $intent,
            'answer' => $message,
            'data' => [],
            'sources' => [],
            'error' => $message,
            'code' => $code,
            'disclaimer' => '',
        ];
    }

    protected function emptyResponse(string $intent, string $message): array
    {
        return $this->errorResponse($intent, $message, 'no_data');
    }
}
