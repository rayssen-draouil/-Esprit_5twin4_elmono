<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Signalement;
use App\Models\UserZoneSubscription;
use App\Models\Zone;
use App\Support\CitizenStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pages et endpoints publics (citoyens) des zones et des alertes.
 *
 * Lecture seule et volontairement limité à des champs non sensibles :
 * aucune donnée personnelle, aucun identifiant interne, aucune note de
 * gestion n'est exposée.
 */
class PublicZoneAlertController extends Controller
{
    private function cacheTtl(): int
    {
        return max(30, (int) config('aquasecure.cache_ttl', 120));
    }

    /* ------------------------------------------------------------------ *
     * Zones
     * ------------------------------------------------------------------ */

    public function showZone(Zone $zone): View
    {
        $alerts = $zone->alerts()
            ->whereNull('resolved_at')
            ->latest()
            ->limit(6)
            ->get();

        $activeAlerts = $alerts->map(fn (Alert $alert) => $this->alertPayload($alert))->all();

        $chart = $this->zoneChart($zone);

        $riskLevel = $zone->effectiveRiskLevel();
        $summary = filled($zone->ai_summary)
            ? (string) $zone->ai_summary
            : $this->fallbackSummary($riskLevel);

        return view('front.zones.show', [
            'zone' => $zone,
            'activeAlerts' => $activeAlerts,
            'activeCount' => count($activeAlerts),
            'chart' => $chart,
            'riskLevel' => $riskLevel,
            'summary' => $summary,
            'metaDescription' => $summary,
        ]);
    }

    /** Données de carte publiques (zones géolocalisées uniquement). */
    public function mapData(): JsonResponse
    {
        $payload = Cache::remember('front.zones.map-data', $this->cacheTtl(), function () {
            return Zone::query()
                ->withCount(['alerts' => fn ($query) => $query->whereNull('resolved_at')])
                ->orderBy('name')
                ->get(['id', 'name', 'address', 'risk_level', 'ai_risk_level', 'ai_risk_score', 'latitude', 'longitude', 'radius_m'])
                ->filter(fn (Zone $zone) => $zone->hasCoordinates())
                ->map(fn (Zone $zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'address' => $zone->address,
                    'risk' => $zone->effectiveRiskLevel(),
                    'risk_level' => $zone->effectiveRiskLevel(),
                    'risk_label' => $zone->risk_label,
                    'ai_risk_level' => $zone->ai_risk_level,
                    'ai_risk_level_label' => $zone->ai_risk_level ? $zone->ai_risk_level_label : null,
                    'ai_risk_score' => $zone->ai_risk_score,
                    'alerts' => (int) $zone->alerts_count,
                    'radius_m' => $zone->radius_m,
                    'latitude' => (float) $zone->latitude,
                    'longitude' => (float) $zone->longitude,
                    'url' => route('front.zones.show', $zone),
                ])
                ->values()
                ->all();
        });

        return response()
            ->json(['success' => true, 'zones' => $payload])
            ->header('Cache-Control', 'public, max-age='.$this->cacheTtl());
    }

    /** Zone la plus proche d'une position (géolocalisation navigateur). */
    public function nearest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        $zones = Zone::query()
            ->withCount(['alerts' => fn ($query) => $query->whereNull('resolved_at')])
            ->get(['id', 'name', 'address', 'risk_level', 'ai_risk_level', 'latitude', 'longitude'])
            ->filter(fn (Zone $zone) => $zone->hasCoordinates());

