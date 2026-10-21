<?php

namespace App\Services\AI;

use App\Models\Alert;
use App\Models\Zone;
use Illuminate\Support\Collection;

/**
 * Outils d'interrogation de l'assistant conversationnel (lecture seule).
 *
 * SÉCURITÉ :
 *  - whitelist explicite (self::NAMES) : aucun autre nom n'est exécuté ;
 *  - requêtes Eloquent uniquement : le modèle ne génère JAMAIS de SQL et
 *    n'a aucun accès direct à la base ;
 *  - aucune écriture possible (create/update/delete) et aucun joint vers
 *    les entités hors périmètre (Incident, Infrastructure, User, …) ;
 *  - aucune donnée personnelle dans les sorties : uniquement des colonnes
 *    métier de zones et d'alertes.
 *
 * Chaque outil valide et borne ses arguments (limit <= 20, period_days <= 90)
 * avant toute requête.
 */
class ChatTools
{
    /** Plage autorisée pour « limit » (bornée côté outil, jamais fiable depuis le modèle). */
    public const LIMIT_MAX = 20;

    /** Plage autorisée pour « period_days » (fenêtre glissante maximale). */
    public const PERIOD_MAX = 90;

    /** Vocabulaire déclaré des alertes (colonne severity). */
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    /** Raccourcis acceptés depuis l'intention, ramenés au vocabulaire déclaré. */
    public const SEVERITY_ALIASES = ['info' => 'low', 'warning' => 'medium'];

    /** Outils autorisés (tout nom absent de cette liste est refusé). */
    public const NAMES = [
        'get_top_risk_zones',
        'get_zone_summary',
        'get_alerts',
        'count_alerts',
        'get_priority_alerts',
    ];

    /* =========================================================================
     |  Définitions au format « tools » OpenAI (envoyées telles quelles)
     * ====================================================================== */

    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        $period = [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => self::PERIOD_MAX,
            'description' => 'Fenêtre temporelle en jours (1 à 90, ex. 7 = derniers 7 jours).',
        ];

        $limit = [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => self::LIMIT_MAX,
            'description' => "Nombre maximal d'éléments à retourner (1 à 20).",
        ];

        $zone = [
            'type' => ['string', 'integer'],
            'description' => 'Nom ou identifiant de la zone AquaSecure (le nom peut être approché, ex. « tunis »).',
        ];

        $severity = [
            'type' => 'string',
            'enum' => ['low', 'medium', 'high', 'critical'],
            'description' => "Gravité déclarée de l'alerte : low, medium, high ou critical.",
        ];

