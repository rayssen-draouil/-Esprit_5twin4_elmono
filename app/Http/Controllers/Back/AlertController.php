<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlertRequest;
use App\Models\Alert;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlertController extends Controller
{
    /** Nombre d'alertes par page dans la liste back-office. */
    private const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $alerts = $this->filteredQuery($request)
            ->with(['zone:id,name', 'incident:id,type'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $grouped = (bool) $request->boolean('group');
        $groups = $grouped
            ? $alerts->getCollection()->groupBy(fn (Alert $alert) => $alert->ai_group_key ?: 'alert-'.$alert->id)
            : collect();

        return view('back.alerts.index', [
            'alerts' => $alerts,
            'zones' => Zone::orderBy('name')->get(['id', 'name']),
            'kpis' => $this->kpis(),
            'severityCounts' => $this->severityCounts($request),
            'grouped' => $grouped,
            'groups' => $groups,
        ]);
    }

    public function create(): View
    {
        return view('back.alerts.create', $this->formData());
    }

    public function store(AlertRequest $request): RedirectResponse
    {
        Alert::create($this->payload($request));

        return redirect()->route('back.alerts.index')->with('success', 'Alerte créée avec succès.');
    }

    public function show(Alert $alert): View
    {
        $alert->load(['zone', 'incident']);

        $related = collect();
        if ($alert->zone_id) {
            $related = Alert::where('zone_id', $alert->zone_id)
                ->whereKeyNot($alert->getKey())
                ->latest()
                ->limit(5)
                ->get(['id', 'type', 'severity', 'message', 'created_at', 'ai_severity']);
        }

        return view('back.alerts.show', [
            'alert' => $alert,
            'related' => $related,
            'timeline' => $this->timeline($alert),
        ]);
    }

    public function edit(Alert $alert): View
    {
        return view('back.alerts.edit', $this->formData() + ['alert' => $alert]);
    }

    public function update(AlertRequest $request, Alert $alert): RedirectResponse
    {
        $alert->update($this->payload($request));

        return redirect()->route('back.alerts.show', $alert)->with('success', 'Alerte modifiée avec succès.');
    }

    public function destroy(Alert $alert): RedirectResponse
    {
        $alert->delete();

        return redirect()->route('back.alerts.index')->with('success', 'Alerte supprimée.');
    }

    /**
     * Acquitter une alerte (marquée lue + acknowledged_at).
     */
    public function acknowledge(Alert $alert): RedirectResponse
    {
        $alert->forceFill([
            'read_at' => $alert->read_at ?? now(),
            'acknowledged_at' => $alert->acknowledged_at ?? now(),
        ])->save();

        return back()->with('success', 'Alerte acquittée.');
    }

    /**
     * Résoudre une alerte (read_at + acknowledged_at + resolved_at).
     */
    public function resolve(Alert $alert): RedirectResponse
    {
        $alert->forceFill([
            'read_at' => $alert->read_at ?? now(),
            'acknowledged_at' => $alert->acknowledged_at ?? now(),
            'resolved_at' => now(),
        ])->save();

        return back()->with('success', 'Alerte résolue.');
    }

    /**
     * Réouvrir une alerte (réinitialise le suivi du workflow).
     */
    public function reopen(Alert $alert): RedirectResponse
    {
        $alert->forceFill([
            'read_at' => null,
            'acknowledged_at' => null,
            'resolved_at' => null,
        ])->save();

        return back()->with('success', 'Alerte réouverte.');
    }

    /**
     * Actions groupées sur une sélection d'alertes (lecture / acquittement / résolution).
     */
    public function bulk(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:read,unread,acknowledged,resolved'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $timestamp = now();

        $update = match ($data['action']) {
            'read' => ['read_at' => $timestamp],
            'unread' => ['read_at' => null],
            'acknowledged' => ['read_at' => $timestamp, 'acknowledged_at' => $timestamp],
            'resolved' => ['read_at' => $timestamp, 'acknowledged_at' => $timestamp, 'resolved_at' => $timestamp],
        };

        $count = Alert::whereIn('id', $ids)->update($update);

        $label = ['read' => 'marquée(s) comme lue(s)', 'unread' => 'marquée(s) comme non lue(s)', 'acknowledged' => 'acquittée(s)', 'resolved' => 'résolue(s)'][$data['action']];
        $message = $count.' alerte(s) '.$label.'.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'updated' => $count, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * Endpoint JSON léger pour le rafraîchissement live des KPI (polling).
     */
    public function live(Request $request): JsonResponse
    {
        $since = $request->date('since');

        $latest = Alert::max('created_at');
        $latestCritical = Alert::where('severity', 'critical')->max('created_at');

        $newCount = $since
            ? Alert::where('created_at', '>', $since)->count()
            : 0;

        $newCriticalCount = $since
            ? Alert::where('severity', 'critical')->where('created_at', '>', $since)->count()
            : 0;

        return response()->json([
            'success' => true,
            'kpi' => $this->kpis(),
            'latest_at' => $latest ? \Illuminate\Support\Carbon::parse($latest)->toIso8601String() : null,
            'latest_critical_at' => $latestCritical ? \Illuminate\Support\Carbon::parse($latestCritical)->toIso8601String() : null,
            'new_count' => $newCount,
            'new_critical_count' => $newCriticalCount,
        ]);
    }

    /**
     * Export CSV de la liste filtrée des alertes.
     */
    public function export(Request $request): StreamedResponse
    {
        $alerts = $this->filteredQuery($request)
            ->with(['zone:id,name'])
            ->get();

        $filename = 'alertes-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($alerts): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Type', 'Message', 'Zone', 'Gravité', 'Statut', 'Lu', 'Créée le']);

            foreach ($alerts as $alert) {
                fputcsv($out, [
                    $this->csvSafe($alert->type),
                    $this->csvSafe($alert->message),
                    $this->csvSafe($alert->zone?->name ?? ''),
                    $this->csvSafe($alert->severity_label),
                    $this->csvSafe($alert->status_label),
                    $alert->read_at ? 'Oui' : 'Non',
                    $alert->created_at?->format('d/m/Y H:i') ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{zones: \Illuminate\Support\Collection} */
    private function formData(): array
    {
        return ['zones' => Zone::orderBy('name')->get(['id', 'name'])];
    }

    /** @return array<string, mixed> */
    private function payload(AlertRequest $request): array
    {
        $data = $request->safe()->only(['zone_id', 'incident_id', 'type', 'severity', 'message']);

        $wasRead = $request->boolean('read');
        $current = $request->route('alert');
        $data['read_at'] = $wasRead ? ($current?->read_at ?? now()) : null;

        return $data;
    }

    /**
     * Requête de liste partagée par l'index et l'export CSV.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Alert>
     */
    private function filteredQuery(Request $request)
    {
        return Alert::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$this->like($request->string('q')).'%';
                $q->where(fn ($w) => $w->where('type', 'like', $term)->orWhere('message', 'like', $term));
            })
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->when($request->filled('zone'), fn ($q) => $q->where('zone_id', $request->integer('zone')))
            ->when($request->filled('status'), fn ($q) => $this->applyStatus($q, $request->string('status')->toString()))
            ->when($request->filled('period'), fn ($q) => $this->applyPeriod($q, $request))
            ->orderBy(...$this->sort($request));
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Alert> $query */
    private function applyStatus($query, string $status): void
    {
        match ($status) {
            'open' => $query->whereNull('resolved_at')->whereNull('acknowledged_at'),
            'acknowledged' => $query->whereNotNull('acknowledged_at')->whereNull('resolved_at'),
            'resolved' => $query->whereNotNull('resolved_at'),
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => null,
        };
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Alert> $query */
    private function applyPeriod($query, Request $request): void
    {
        match ($request->string('period')->toString()) {
            'today' => $query->where('created_at', '>=', now()->startOfDay()),
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            'custom' => $query
                ->when($request->date('from'), fn ($q, $from) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($from)->startOfDay()))
                ->when($request->date('to'), fn ($q, $to) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($to)->endOfDay())),
            default => null,
        };
    }

    /**
     * Tri validé (liste blanche). Le tri « priority » combine gravité + ancienneté.
     *
     * @return array<int, mixed>
     */
    private function sort(Request $request): array
    {
        $column = $request->string('sort')->toString();
        $direction = $request->string('dir')->lower()->toString() === 'asc' ? 'asc' : 'desc';

        return match ($column) {
            'oldest' => ['created_at', 'asc'],
            'severity' => [
                \Illuminate\Support\Facades\DB::raw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END"), $direction,
            ],
            'age' => ['created_at', 'asc'],
            'priority' => [
                \Illuminate\Support\Facades\DB::raw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END"), 'desc',
            ],
            default => ['created_at', 'desc'],
        };
    }

    /**
     * @return array{open: int, critical: int, resolved_today: int, average_age: int}
     */
    private function kpis(): array
    {
        $openQuery = Alert::whereNull('resolved_at');

        $averageHours = (clone $openQuery)->whereNotNull('created_at')->get(['created_at'])
            ->avg(fn (Alert $alert) => $alert->ageInHours());

        return [
            'open' => (clone $openQuery)->count(),
            'critical' => (clone $openQuery)->where('severity', 'critical')->count(),
            'resolved_today' => Alert::whereDate('resolved_at', now()->toDateString())->count(),
            'average_age' => (int) round((float) $averageHours / 24),
        ];
    }

    /**
     * Nombre d'alertes par gravité pour les chips (en respectant les autres filtres).
     *
     * @return array<string, int>
     */
    private function severityCounts(Request $request): array
    {
        return Alert::query()
            ->when($request->filled('zone'), fn ($q) => $q->where('zone_id', $request->integer('zone')))
            ->when($request->filled('status'), fn ($q) => $this->applyStatus($q, $request->string('status')->toString()))
            ->when($request->filled('period'), fn ($q) => $this->applyPeriod($q, $request))
            ->selectRaw('severity, COUNT(*) as aggregate')
            ->groupBy('severity')
            ->pluck('aggregate', 'severity')
            ->all();
    }

    /**
     * Étapes du cycle de vie de l'alerte pour la timeline.
     *
     * @return Collection<int, array{label: string, at: \Illuminate\Support\Carbon|null, done: bool}>
     */
    private function timeline(Alert $alert): Collection
    {
        return collect([
            ['label' => 'Alerte créée', 'at' => $alert->created_at, 'done' => true],
            ['label' => 'Classification IA', 'at' => $alert->ai_analyzed_at, 'done' => $alert->ai_analyzed_at !== null],
            ['label' => 'Prise en compte (acquittée)', 'at' => $alert->acknowledged_at, 'done' => $alert->acknowledged_at !== null],
            ['label' => 'Résolue', 'at' => $alert->resolved_at, 'done' => $alert->resolved_at !== null],
        ]);
    }

    private function like($value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], (string) $value);
    }

    /** Neutralise une éventuelle injection de formule dans un CSV. */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
