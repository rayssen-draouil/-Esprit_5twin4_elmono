<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\ZoneRequest;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZoneController extends Controller
{
    /** Nombre de zones par page dans la liste back-office. */
    private const PER_PAGE = 12;

    public function index(Request $request): View
    {
        $zones = $this->filteredQuery($request)
            ->withCount(['infrastructures', 'incidents'])
            ->withMax('alerts', 'created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('back.zones.index', [
            'zones' => $zones,
            'kpis' => $this->kpis(),
        ]);
    }

    public function create(): View
    {
        return view('back.zones.create');
    }

    public function store(ZoneRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['image'], $data['remove_image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('zones', 'public');
        }

        Zone::create($data);

        return redirect()->route('back.zones.index')->with('success', 'Zone ajoutée avec succès.');
    }

    public function show(Zone $zone): View
    {
        $zone->load([
            'infrastructures',
            'incidents' => fn ($q) => $q->latest('reported_at'),
            'alerts' => fn ($q) => $q->latest(),
        ]);

        $alertCounts = collect(Zone::AI_RISK_LEVELS)
            ->mapWithKeys(fn ($label, $level) => [$level => $zone->alerts->where('severity', $level)->count()])
            ->all();

        return view('back.zones.show', [
            'zone' => $zone,
            'alertCounts' => $alertCounts,
            'chart' => $this->alertsTimeline($zone),
        ]);
    }

    /**
     * Données JSON de la carte des zones (back-office).
     *
     * Seules les zones géolocalisées sont renvoyées. Aucune donnée
     * personnelle n'est exposée : uniquement des données de zone.
     */
    public function mapData(): JsonResponse
    {
        $zones = Zone::orderBy('name')
            ->get(['id', 'name', 'address', 'risk_level', 'latitude', 'longitude', 'radius_m', 'ai_risk_score', 'ai_risk_level']);

        $located = $zones->filter(fn (Zone $zone) => $zone->hasCoordinates())->values();

        return response()->json([
            'success' => true,
            'total' => $zones->count(),
            'located' => $located->count(),
            'zones' => $located->map(fn (Zone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'address' => $zone->address,
                'url' => route('back.zones.show', $zone),
                'risk_level' => $zone->risk_level,
                'risk_label' => $zone->risk_label,
                'ai_risk_score' => $zone->ai_risk_score,
                'ai_risk_level' => $zone->ai_risk_level,
                'ai_risk_level_label' => $zone->ai_risk_level ? $zone->ai_risk_level_label : null,
                'latitude' => $zone->latitude,
                'longitude' => $zone->longitude,
                'radius_m' => $zone->radius_m,
            ]),
        ]);
    }

    /**
     * Export CSV de la liste filtrée des zones (mêmes filtres que l'index).
     */
    public function export(Request $request): StreamedResponse
    {
        $zones = $this->filteredQuery($request)
            ->withCount(['infrastructures', 'incidents'])
            ->withMax('alerts', 'created_at')
            ->get();

        $filename = 'zones-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($zones): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nom', 'Adresse', 'Niveau de risque', 'Score IA', 'Niveau IA', 'Infrastructures', 'Incidents', 'Dernière analyse IA']);

            foreach ($zones as $zone) {
                fputcsv($out, [
                    $this->csvSafe($zone->name),
                    $this->csvSafe($zone->address),
                    $this->csvSafe($zone->risk_label),
                    $zone->ai_risk_score,
                    $this->csvSafe($zone->ai_risk_level ? $zone->ai_risk_level_label : ''),
                    $zone->infrastructures_count,
                    $zone->incidents_count,
                    $zone->ai_analyzed_at?->format('d/m/Y H:i') ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function edit(Zone $zone): View
    {
        return view('back.zones.edit', compact('zone'));
    }

    public function update(ZoneRequest $request, Zone $zone): RedirectResponse
    {
        $data = $request->validated();
        $removeImage = (bool) ($data['remove_image'] ?? false);
        unset($data['image'], $data['remove_image']);

        if ($request->hasFile('image')) {
            $this->deleteImage($zone);
            $data['image_path'] = $request->file('image')->store('zones', 'public');
        } elseif ($removeImage) {
            $this->deleteImage($zone);
            $data['image_path'] = null;
        }

        $zone->update($data);

        return redirect()->route('back.zones.show', $zone)->with('success', 'Zone modifiée avec succès.');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        // Les FK infrastructures/incidents sont en restrictOnDelete : on évite l'erreur SQL.
        if ($zone->infrastructures()->exists() || $zone->incidents()->exists()) {
            return redirect()->route('back.zones.index')
                ->with('error', 'Impossible de supprimer cette zone : elle contient des infrastructures ou des incidents.');
        }

        $this->deleteImage($zone);

        $zone->delete();

        return redirect()->route('back.zones.index')->with('success', 'Zone supprimée.');
    }

    /** Supprime le fichier photo d'une zone du disque public (si présent). */
    private function deleteImage(Zone $zone): void
    {
        if ($zone->image_path && Storage::disk('public')->exists($zone->image_path)) {
            Storage::disk('public')->delete($zone->image_path);
        }
    }

    /**
     * Requête de liste partagée par l'index et l'export CSV.
     *
     * @return Builder<Zone>
     */
    private function filteredQuery(Request $request)
    {
        return Zone::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $request->string('q')).'%'))
            ->when($request->filled('risk'), fn ($q) => $q->where('risk_level', $request->string('risk')))
            ->when($request->input('coords') === 'with', fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude'))
            ->when($request->input('coords') === 'without', fn ($q) => $q->where(fn ($w) => $w->whereNull('latitude')->orWhereNull('longitude')))
            ->orderBy(...$this->sort($request));
    }

    /**
     * Colonne et direction de tri validées (liste blanche, aucune injection).
     *
     * @return array{0: string, 1: string}
     */
    private function sort(Request $request): array
    {
        $columns = [
            'name' => 'name',
            'risk_score' => 'ai_risk_score',
            'last_alert' => 'last_alert_at',
            'last_analysis' => 'ai_analyzed_at',
        ];

        $column = $columns[$request->string('sort')->toString()] ?? 'name';
        $direction = $request->string('dir')->lower()->toString() === 'desc' ? 'desc' : 'asc';

        return [$column, $direction];
    }

    /**
     * Indicateurs clés affichés en haut de la liste.
     *
     * @return array{total: int, critical: int, average_score: int|null, stale: int}
     */
    private function kpis(): array
    {
        return [
            'total' => Zone::count(),
            'critical' => Zone::where(fn ($q) => $q->where('risk_level', 'high')->orWhere('ai_risk_level', 'critical'))->count(),
            'average_score' => (int) round((float) Zone::whereNotNull('ai_risk_score')->avg('ai_risk_score')),
            'stale' => Zone::where(fn ($q) => $q->whereNull('ai_analyzed_at')->orWhere('ai_analyzed_at', '<', now()->subDays(7)))->count(),
        ];
    }

    /**
     * Série journalière des alertes d'une zone sur 30 jours (pour Chart.js).
     *
     * @return array{labels: array<int, string>, values: array<int, int>, total: int}
     */
    private function alertsTimeline(Zone $zone): array
    {
        /** @var Collection<string, int> $counts */
        $counts = $zone->alerts()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->get(['created_at'])
            ->groupBy(fn ($alert) => $alert->created_at->format('Y-m-d'))
            ->map->count();

        $labels = [];
        $values = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $labels[] = $day->format('d/m');
            $values[] = (int) ($counts[$day->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values, 'total' => array_sum($values)];
    }

    /** Neutralise une éventuelle injection de formule dans un CSV. */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