        if ($zones->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune zone géolocalisée n’est disponible pour le moment.',
            ], 404);
        }

        $nearest = $zones
            ->map(function (Zone $zone) use ($lat, $lng) {
                $distance = $this->distanceKm($lat, $lng, (float) $zone->latitude, (float) $zone->longitude);

                return ['zone' => $zone, 'distance' => $distance];
            })
            ->sortBy('distance')
            ->first();

        $zone = $nearest['zone'];

        return response()->json([
            'success' => true,
            'zone' => [
                'id' => $zone->id,
                'name' => $zone->name,
                'address' => $zone->address,
                'risk' => $zone->effectiveRiskLevel(),
                'risk_label' => $zone->risk_label,
                'alerts' => (int) $zone->alerts_count,
                'distance_km' => round((float) $nearest['distance'], 2),
                'url' => route('front.zones.show', $zone),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /* ------------------------------------------------------------------ *
     * Alertes
     * ------------------------------------------------------------------ */

    public function showAlert(Alert $alert): View
    {
        $alert->loadMissing('zone');

        return view('front.alerts.show', [
            'alert' => $alert,
            'zone' => $alert->zone,
            'explanation' => $alert->citizenExplanation(),
            'actions' => $alert->citizenActions(),
            'timeline' => $alert->publicTimeline(),
            'metaDescription' => Str::limit($alert->citizenExplanation(), 150),
        ]);
    }

    /** Endpoint public léger pour l'actualisation du fil d'alertes. */
    public function live(Request $request): JsonResponse
    {
        $since = $request->integer('since');

        $kpis = Cache::remember('front.alerts.kpis', 30, fn () => [
            'open' => Alert::whereNull('acknowledged_at')->whereNull('resolved_at')->count(),
            'critical' => Alert::where(fn ($q) => $q->where('severity', 'critical')->orWhere('ai_severity', 'critical'))
                ->whereNull('resolved_at')
                ->count(),
            'resolved_today' => Alert::whereNotNull('resolved_at')->where('resolved_at', '>=', now()->startOfDay())->count(),
        ]);

        $query = Alert::query()->whereNull('resolved_at');

        $newCount = $since > 0
            ? (clone $query)->where('created_at', '>', now()->setTimestamp($since))->count()
            : 0;

        $latest = (clone $query)
            ->with('zone:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Alert $alert) => [
                'type' => $alert->type,
                'zone' => $alert->zone->name ?? 'Zone non définie',
                'severity' => $alert->severity_label,
                'severityLevel' => $alert->severity,
                'date' => $alert->created_at?->format('d/m/Y H:i'),
                'url' => route('front.alerts.show', $alert),
            ]);

        return response()->json([
            'success' => true,
            'new_count' => $newCount,
            'kpis' => $kpis,
            'latest' => $latest,
            'last_at' => $latest->first()['date'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    /* ------------------------------------------------------------------ *
     * Suivi de signalement
     * ------------------------------------------------------------------ */

    public function trackReport(Request $request): View
    {
        $reference = trim((string) $request->query('ref', ''));
        $tracking = null;
        $error = null;

        if ($reference !== '') {
            $signalement = $this->findTrackedReport($reference);

            if ($signalement) {
                $signalement->loadMissing('incident.zone');

                $tracking = [
                    'reference' => $signalement->publicReference(),
                    'token' => $signalement->tracking_token,
                    'type' => $signalement->type ?: 'Signalement',
                    'location' => $signalement->location,
                    'zone' => $signalement->incident?->zone?->name,
                    'status' => $signalement->statusLabel(),
                    'reported_at' => $signalement->reported_at?->format('d/m/Y à H:i'),
                    'resolved_at' => $signalement->incident?->resolved_at?->format('d/m/Y à H:i'),
                    'timeline' => CitizenStatus::signalementTimeline($signalement),
                ];
            } else {
                $error = 'Aucun dossier ne correspond à cette référence. Vérifiez la saisie (ex. AQ-ABCD-1234).';
            }
        }

        return view('front.track', [
            'reference' => $reference,
            'tracking' => $tracking,
            'error' => $error,
            'metaDescription' => 'Suivez l’avancement de votre signalement AquaSecure en toute confidentialité.',
        ]);
    }

    private function findTrackedReport(string $reference): ?Signalement
    {
        $normalized = strtoupper(str_replace(' ', '', $reference));

        // Référence opaque : accessible à tous (aucune énumération possible).
        if (preg_match('/^AQ-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $normalized)) {
            return Signalement::where('tracking_token', $normalized)->first();
        }

        // Référence lisible SIG-0012 / INC-0003 : réservée au propriétaire connecté.
        if (! auth()->check()) {
            return null;
        }

        if (preg_match('/^(?:SIG|SGL|SIGNALEMENT)-?(\d+)$/', $normalized, $matches)) {
            return Signalement::where('user_id', auth()->id())->find((int) $matches[1]);
        }

        if (preg_match('/^(?:INC|INCIDENT)-?(\d+)$/', $normalized, $matches)) {
            return Signalement::where('user_id', auth()->id())
                ->where('incident_id', (int) $matches[1])
                ->first();
        }

        return null;
    }

    /* ------------------------------------------------------------------ *
     * Zones suivies (utilisateurs connectés)
     * ------------------------------------------------------------------ */

    public function subscribe(Zone $zone): JsonResponse|RedirectResponse
    {
        UserZoneSubscription::firstOrCreate([
            'user_id' => auth()->id(),
            'zone_id' => $zone->id,
        ]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'subscribed' => true, 'zone_id' => $zone->id]);
        }

        return back()->with('success', 'Vous suivez désormais la zone « '.$zone->name.' ».');
    }

    public function unsubscribe(Zone $zone): JsonResponse|RedirectResponse
    {
        UserZoneSubscription::where('user_id', auth()->id())
            ->where('zone_id', $zone->id)
            ->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'subscribed' => false, 'zone_id' => $zone->id]);
        }

        return back()->with('success', 'Vous ne suivez plus la zone « '.$zone->name.' ».');
    }

    /** Identifiants des zones suivies (utilisateur connecté). */
    public function subscriptions(): JsonResponse
    {
        $ids = UserZoneSubscription::where('user_id', auth()->id())->pluck('zone_id');

        return response()->json(['success' => true, 'zone_ids' => $ids]);
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function alertPayload(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'message' => Str::limit((string) $alert->message, 150),
            'severity' => $alert->severity,
            'severityLabel' => $alert->severity_label,
            'aiSeverity' => $alert->ai_severity,
            'status' => $alert->status_label,
            'statusLevel' => $alert->status(),
            'date' => $alert->created_at?->format('d/m/Y H:i'),
            'url' => route('front.alerts.show', $alert),
        ];
    }

    /**
     * @return array{labels: array<int, string>, counts: array<int, int>, total: int}
     */
    private function zoneChart(Zone $zone): array
    {
        $counts = $zone->alerts()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->pluck('created_at')
            ->groupBy(fn ($date) => $date->format('Y-m-d'))
            ->map->count();

        $labels = [];
        $values = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('Y-m-d');
            $values[] = (int) ($counts[$day->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'counts' => $values, 'total' => array_sum($values)];
    }

    private function fallbackSummary(string $riskLevel): string
    {
        return match ($riskLevel) {
            'critical' => "Cette zone fait l'objet d'une vigilance maximale. Nos équipes sont mobilisées et des restrictions d'usage peuvent s'appliquer.",
            'high' => 'La situation est sensible dans cette zone. Nos équipes surveillent le réseau de près et vous recommandent la prudence.',
            'medium' => 'La situation est stable, avec quelques points de vigilance suivis par nos équipes.',
            default => 'La situation est normale dans cette zone. Le réseau est suivi en continu.',
        };
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }
}
