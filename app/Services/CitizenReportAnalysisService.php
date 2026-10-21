<?php

namespace App\Services;

use App\Models\Signalement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CitizenReportAnalysisService
{
    public function __construct(
        protected GroqAiService $groqService
    ) {}

    /**
     * Analyze a citizen signalement report using AI and hybrid duplicate detection.
     */
    public function analyze(Signalement|int $signalement, bool $persist = true): array
    {
        $report = is_numeric($signalement)
            ? Signalement::with(['incident.zone', 'user'])->findOrFail($signalement)
            : $signalement->loadMissing(['incident.zone', 'user']);

        // 1. Identify candidate duplicate reports in DB
        $duplicateCandidates = $this->findDuplicateCandidates($report);

        // 2. Call Groq AI or fallback
        $analysis = null;
        $status = 'completed';

        if ($this->groqService->isConfigured()) {
            try {
                $rawAi = $this->callGroqReportAnalysis($report, $duplicateCandidates);
                $analysis = $this->validateAndNormalizeReportAnalysis($rawAi, $report, $duplicateCandidates);
            } catch (\Throwable $e) {
                Log::warning('Groq citizen report analysis failed, using deterministic fallback', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
                $analysis = $this->generateDeterministicReportAnalysis($report, $duplicateCandidates);
                $status = 'deterministic_fallback';
            }
        } else {
            $analysis = $this->generateDeterministicReportAnalysis($report, $duplicateCandidates);
            $status = 'deterministic_fallback';
        }

        $analysis['status'] = $status;
        $analysis['analyzed_at'] = Carbon::now()->toIso8601String();
        $analysis['analyzed_at_display'] = Carbon::now()->format('d/m/Y à H:i');

        // 3. Persist analysis separately without altering original citizen report text
        if ($persist) {
            $report->ai_analysis = $analysis;
            $report->ai_status = $status === 'completed' ? 'completed' : 'fallback';
            $report->save();
        }

        return $analysis;
    }

    /**
     * Find plausible duplicate candidate reports submitted in the last 30 days.
     */
    public function findDuplicateCandidates(Signalement $report): Collection
    {
        $since = now()->subDays(30);

        return Signalement::where('id', '!=', $report->id)
            ->where('reported_at', '>=', $since)
            ->where(function ($q) use ($report) {
                if (! empty($report->location)) {
                    $q->where('location', 'like', '%'.substr($report->location, 0, 15).'%');
                }
                if (! empty($report->type)) {
                    $q->orWhere('type', $report->type);
                }
            })
            ->latest('reported_at')
            ->limit(5)
            ->get(['id', 'type', 'description', 'location', 'reported_at', 'status']);
    }

    /**
     * Send anonymous report details to Groq AI.
     */
    protected function callGroqReportAnalysis(Signalement $report, Collection $candidates): array
    {
        $systemPrompt = <<<'PROMPT'
Vous êtes le classificateur d'incidents et de signalements citoyens pour la régie d'eau AquaSecure.
Votre mission est d'analyser le signalement rédigé par un usager, d'en extraire la typologie technique, la gravité et de détecter d'éventuels signalements doublons.

RÈGLES OPÉRATIONNELLES :
1. Ne modifiez jamais le texte original du citoyen : produisez uniquement une analyse d'aide à la décision pour le gestionnaire.
2. Catégories autorisées pour 'suggested_category' : 'fuite', 'coupure', 'contamination', 'panne', 'pression', 'qualite', 'degat', 'autre'.
3. Gravité autorisée pour 'suggested_severity' et 'suggested_priority' : 'low', 'medium', 'high', 'critical'.
4. Détection de doublons : examinez les signalements candidats fournis. N'identifiez comme doublon plausible QUE les signalements traitant manifestement du même événement au même endroit. Mentionnez toujours : "Possible doublon — revue requise".
5. Restituez STRICTEMENT un objet JSON valide conforme au schéma demandé.
PROMPT;

        $userContext = [
            'report' => [
                'id' => $report->id,
                'declared_type' => $report->type,
                'description' => $report->description,
                'location' => $report->location,
                'declared_priority' => $report->priority,
                'reported_at' => $report->reported_at?->format('d/m/Y H:i'),
            ],
            'candidate_duplicates' => $candidates->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'description' => substr((string) $c->description, 0, 140),
                'location' => $c->location,
                'reported_at' => $c->reported_at?->format('d/m/Y H:i'),
            ])->toArray(),
        ];

        $userPrompt = "Analysez ce signalement citoyen et évaluez les doublons potentiels :\n\n"
            .json_encode($userContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\n\nProduisez un JSON structuré contenant exactement :\n"
            ."- report_id : {$report->id}\n"
            ."- suggested_category : 'fuite' | 'coupure' | 'contamination' | 'panne' | 'pression' | 'qualite' | 'degat' | 'autre'\n"
            ."- suggested_severity : 'low' | 'medium' | 'high' | 'critical'\n"
            ."- suggested_priority : 'low' | 'medium' | 'high' | 'critical'\n"
            ."- summary : Synthèse technique concise du dysfonctionnement\n"
            ."- location_details : Précisions topographiques ou de repère extraites\n"
            ."- recommended_next_action : Prochaine étape recommandée pour le gestionnaire\n"
            ."- possible_duplicate_reports : Tableau d'IDs des signalements doublons probables (ex: [12, 15])\n"
            ."- duplicate_reason : Explication du lien ou mention 'Aucun doublon détecté'\n"
            ."- uncertainties : Incertitudes ou éléments ambigus dans la déclaration citoyenne\n"
            ."- requires_human_review : true ou false\n";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        return $this->groqService->chatCompletionJson($messages, 0.1);
    }

    /**
     * Validate and normalize the report analysis.
     */
    protected function validateAndNormalizeReportAnalysis(array $raw, Signalement $report, Collection $candidates): array
    {
        $allowedCategories = ['fuite', 'coupure', 'contamination', 'panne', 'pression', 'qualite', 'degat', 'autre'];
        $category = strtolower((string) ($raw['suggested_category'] ?? 'autre'));
        if (! in_array($category, $allowedCategories, true)) {
            $category = $this->guessCategoryFromText($report->description);
        }

        $allowedLevels = ['low', 'medium', 'high', 'critical'];
        $severity = strtolower((string) ($raw['suggested_severity'] ?? 'medium'));
        if (! in_array($severity, $allowedLevels, true)) {
            $severity = 'medium';
        }

        $priority = strtolower((string) ($raw['suggested_priority'] ?? $severity));
        if (! in_array($priority, $allowedLevels, true)) {
            $priority = $severity;
        }

        // Validate duplicate report IDs (must be in candidate set)
        $candidateIds = $candidates->pluck('id')->toArray();
        $rawDuplicates = is_array($raw['possible_duplicate_reports'] ?? null) ? $raw['possible_duplicate_reports'] : [];
        $validDuplicates = array_values(array_intersect($rawDuplicates, $candidateIds));

        $dupReason = (string) ($raw['duplicate_reason'] ?? '');
        if (! empty($validDuplicates)) {
            $dupReason = 'Possible doublon — revue requise : '.($dupReason ?: 'Similitude de localisation et de description avec le(s) signalement(s) #'.implode(', #', $validDuplicates));
        } else {
            $dupReason = 'Aucun signalement doublon identifié parmi les signalements récents.';
        }

        return [
            'report_id' => $report->id,
            'suggested_category' => $category,
            'suggested_category_label' => ucfirst($category),
            'suggested_severity' => $severity,
            'suggested_priority' => $priority,
            'summary' => (string) ($raw['summary'] ?? substr((string) $report->description, 0, 160)),
            'location_details' => (string) ($raw['location_details'] ?? ($report->location ?? 'Non précisé')),
            'recommended_next_action' => (string) ($raw['recommended_next_action'] ?? 'Vérifier sur le terrain et qualifier l\'incident.'),
            'possible_duplicate_reports' => $validDuplicates,
            'duplicate_reason' => $dupReason,
            'uncertainties' => is_array($raw['uncertainties'] ?? null) ? array_map('strval', $raw['uncertainties']) : [],
            'requires_human_review' => (bool) ($raw['requires_human_review'] ?? true),
            'original_description' => $report->description,
            'is_ai_generated' => true,
        ];
    }

    /**
     * Deterministic report analysis fallback.
     */
    public function generateDeterministicReportAnalysis(Signalement $report, Collection $candidates): array
    {
        $category = $this->guessCategoryFromText($report->description);
        $severity = strtolower((string) ($report->priority ?? 'medium'));
        if (! in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $severity = 'medium';
        }

        // Check duplicates by exact location or matching type
        $matching = $candidates->filter(function ($c) use ($report) {
            return ! empty($report->location) && strtolower($c->location) === strtolower($report->location);
        });

        $dupIds = $matching->pluck('id')->toArray();
        $dupReason = ! empty($dupIds)
            ? 'Possible doublon — revue requise : Même localisation déclarée que le(s) signalement(s) #'.implode(', #', $dupIds)
            : 'Aucun doublon évident détecté dans le secteur.';

        return [
            'report_id' => $report->id,
            'suggested_category' => $category,
            'suggested_category_label' => ucfirst($category),
            'suggested_severity' => $severity,
            'suggested_priority' => $severity,
            'summary' => 'Signalement citoyen enregistré : '.substr((string) $report->description, 0, 140),
            'location_details' => $report->location ?: 'Non renseigné',
            'recommended_next_action' => 'Contrôler le signalement et décider de l\'ouverture d\'un incident.',
            'possible_duplicate_reports' => $dupIds,
            'duplicate_reason' => $dupReason,
            'uncertainties' => ['Classification basée sur les mots-clés du signalement sans évaluation LLM'],
            'requires_human_review' => true,
            'original_description' => $report->description,
            'is_ai_generated' => false,
        ];
    }

    protected function guessCategoryFromText(?string $text): string
    {
        $lower = strtolower((string) $text);
        if (str_contains($lower, 'fuite') || str_contains($lower, 'écoulement') || str_contains($lower, 'jaillit')) {
            return 'fuite';
        }
        if (str_contains($lower, 'coupure') || str_contains($lower, 'arret') || str_contains($lower, 'sec')) {
            return 'coupure';
        }
        if (str_contains($lower, 'couleur') || str_contains($lower, 'odeur') || str_contains($lower, 'marron') || str_contains($lower, 'contamination')) {
            return 'contamination';
        }
        if (str_contains($lower, 'pression') || str_contains($lower, 'faible débit')) {
            return 'pression';
        }
        if (str_contains($lower, 'pompe') || str_contains($lower, 'moteur') || str_contains($lower, 'panne')) {
            return 'panne';
        }

        return 'autre';
    }
}