        $def = fn (string $name, string $description, array $properties, array $required): array => [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ],
        ];

        return [
            $def(
                'get_top_risk_zones',
                "Retourne les zones AquaSecure les plus risquées (score IA 0-100, risque déclaré, nombre d'alertes).",
                ['limit' => $limit],
                []
            ),
            $def(
                'get_zone_summary',
                "Retourne le résumé complet d'une zone AquaSecure : risque, score IA, synthèse, alertes par gravité.",
                ['zone' => $zone],
                ['zone']
            ),
            $def(
                'get_alerts',
                'Liste les alertes AquaSecure récentes, filtrables par zone, période (jours) et gravité.',
                ['zone' => $zone, 'period_days' => $period, 'severity' => $severity, 'limit' => $limit],
                []
            ),
            $def(
                'count_alerts',
                'Compte les alertes AquaSecure (filtres zone / période / gravité) avec répartition par gravité.',
                ['zone' => $zone, 'period_days' => $period, 'severity' => $severity],
                []
            ),
            $def(
                'get_priority_alerts',
                'Retourne les alertes prioritaires (gravité élevée + récence), classées par score de priorité.',
                ['limit' => $limit],
                []
            ),
        ];
    }

    /* =========================================================================
     |  Exécution
     * ====================================================================== */

    /**
     * Exécute un outil de la whitelist avec des arguments validés.
     *
     * @return array{ok: bool, tool: string, data?: array<string, mixed>, error?: string}
     */
    public function execute(string $name, array $args): array
    {
        if (! in_array($name, self::NAMES, true)) {
            return ['ok' => false, 'tool' => $name, 'error' => 'Outil inconnu ou non autorisé.'];
        }

        try {
            $result = match ($name) {
                'get_top_risk_zones' => $this->topRiskZones($args),
                'get_zone_summary' => $this->zoneSummary($args),
                'get_alerts' => $this->listAlerts($args),
                'count_alerts' => $this->countAlerts($args),
                'get_priority_alerts' => $this->priorityAlerts($args),
                default => ['error' => 'Outil inconnu ou non autorisé.'],
            };
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'tool' => $name, 'error' => "Erreur interne lors de l'exécution de l'outil {$name}."];
        }

        if (isset($result['error'])) {
            return ['ok' => false, 'tool' => $name, 'error' => $result['error']];
        }

        return ['ok' => true, 'tool' => $name, 'data' => $result];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function topRiskZones(array $args): array
    {
        $limit = $this->clampLimit($args['limit'] ?? 5, 5);

        /** @var Collection<int, Zone> $zones */
        $zones = Zone::query()->get();

        $sorted = $zones
            ->sortBy([
                fn (Zone $a, Zone $b) => ($b->ai_risk_score ?? -1) <=> ($a->ai_risk_score ?? -1),
                fn (Zone $a, Zone $b) => $this->riskRank($b->risk_level) <=> $this->riskRank($a->risk_level),
                fn (Zone $a, Zone $b) => $a->id <=> $b->id,
            ])
            ->take($limit);

        $items = [];
        foreach ($sorted as $zone) {
            $items[] = $this->zoneRow($zone, includeSummary: false);
        }

        return [
            'count' => count($items),
            'total_zones' => $zones->count(),
            'zones' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function zoneSummary(array $args): array
    {
        $resolved = $this->resolveZone($args['zone'] ?? null);

        if ($resolved['zone'] === null) {
            return [
                'error' => 'Zone introuvable.'.(
                    $resolved['suggestions'] !== []
                        ? ' Suggestions : '.implode(', ', $resolved['suggestions']).'.'
                        : ' Liste des zones : '.implode(', ', $this->allZoneNames()).'.'
                ),
            ];
        }

        $zone = $resolved['zone'];

        $alerts = $zone->alerts()->get(['id', 'type', 'severity', 'ai_severity', 'created_at']);

        $since7 = now()->subDays(7);
        $since30 = now()->subDays(30);

        $bySeverity = array_fill_keys(self::SEVERITIES, 0);
        $byAiSeverity = ['info' => 0, 'warning' => 0, 'critical' => 0];

        foreach ($alerts as $alert) {
            $key = in_array($alert->severity, self::SEVERITIES, true) ? $alert->severity : 'medium';
            $bySeverity[$key]++;

            $aiKey = $alert->ai_severity ?? null;
            if (in_array($aiKey, array_keys($byAiSeverity), true)) {
                $byAiSeverity[$aiKey]++;
            }
        }

        $last30 = $alerts->filter(fn (Alert $a) => $a->created_at !== null && $a->created_at->gte($since30))->count();
        $last7 = $alerts->filter(fn (Alert $a) => $a->created_at !== null && $a->created_at->gte($since7))->count();

        return [
            'zone' => $this->zoneRow($zone, includeSummary: true),
            'alerts' => [
                'total' => $alerts->count(),
                'last_7_days' => $last7,
                'last_30_days' => $last30,
                'by_declared_severity' => $bySeverity,
                'by_ai_severity' => $byAiSeverity,
                'recent' => $this->alertRows($alerts->sortByDesc('created_at')->take(5)->values()),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function listAlerts(array $args): array
    {
        $limit = $this->clampLimit($args['limit'] ?? 10, 10);

        $query = Alert::query()->with('zone:id,name')->latest('created_at');

        $filters = $this->applyAlertFilters($query, $args);

        if (isset($filters['error'])) {
            return $filters;
        }

        $total = (clone $query)->count();

        $items = $this->alertRows($query->limit($limit)->get());

        return [
            'total_matching' => $total,
            'returned' => count($items),
            'filters' => $filters['filters'],
            'alerts' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function countAlerts(array $args): array
    {
        $query = Alert::query();

        $filters = $this->applyAlertFilters($query, $args);

        if (isset($filters['error'])) {
            return $filters;
        }

        $breakdown = (clone $query)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        return [
            'total' => $query->count(),
            'filters' => $filters['filters'],
            'by_declared_severity' => [
                'low' => (int) $breakdown->get('low', 0),
                'medium' => (int) $breakdown->get('medium', 0),
                'high' => (int) $breakdown->get('high', 0),
                'critical' => (int) $breakdown->get('critical', 0),
            ],
        ];
    }

    /**
     * Alertes prioritaires : aucun score de priorité n'existe en base, le
     * classement combine donc la gravité (déclarée et IA) et la récence.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function priorityAlerts(array $args): array
    {
        $limit = $this->clampLimit($args['limit'] ?? 5, 5);

        $candidates = Alert::query()
            ->with('zone:id,name')
            ->where('created_at', '>=', now()->subDays(self::PERIOD_MAX))
            ->latest('created_at')
            ->limit(200)
            ->get();

        $scored = $candidates->map(function (Alert $alert) {
            $severity = $this->severityRank($alert->severity);

            $ai = match ($alert->ai_severity) {
                'critical' => 3,
                'warning' => 2,
                'info' => 1,
                default => 0,
            };

            $ageDays = $alert->created_at ? now()->diffInDays($alert->created_at) : self::PERIOD_MAX;
            $recency = max(0, 20 - (int) round($ageDays * (20 / self::PERIOD_MAX)));

            return [
                'alert' => $alert,
                'priority_score' => min(100, ($severity * 15) + ($ai * 10) + $recency),
            ];
        })
            ->sortByDesc([
                fn (array $a, array $b) => $b['priority_score'] <=> $a['priority_score'],
                fn (array $a, array $b) => ($b['alert']->created_at?->getTimestamp() ?? 0) <=> ($a['alert']->created_at?->getTimestamp() ?? 0),
            ])
            ->take($limit);

        $items = [];
        foreach ($scored as $row) {
            $item = $this->alertRow($row['alert']);
            $item['priority_score'] = (int) $row['priority_score'];
            $item['priority_reason'] = sprintf(
                'Gravité %s%s + création le %s.',
                $row['alert']->severity_label,
                $row['alert']->ai_severity !== null ? ' (IA : '.$row['alert']->ai_severity_label.')' : '',
                $row['alert']->created_at?->format('d/m/Y') ?? '—'
            );
            $items[] = $item;
        }

        return [
            'method' => 'gravité + ancienneté (aucun score de priorité stocké en base)',
            'window_days' => self::PERIOD_MAX,
            'count' => count($items),
            'alerts' => $items,
        ];
    }

    /* =========================================================================
     |  Filtres, clampage, résolution de zone
     * ====================================================================== */

    /**
     * Applique les filtres communs zone / période / gravité à une requête.
     *
     * @param  array<string, mixed>  $args
     * @return array{filters: array<string, mixed>}|array{filters: array<string, mixed>, error: string}
     */
    protected function applyAlertFilters($query, array $args): array
    {
        $filters = ['zone' => null, 'period_days' => null, 'severity' => null];

        if (isset($args['zone']) && $args['zone'] !== null && trim((string) $args['zone']) !== '') {
            $resolved = $this->resolveZone((string) $args['zone']);

            if ($resolved['zone'] === null) {
                return [
                    'filters' => $filters,
                    'error' => 'Zone introuvable.'.(
                        $resolved['suggestions'] !== []
                            ? ' Suggestions : '.implode(', ', $resolved['suggestions']).'.'
                            : ' Liste des zones : '.implode(', ', $this->allZoneNames()).'.'
                    ),
                ];
            }

            $query->where('zone_id', $resolved['zone']->id);
            $filters['zone'] = $resolved['zone']->name;
        }

        if (isset($args['period_days']) && $args['period_days'] !== null && $args['period_days'] !== '') {
            $days = max(1, min(self::PERIOD_MAX, (int) $args['period_days']));
            $query->where('created_at', '>=', now()->subDays($days));
            $filters['period_days'] = $days;
        }

        if (isset($args['severity']) && $args['severity'] !== null && trim((string) $args['severity']) !== '') {
            $severity = strtolower(trim((string) $args['severity']));
            $severity = self::SEVERITY_ALIASES[$severity] ?? $severity;

            if (! in_array($severity, self::SEVERITIES, true)) {
                return [
                    'filters' => $filters,
                    'error' => 'Gravité invalide : '.self::severitiesToString().'.',
                ];
            }

            $query->where('severity', $severity);
            $filters['severity'] = $severity;
        }

        return ['filters' => $filters];
    }

    public static function severitiesToString(): string
    {
        return implode(' | ', self::SEVERITIES);
    }

    /**
     * Bornage strict d'une limite (l'argument vient toujours d'un modèle).
     */
    public function clampLimit(mixed $limit, int $default = self::LIMIT_MAX): int
    {
        if ($limit === null || $limit === '' || ! is_numeric($limit)) {
            return $default;
        }

        return max(1, min(self::LIMIT_MAX, (int) $limit));
    }

    /**
     * Bornage strict d'une période en jours.
     */
    public function clampPeriod(mixed $days): ?int
    {
        if ($days === null || $days === '' || ! is_numeric($days)) {
            return null;
        }

        return max(1, min(self::PERIOD_MAX, (int) $days));
    }

    /**
     * Résolution floue d'une zone par identifiant ou nom
     * (casse, accents et ponctuation ignorés, puis similarité).
     *
     * @return array{zone: Zone|null, suggestions: array<int, string>}
     */
    public function resolveZone(mixed $needle): array
    {
        if ($needle === null) {
            return ['zone' => null, 'suggestions' => []];
        }

        $needle = trim((string) $needle);

        if ($needle === '') {
            return ['zone' => null, 'suggestions' => []];
        }

        if (ctype_digit($needle)) {
            $zone = Zone::query()->find((int) $needle);
            if ($zone !== null) {
                return ['zone' => $zone, 'suggestions' => []];
            }
        }

        $normalizedNeedle = $this->normalize($needle);

        if ($normalizedNeedle === '') {
            return ['zone' => null, 'suggestions' => []];
        }

        /** @var Collection<int, Zone> $zones */
        $zones = Zone::query()->get();

        foreach ($zones as $zone) {
            if ($this->normalize($zone->name) === $normalizedNeedle) {
                return ['zone' => $zone, 'suggestions' => []];
            }
        }

        foreach ($zones as $zone) {
            if (str_contains($this->normalize($zone->name), $normalizedNeedle)
                || str_contains($normalizedNeedle, $this->normalize($zone->name))) {
                return ['zone' => $zone, 'suggestions' => []];
            }
        }

        $best = null;
        $bestPercent = 0.0;

        foreach ($zones as $zone) {
            similar_text($normalizedNeedle, $this->normalize($zone->name), $percent);
            if ($percent > $bestPercent) {
                $bestPercent = $percent;
                $best = $zone;
            }
        }

        if ($best !== null && $bestPercent >= 60.0) {
            return ['zone' => $best, 'suggestions' => []];
        }

        $suggestions = $zones
            ->map(function (Zone $zone) use ($normalizedNeedle) {
                similar_text($normalizedNeedle, $this->normalize($zone->name), $percent);

                return ['name' => $zone->name, 'percent' => $percent];
            })
            ->filter(fn (array $row) => $row['percent'] >= 40.0)
            ->sortByDesc('percent')
            ->take(3)
            ->pluck('name')
            ->values()
            ->all();

        return ['zone' => null, 'suggestions' => $suggestions];
    }

    /**
     * Normalisation d'un nom de zone pour la comparaison floue.
     * Indépendante de la plateforme : table d'accents explicite, translittération
     * iconv acceptée uniquement si elle produit du pur ASCII.
     */
    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        // Table d'accents fiable (iconv TRANSLIT n'est pas supporté partout).
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
            'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ù' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
            '’' => ' ', "'" => ' ',
        ]);

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (is_string($transliterated) && preg_match('/^[\x00-\x7F]*$/', $transliterated)) {
            $value = $transliterated;
        }

        $value = (string) preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /**
     * @return array<int, string>
     */
    public function allZoneNames(): array
    {
        return Zone::query()->orderBy('name')->pluck('name')->all();
    }

    /* =========================================================================
     |  Formatage des sorties (compact, sans donnée personnelle)
     * ====================================================================== */

    /**
     * @return array<string, mixed>
     */
    protected function zoneRow(Zone $zone, bool $includeSummary): array
    {
        $alertsTotal = $zone->alerts()->count();

        $row = [
            'id' => $zone->id,
            'name' => $zone->name,
            'address' => $zone->address !== null ? mb_substr((string) $zone->address, 0, 120) : null,
            'declared_risk_level' => $zone->risk_level,
            'declared_risk_label' => $zone->risk_label,
            'ai_risk_score' => $zone->ai_risk_score,
            'ai_risk_level' => $zone->ai_risk_level,
            'ai_risk_level_label' => $zone->ai_risk_level !== null ? $zone->ai_risk_level_label : null,
            'alerts_total' => $alertsTotal,
            'ai_analyzed_at' => $zone->ai_analyzed_at?->format('d/m/Y H:i'),
        ];

        if ($includeSummary) {
            $row['description'] = $zone->description !== null ? mb_substr((string) $zone->description, 0, 300) : null;
            $row['ai_summary'] = $zone->ai_summary !== null ? mb_substr((string) $zone->ai_summary, 0, 500) : null;

            $recommendations = [];
            foreach (array_slice((array) ($zone->ai_recommendations ?? []), 0, 3) as $item) {
                $recommendations[] = is_array($item)
                    ? mb_substr((string) ($item['action'] ?? ''), 0, 200)
                    : mb_substr((string) $item, 0, 200);
            }
            $row['ai_recommendations'] = $recommendations;

            $coordinates = $zone->coordinates();
            $row['location'] = $coordinates !== null
                ? ['latitude' => $coordinates['latitude'], 'longitude' => $coordinates['longitude'], 'radius_m' => $zone->radius_m]
                : null;
        }

        return $row;
    }

    /**
     * @param  Collection<int, Alert>  $alerts
     * @return array<int, array<string, mixed>>
     */
    protected function alertRows(Collection $alerts): array
    {
        return $alerts->map(fn (Alert $alert): array => $this->alertRow($alert))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function alertRow(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'zone_id' => $alert->zone_id,
            'zone_name' => $alert->zone?->name,
            'declared_severity' => $alert->severity,
            'declared_severity_label' => $alert->severity_label,
            'ai_severity' => $alert->ai_severity,
            'ai_severity_label' => $alert->ai_severity !== null ? $alert->ai_severity_label : null,
            'message' => mb_substr((string) $alert->message, 0, 140),
            'created_at' => $alert->created_at?->format('d/m/Y H:i') ?? '—',
            'is_read' => $alert->read_at !== null,
        ];
    }

    /**
     * Pondération d'une gravité déclarée (0..4).
     */
    protected function severityRank(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    /**
     * Pondération d'un niveau de risque de zone (ordre croissant).
     */
    protected function riskRank(?string $level): int
    {
        return match ($level) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}
