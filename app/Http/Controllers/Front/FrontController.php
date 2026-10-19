<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Http\Requests\ReportMalfunctionRequest;
use App\Models\Financement;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\Project;
use App\Models\Signalement;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FrontController extends Controller
{
    private function shared(): array
    {
        return [
            'stats' => [
                'Sites sécurisés' => (string) max(Infrastructure::count(), 128),
                'Alertes traitées' => (string) max(Incident::count() * 120, 2480),
                'Communes partenaires' => (string) max(Zone::count() * 11, 34),
            ]
        ];
    }

    public function home(): View
    {
        $ttl = max(30, (int) config('aquasecure.cache_ttl', 120));

        $criticalAlerts = Cache::remember('front.home.critical', $ttl, fn () => Alert::query()
            ->with('zone:id,name')
            ->whereNull('resolved_at')
            ->where(fn ($query) => $query->where('severity', 'critical')->orWhere('ai_severity', 'critical'))
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Alert $alert) => [
                'type' => $alert->type,
                'zone' => $alert->zone->name ?? 'Zone non définie',
                'date' => $alert->created_at?->format('d/m/Y H:i'),
                'url' => route('front.alerts.show', $alert),
            ])
            ->all());

        $publicStats = Cache::remember('front.home.stats', $ttl, fn () => [
            'active_alerts' => Alert::whereNull('resolved_at')->count(),
            'zones' => Zone::count(),
            'resolved_month' => Alert::whereNotNull('resolved_at')
                ->where('resolved_at', '>=', now()->startOfMonth())
                ->count(),
            'critical' => Alert::whereNull('resolved_at')
                ->where(fn ($query) => $query->where('severity', 'critical')->orWhere('ai_severity', 'critical'))
                ->count(),
        ]);

        return view('front.home', $this->shared() + [
            'criticalAlerts' => $criticalAlerts,
            'publicStats' => $publicStats,
            'featuredProjects' => $this->projectsData(),
            'networkStatus' => [
                ['icon' => '◉', 'label' => "Qualité de l'eau", 'value' => '98.7', 'unit' => '%', 'status' => 'Excellent', 'change' => '+2.4 %', 'tone' => 'success'],
                ['icon' => '↕', 'label' => 'Pression du réseau', 'value' => '4.2', 'unit' => 'bar', 'status' => 'Normal', 'change' => '+0.8 %', 'tone' => 'info'],
                ['icon' => '◒', 'label' => 'Niveau des réservoirs', 'value' => '76', 'unit' => '%', 'status' => 'Attention', 'change' => '-3.1 %', 'tone' => 'warning'],
                ['icon' => '⌁', 'label' => 'Réseau global', 'value' => '99.2', 'unit' => '%', 'status' => 'Opérationnel', 'change' => '+1.2 %', 'tone' => 'success'],
            ],
            'recentIncidents' => $this->incidentsData(),
            'infrastructures' => $this->infrastructureData(),
        ]);
    }

    public function about(): View
    {
        return view('front.about', $this->shared());
    }

    public function services(): View
    {
        return view('front.services', $this->shared());
    }

    public function projects(): View
    {
        return view('front.projects.index', $this->shared() + ['projects' => $this->projectsData()]);
    }

    public function funding(): View
    {
        $programs = Financement::pluck('source')->unique()->toArray();
        if (empty($programs)) {
            $programs = ['Fonds bleu européen', 'Aqua Transition', 'Résilience territoriale'];
        }
        return view('front.funding', $this->shared() + ['programs' => $programs]);
    }

    public function news(): View
    {
        return view('front.news', $this->shared() + [
            'articles' => [
                'AquaSecure déploie son réseau de capteurs nouvelle génération',
                'Un nouveau partenariat pour protéger nos littoraux',
                'Bilan de la saison hydrologique 2025'
            ]
        ]);
    }

    public function contact(): View
    {
        return view('front.contact', $this->shared());
    }

    public function zones(Request $request): View
    {
        $sortMap = [
            'risk' => ['ai_risk_score', 'desc'],
            'score' => ['ai_risk_score', 'desc'],
            'incidents' => ['incidents_count', 'desc'],
            'alerts' => ['alerts_count', 'desc'],
            'analyzed' => ['ai_analyzed_at', 'desc'],
            'last_alert' => ['alerts_max_created_at', 'desc'],
            'name' => ['name', 'asc'],
        ];
        $sortKey = $request->string('sort')->toString();
        if (! array_key_exists($sortKey, $sortMap)) {
            $sortKey = 'name';
        }

        $applyFilters = fn ($query) => $query
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$this->likeTerm($request->string('q')).'%')
                ->orWhere('address', 'like', '%'.$this->likeTerm($request->string('q')).'%')))
            ->when($request->filled('region'), fn ($q) => $q->where('address', 'like', '%'.$this->likeTerm($request->string('region')).'%'))
            ->when($request->input('geo') === 'with', fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude'))
            ->when($request->input('geo') === 'without', fn ($q) => $q->whereNull('latitude'));

        $zones = Zone::withCount(['infrastructures', 'incidents', 'alerts'])
            ->withMax('alerts', 'created_at')
            ->tap($applyFilters)
            ->when($request->filled('risk'), fn ($q) => $q->where('risk_level', $request->string('risk')))
            ->orderBy(...$sortMap[$sortKey])
            ->paginate(12)
            ->withQueryString();

        $zones->setCollection($zones->getCollection()->map(fn ($z) => [
            'id' => $z->id,
            'name' => $z->name,
            'address' => $z->address,
            'description' => $z->description,
            'image' => $z->image_path ? asset('storage/'.$z->image_path) : null,
            'risk' => $z->risk_label,
            'riskLevel' => $z->risk_level,
            'aiRiskLevel' => $z->ai_risk_level,
            'aiScore' => $z->ai_risk_score,
            'aiAnalyzedAt' => $z->ai_analyzed_at?->format('d/m/Y'),
            'infrastructures' => $z->infrastructures_count,
            'incidents' => $z->incidents_count,
            'alertsCount' => $z->alerts_count,
            'lastAlertAt' => $z->alerts_max_created_at,
            'lastAlert' => $z->alerts_max_created_at
                ? Carbon::parse($z->alerts_max_created_at)->diffForHumans()
                : null,
            'hasCoordinates' => $z->hasCoordinates(),
            'url' => route('front.zones.show', $z),
        ]));

        $riskCounts = $applyFilters(Zone::query())
            ->groupBy('risk_level')
            ->selectRaw('risk_level, COUNT(*) as total')
            ->pluck('total', 'risk_level');

        $kpis = [
            'total' => Zone::count(),
            'highRisk' => Zone::where(fn ($q) => $q->where('risk_level', 'high')->orWhere('ai_risk_level', 'critical'))->count(),
            'avgScore' => Zone::whereNotNull('ai_risk_score')->avg('ai_risk_score'),
            'analyzed' => Zone::whereNotNull('ai_analyzed_at')->count(),
        ];

        $regions = Zone::query()
            ->pluck('address')
            ->filter()
            ->map(function ($address) {
                $parts = explode(',', (string) $address);

                return trim((string) end($parts));
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('front.zones.index', $this->shared() + [
            'zones' => $zones,
            'kpis' => $kpis,
            'riskCounts' => $riskCounts,
            'regions' => $regions,
            'sortKey' => $sortKey,
        ]);
    }

    public function alerts(Request $request): View|JsonResponse
    {
        $sortKey = $request->string('sort')->toString();
        if (! in_array($sortKey, ['date', 'priority', 'oldest'], true)) {
            $sortKey = 'priority';
        }

        $applyFilters = function ($query, array $skip = []) use ($request) {
            $query
                ->when($request->filled('q') && ! in_array('q', $skip), function ($q) use ($request) {
                    $term = '%'.$this->likeTerm($request->string('q')).'%';
                    $q->where(fn ($w) => $w->where('type', 'like', $term)->orWhere('message', 'like', $term));
                })
                ->when($request->filled('severity') && ! in_array('severity', $skip), fn ($q) => $q->where('severity', $request->string('severity')))
                ->when($request->filled('zone') && ! in_array('zone', $skip), fn ($q) => $q->where('zone_id', $request->integer('zone')))
                ->when($request->input('status') === 'open' && ! in_array('status', $skip), fn ($q) => $q->whereNull('acknowledged_at')->whereNull('resolved_at'))
                ->when($request->input('status') === 'acknowledged' && ! in_array('status', $skip), fn ($q) => $q->whereNotNull('acknowledged_at')->whereNull('resolved_at'))
                ->when($request->input('status') === 'resolved' && ! in_array('status', $skip), fn ($q) => $q->whereNotNull('resolved_at'));

            if (in_array('period', $skip)) {
                return $query;
            }

            $from = (string) $request->input('from');
            $to = (string) $request->input('to');
            if (! in_array('from', $skip) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
            }
            if (! in_array('to', $skip) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
            }
            if (! $request->filled('from') && ! $request->filled('to')) {
                $query
                    ->when($request->input('period') === 'today', fn ($q) => $q->where('created_at', '>=', now()->startOfDay()))
                    ->when($request->input('period') === '7d', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
                    ->when($request->input('period') === '30d', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)));
            }

            return $query;
        };

        $kpis = [
            'total' => Alert::count(),
            'open' => Alert::whereNull('acknowledged_at')->whereNull('resolved_at')->count(),
            'critical' => Alert::where(fn ($q) => $q->where('severity', 'critical')->orWhere('ai_severity', 'critical'))->count(),
            'last24h' => Alert::where('created_at', '>=', now()->subDay())->count(),
        ];

        $chart = $this->alertsChart($applyFilters);

        if ($request->boolean('live')) {
            $key = 'front-alerts-live:'.request()->ip();
            $hits = Cache::increment($key);
            if ($hits === 1) {
                Cache::put($key, 1, 60);
            }
            if ($hits > 30) {
                abort(429, 'Trop de requêtes, réessayez dans une minute.');
            }

            $since = $request->integer('since');
            $fresh = $applyFilters(Alert::query());
            $newCount = $since > 0
                ? (clone $fresh)->where('created_at', '>', now()->setTimestamp($since))->count()
                : 0;

            $latest = (clone $fresh)->with('zone')->latest()->limit(5)->get()->map(fn ($a) => [
                'type' => $a->type,
                'message' => Str::limit($a->message, 70),
                'zone' => $a->zone->name ?? 'Zone non définie',
                'severity' => $a->severity_label,
                'severityLevel' => $a->severity,
                'status' => $a->status_label,
                'statusLevel' => $a->status(),
                'date' => $a->created_at?->format('d M Y H:i'),
            ]);

            return response()->json([
                'success' => true,
                'new_count' => $newCount,
                'kpis' => $kpis,
                'latest' => $latest,
                'updated_at' => now()->toIso8601String(),
            ])->header('Cache-Control', 'no-store');
        }

        $severityCounts = $applyFilters(Alert::query(), ['severity'])
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as total')
            ->pluck('total', 'severity');

        $alerts = $applyFilters(Alert::with(['zone', 'incident']));
        if ($sortKey === 'priority') {
            $alerts->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")->latest();
        } elseif ($sortKey === 'oldest') {
            $alerts->oldest();
        } else {
            $alerts->latest();
        }

        $alerts = $alerts->paginate(12)->withQueryString();
        $alerts->setCollection($alerts->getCollection()->map(fn ($a) => [
            'type' => $a->type,
            'message' => $a->message,
            'zone' => $a->zone->name ?? 'Zone non définie',
            'zoneId' => $a->zone_id,
            'zoneImage' => $a->zone?->image_path ? asset('storage/'.$a->zone->image_path) : null,
            'severity' => $a->severity_label,
            'severityLevel' => $a->severity,
            'status' => $a->status_label,
            'statusLevel' => $a->status(),
            'aiSeverity' => $a->ai_severity,
            'date' => $a->created_at?->format('d/m/Y H:i'),
            'url' => route('front.alerts.show', $a),
        ]));

        return view('front.alerts.index', $this->shared() + [
            'alerts' => $alerts,
            'zones' => Zone::orderBy('name')->get(['id', 'name']),
            'kpis' => $kpis,
            'severityCounts' => $severityCounts,
            'chart' => $chart,
            'sortKey' => $sortKey,
        ]);
    }

    private function alertsChart(callable $applyFilters): array
    {
        $rows = $applyFilters(Alert::query(), ['period', 'from', 'to'])
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->pluck('created_at');

        $byDay = [];
        foreach ($rows as $createdAt) {
            $key = $createdAt->format('Y-m-d');
            $byDay[$key] = ($byDay[$key] ?? 0) + 1;
        }

        $labels = [];
        $counts = [];
        for ($i = 29; $i >= 0; $i--) {
            $key = now()->subDays($i)->format('Y-m-d');
            $labels[] = $key;
            $counts[] = $byDay[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
            'total' => array_sum($counts),
            'from' => now()->subDays(29)->format('d/m/Y'),
        ];
    }

    /** Échappe les jokers LIKE saisis par l'utilisateur. */
    private function likeTerm($value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], (string) $value);
    }

    public function incidents(): View
    {
        return view('front.incidents.index', $this->shared() + ['incidents' => $this->incidentsData()]);
    }

    public function createIncident(): View
    {
        $zones = Zone::all();
        $infrastructures = Infrastructure::all();
        return view('front.incidents.create', $this->shared() + compact('zones', 'infrastructures'));
    }

    public function storeIncident(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'title' => 'required|string|min:3|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'priority' => 'nullable|string|max:50',
            'zone_id' => 'nullable|exists:zones,id',
            'infrastructure_id' => 'nullable|exists:infrastructures,id',
            'incident_date' => 'nullable|date',
            'photo' => 'nullable|image|max:4096',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('incidents', 'public');
        }

        $zone = null;
        if (!empty($validated['zone_id'])) {
            $zone = Zone::find($validated['zone_id']);
        }
        if (!$zone) {
            $zone = Zone::firstOrCreate(
                ['name' => 'Secteur Local'],
                ['address' => $validated['location'], 'risk_level' => 'medium']
            );
        }

        $signalement = Signalement::create([
            'user_id' => auth()->id(),
            'reporter_name' => auth()->user()->name,
            'reporter_email' => auth()->user()->email,
            'type' => $validated['type'],
            'description' => $validated['description'] . (!empty($validated['title']) ? ' — ' . $validated['title'] : ''),
            'location' => $validated['location'],
            'photo_path' => $photoPath,
            'priority' => $validated['priority'] ?? null,
            'status' => 'new',
            'reported_at' => !empty($validated['incident_date']) ? $validated['incident_date'] : now(),
        ]);

        return redirect()
            ->route('front.track', ['ref' => $signalement->tracking_token])
            ->with('success', 'Votre signalement est enregistré. Conservez votre référence de suivi : '.$signalement->tracking_token.'.');
    }

    public function showIncident(string $incident): View
    {
        $incidentId = ctype_digit($incident) ? (int) $incident : (int) preg_replace('/\D+/', '', $incident);
        $record = Incident::with(['zone', 'infrastructure', 'signalements'])
            ->when(auth()->user()?->role === 'citizen', fn ($query) => $query->whereHas('signalements', fn ($signalements) => $signalements->where('user_id', auth()->id())))
            ->findOrFail($incidentId);
        $incidentData = [
            'id' => 'INC-' . str_pad($record->id, 4, '0', STR_PAD_LEFT),
            'title' => $record->description,
            'site' => $record->location ?? ($record->zone->name ?? 'Site non défini'),
            'priority' => match ($record->severity) {
                'critical' => 'Critique',
                'high' => 'Élevée',
                'low' => 'Faible',
                default => 'Moyenne',
            },
            'status' => [
                'reported' => 'Ouvert',
                'in_progress' => 'En cours',
                'resolved' => 'Résolu',
                'closed' => 'Fermé',
            ][$record->status] ?? $record->status,
            'date' => $record->reported_at ? $record->reported_at->format('d M Y') : now()->format('d M Y'),
            'description' => $record->description,
            'photo_path' => $record->photo_path ?? $record->signalements->first()?->photo_path,
            'type' => $record->type,
        ];

        return view('front.incidents.show', $this->shared() + ['incident' => $incidentData, 'incidentCode' => $incidentData['id']]);
    }

    public function infrastructures(Request $request): View
    {
        $query = Infrastructure::with(['zone', 'maintenances'])
            ->search($request->input('search'))
            ->zone($request->input('zone_id'))
            ->type($request->input('type'))
            ->condition($request->input('condition'));

        $quick = $request->input('quick');
        if ($quick === 'operational') {
            $query->where('status', 'operational');
        } elseif ($quick === 'maintenance') {
            $query->where('status', 'maintenance');
        } elseif ($quick === 'critical') {
            $query->whereIn('status', ['critical', 'offline', 'out_of_service']);
        } elseif ($request->filled('status')) {
            $query->status($request->input('status'));
        }

        $infrastructures = $query->latest()->paginate(9)->withQueryString();

        $zones = Zone::orderBy('name')->get();
        $types = Infrastructure::distinct()->whereNotNull('type')->pluck('type')->sort()->values();
        $totalCount = Infrastructure::count();
        $operationalCount = Infrastructure::where('status', 'operational')->count();

        return view('front.infrastructures.index', $this->shared() + compact(
            'infrastructures',
            'zones',
            'types',
            'totalCount',
            'operationalCount'
        ));
    }

    public function showInfrastructure(string $infrastructure): View
    {
        $record = Infrastructure::with([
            'zone',
            'maintenances' => fn ($q) => $q->orderBy('scheduled_at', 'desc')->limit(10),
        ])
        ->where('id', $infrastructure)
        ->orWhere('reference_code', $infrastructure)
        ->orWhere('name', $infrastructure)
        ->first();

        if (!$record) {
            abort(404, 'Infrastructure introuvable.');
        }

        return view('front.infrastructures.show', $this->shared() + [
            'infrastructure' => $record,
            'infrastructureCode' => $record->reference_code ?? ('INF-' . $record->id),
        ]);
    }

    public function reportMalfunction(ReportMalfunctionRequest $request, string $infrastructure): RedirectResponse
    {
        $infra = Infrastructure::where('id', $infrastructure)
            ->orWhere('reference_code', $infrastructure)
            ->orWhere('name', $infrastructure)
            ->firstOrFail();

        $validated = $request->validated();

        $reporter = $validated['reporter_name'] ?: (auth()->user()?->name ?? 'Citoyen');
        $phone = $validated['reporter_phone'] ?: '';
        $type = $validated['malfunction_type'];

        $nextId = (Maintenance::max('id') ?? 0) + 1;
        $refCode = sprintf('MNT-%s-SIG-%03d', date('Y'), $nextId);

        $maintenance = Maintenance::create([
            'infrastructure_id' => $infra->id,
            'reference_code' => $refCode,
            'type' => 'Corrective',
            'status' => 'reported',
            'priority' => $validated['priority'],
            'scheduled_at' => now(),
            'team' => 'Signalement usager (' . $reporter . ')',
            'description' => "[Dysfonctionnement signalé : {$type}] " . $validated['description'] . " (Signalé par : {$reporter}" . ($phone ? " - Tél: {$phone}" : "") . ")",
        ]);

        // If high or critical, update infrastructure status
        if ($validated['priority'] === 'critical') {
            $infra->status = 'critical';
            $infra->condition = 'critical';
            $infra->save();
        } elseif ($validated['priority'] === 'high' && $infra->status === 'operational') {
            $infra->condition = 'poor';
            $infra->save();
        }

        return back()->with('success', "Votre signalement sur l'infrastructure « {$infra->name} » a été transmis avec succès aux équipes de maintenance (Réf. {$refCode}). Un ordre d'intervention a été initié.");
    }

    public function showProject(string $project): View
    {
        $record = Project::where('id', $project)->orWhere('name', $project)->first();
        $projData = $record ? [
            'name' => $record->name,
            'location' => 'Territoire national',
            'status' => $record->status === 'in_progress' ? 'En cours' : ($record->status === 'completed' ? 'Opérationnel' : 'À l’étude'),
            'progress' => $record->progress,
            'type' => $record->type,
        ] : ($this->projectsData()[0] ?? []);

        return view('front.projects.show', $this->shared() + ['project' => $projData, 'projectCode' => $project]);
    }

    private function projectsData(): array
    {
        return Project::all()->map(fn($p) => [
            'name' => $p->name,
            'location' => 'France',
            'status' => $p->status === 'in_progress' ? 'En déploiement' : ($p->status === 'completed' ? 'Opérationnel' : 'À l’étude'),
            'progress' => $p->progress,
            'type' => $p->type,
        ])->toArray();
    }

    private function incidentsData(): array
    {
        return Incident::with('zone')->when(auth()->user()?->role === 'citizen', fn ($query) => $query->whereHas('signalements', fn ($signalements) => $signalements->where('user_id', auth()->id())))->orderByDesc('id')->get()->map(fn($i) => [
            'id' => 'INC-' . str_pad($i->id, 4, '0', STR_PAD_LEFT),
            'title' => $i->description,
            'site' => $i->location ?? ($i->zone->name ?? 'Zone'),
            'priority' => 'Critique',
            'status' => $i->status === 'reported' ? 'Ouvert' : ($i->status === 'in_progress' ? 'En cours' : 'Résolu'),
            'date' => $i->reported_at ? $i->reported_at->format('d M Y') : now()->format('d M Y'),
        ])->toArray();
    }

    private function infrastructureData(): array
    {
        return Infrastructure::with('zone')->get()->map(fn($inf) => [
            'name' => $inf->name,
            'type' => $inf->type,
            'region' => $inf->zone->name ?? 'France',
            'health' => $inf->status === 'operational' ? '98%' : '76%',
            'status' => $inf->status === 'operational' ? 'Connectée' : 'Maintenance',
        ])->toArray();
    }
}
