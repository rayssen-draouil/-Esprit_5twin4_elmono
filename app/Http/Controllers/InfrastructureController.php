<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInfrastructureRequest;
use App\Http\Requests\UpdateInfrastructureRequest;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InfrastructureController extends Controller
{
    public function index(Request $request): View
    {
        // 1. Calculate Real KPIs from database
        $statistics = [
            'total' => Infrastructure::count(),
            'operational' => Infrastructure::where('status', 'operational')->count(),
            'maintenance' => Infrastructure::where('status', 'maintenance')->count(),
            'critical' => Infrastructure::whereIn('status', ['critical', 'offline', 'out_of_service'])->count(),
            'due_soon' => Infrastructure::maintenanceDue()->count(),
        ];

        // 2. Query with Scopes & Advanced Filters
        $query = Infrastructure::with(['zone', 'maintenances'])
            ->search($request->input('search'))
            ->zone($request->input('zone_id'))
            ->type($request->input('type'))
            ->condition($request->input('condition'))
            ->criticality($request->input('criticality'));

        // Quick filter tabs
        $quick = $request->input('quick');
        if ($quick === 'operational') {
            $query->where('status', 'operational');
        } elseif ($quick === 'maintenance') {
            $query->where('status', 'maintenance');
        } elseif ($quick === 'critical') {
            $query->whereIn('status', ['critical', 'offline', 'out_of_service']);
        } elseif ($quick === 'due_soon') {
            $query->maintenanceDue();
        } elseif ($request->filled('status')) {
            $query->status($request->input('status'));
        }

        // Sorting
        $sort = $request->input('sort', 'created_at');
        $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['name', 'reference_code', 'status', 'condition', 'next_maintenance_date', 'created_at'], true)) {
            $query->orderBy($sort, $order);
        } else {
            $query->latest();
        }

        $perPage = $request->input('view') === 'grid' ? 9 : 10;
        $infrastructures = $query->paginate($perPage)->withQueryString();

        // 3. Active filter chips helper
        $activeFilters = $this->buildActiveFilters($request);

        // 4. Form / Filter Options
        $zones = Zone::orderBy('name')->get(['id', 'name']);
        $types = Infrastructure::distinct()->whereNotNull('type')->pluck('type')->sort()->values();
        if ($types->isEmpty()) {
            $types = collect(['Station de pompage', 'Barrage', 'Usine de filtration', 'Réservoir de stockage', 'Capteurs qualité']);
        }

        $viewMode = $request->input('view', 'table');

        return view('infrastructures.index', compact(
            'infrastructures',
            'statistics',
            'zones',
            'types',
            'viewMode',
            'activeFilters'
        ));
    }

    public function create(): View
    {
        return view('infrastructures.create', $this->formOptions());
    }

    public function store(StoreInfrastructureRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Auto-generate reference code if not provided
        if (empty($validated['reference_code'])) {
            $nextId = (Infrastructure::max('id') ?? 0) + 1;
            $validated['reference_code'] = sprintf('INF-%s-%03d', date('Y'), $nextId);
        }

        // Image upload handling
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('infrastructures', 'public');
            $validated['image_path'] = $path;
        }

        $infrastructure = Infrastructure::create($validated);

        return redirect()
            ->route('infrastructures.show', $infrastructure)
            ->with('success', "L'infrastructure « {$infrastructure->name} » a été créée avec succès.");
    }

    public function show(Infrastructure $infrastructure): View
    {
        $infrastructure->load([
            'zone',
            'maintenances.technician',
        ]);

        $metrics = [
            'total_maintenances' => $infrastructure->maintenances->count(),
            'completed_maintenances' => $infrastructure->maintenances->where('status', 'completed')->count(),
            'upcoming_maintenances' => $infrastructure->maintenances->whereIn('status', ['planned', 'in_progress'])->count(),
            'overdue_maintenances' => $infrastructure->maintenances->filter(fn ($m) => $m->is_overdue)->count(),
            'total_cost' => (float) $infrastructure->maintenances->sum('cost'),
            'last_maintenance' => $infrastructure->last_maintenance_date,
            'next_maintenance' => $infrastructure->next_maintenance_date,
        ];

        return view('infrastructures.show', compact('infrastructure', 'metrics'));
    }

    public function edit(Infrastructure $infrastructure): View
    {
        return view('infrastructures.edit', array_merge(
            ['infrastructure' => $infrastructure],
            $this->formOptions()
        ));
    }

    public function update(UpdateInfrastructureRequest $request, Infrastructure $infrastructure): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            if ($infrastructure->image_path && Storage::disk('public')->exists($infrastructure->image_path)) {
                Storage::disk('public')->delete($infrastructure->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('infrastructures', 'public');
        }

        $infrastructure->update($validated);

        return redirect()
            ->route('infrastructures.show', $infrastructure)
            ->with('success', "L'infrastructure « {$infrastructure->name} » a été mise à jour.");
    }

    public function destroy(Infrastructure $infrastructure): RedirectResponse
    {
        $name = $infrastructure->name;

        if ($infrastructure->image_path && Storage::disk('public')->exists($infrastructure->image_path)) {
            Storage::disk('public')->delete($infrastructure->image_path);
        }

        $infrastructure->delete();

        return redirect()
            ->route('infrastructures.index')
            ->with('success', "L'infrastructure « {$name} » et son historique ont été supprimés.");
    }

    private function formOptions(): array
    {
        return [
            'zones' => Zone::orderBy('name')->get(['id', 'name']),
            'statuses' => [
                'operational' => 'Opérationnel',
                'maintenance' => 'En maintenance',
                'critical' => 'Critique',
                'offline' => 'Hors ligne',
                'out_of_service' => 'Hors service',
            ],
            'conditions' => [
                'excellent' => 'Excellent état',
                'good' => 'Bon état',
                'fair' => 'État moyen (surveillance)',
                'poor' => 'État dégradé',
                'critical' => 'État critique',
            ],
            'criticalities' => [
                'low' => 'Faible',
                'medium' => 'Moyenne',
                'high' => 'Élevée',
                'vital' => 'Vitale (priorité absolue)',
            ],
            'types' => [
                'Station de pompage',
                'Barrage',
                'Usine de filtration',
                'Réservoir de stockage',
                'Capteurs qualité',
                'Canalisation principale',
                'Station d’épuration',
            ],
        ];
    }

    private function buildActiveFilters(Request $request): array
    {
        $filters = [];

        if ($request->filled('search')) {
            $filters['search'] = ['label' => 'Recherche : '.$request->input('search'), 'param' => 'search'];
        }
        if ($request->filled('quick')) {
            $labels = [
                'operational' => 'Statut : Opérationnel',
                'maintenance' => 'Statut : En maintenance',
                'critical' => 'Statut : Critique',
                'due_soon' => 'Maintenance requise bientôt',
            ];
            if (isset($labels[$request->input('quick')])) {
                $filters['quick'] = ['label' => $labels[$request->input('quick')], 'param' => 'quick'];
            }
        }
        if ($request->filled('status') && ! $request->filled('quick')) {
            $filters['status'] = ['label' => 'Statut : '.ucfirst($request->input('status')), 'param' => 'status'];
        }
        if ($request->filled('type')) {
            $filters['type'] = ['label' => 'Type : '.$request->input('type'), 'param' => 'type'];
        }
        if ($request->filled('zone_id')) {
            $zone = Zone::find($request->input('zone_id'));
            if ($zone) {
                $filters['zone_id'] = ['label' => 'Zone : '.$zone->name, 'param' => 'zone_id'];
            }
        }
        if ($request->filled('condition')) {
            $filters['condition'] = ['label' => 'Condition : '.ucfirst($request->input('condition')), 'param' => 'condition'];
        }
        if ($request->filled('criticality')) {
            $filters['criticality'] = ['label' => 'Criticité : '.ucfirst($request->input('criticality')), 'param' => 'criticality'];
        }

        return $filters;
    }
}
