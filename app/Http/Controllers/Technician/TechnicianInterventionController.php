<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\MaintenanceLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TechnicianInterventionController extends Controller
{
    protected MaintenanceLifecycleService $lifecycleService;

    public function __construct(MaintenanceLifecycleService $lifecycleService)
    {
        $this->lifecycleService = $lifecycleService;
    }

    /**
     * Get the authenticated technician.
     *
     * Le technicien est un rôle applicatif : il correspond directement à
     * l'utilisateur connecté.
     */
    protected function getTechnician(): ?User
    {
        $user = Auth::user();

        if (!$user || !$user->isTechnician()) {
            return null;
        }

        return $user;
    }

    /**
     * Display the technician's work list (Mes interventions).
     */
    public function index(Request $request): View
    {
        $technician = $this->getTechnician();

        $query = Maintenance::with(['infrastructure.zone'])
            ->where('technician_id', $technician?->id ?? 0);

        $filter = $request->input('status', 'all');

        if ($filter === 'planned') {
            $query->where('status', 'planned');
        } elseif ($filter === 'in_progress') {
            $query->where('status', 'in_progress');
        } elseif ($filter === 'awaiting_validation') {
            $query->where('status', 'awaiting_validation');
        } elseif ($filter === 'completed') {
            $query->where('status', 'completed');
        }

        $maintenances = $query->orderByRaw("CASE
                WHEN status = 'in_progress' THEN 1
                WHEN status = 'awaiting_validation' THEN 2
                WHEN status = 'planned' THEN 3
                WHEN status = 'completed' THEN 4
                ELSE 5 END")
            ->latest('scheduled_at')
            ->paginate(10)
            ->withQueryString();

        // Counts for tabs
        $stats = [
            'all' => Maintenance::where('technician_id', $technician?->id ?? 0)->count(),
            'in_progress' => Maintenance::where('technician_id', $technician?->id ?? 0)->where('status', 'in_progress')->count(),
            'awaiting_validation' => Maintenance::where('technician_id', $technician?->id ?? 0)->where('status', 'awaiting_validation')->count(),
            'planned' => Maintenance::where('technician_id', $technician?->id ?? 0)->where('status', 'planned')->count(),
            'completed' => Maintenance::where('technician_id', $technician?->id ?? 0)->where('status', 'completed')->count(),
        ];

        return view('techniciens.interventions.index', compact('maintenances', 'technician', 'filter', 'stats'));
    }

    /**
     * Display details of a specific assigned intervention and work report form.
     */
    public function show(Maintenance $maintenance): View
    {
        $technician = $this->getTechnician();

        // Enforce authorization: technician can only access their own assignments
        if (!$technician || (int) $maintenance->technician_id !== (int) $technician->id) {
            abort(403, "Accès interdit : Cette intervention ne vous est pas assignée.");
        }

        $maintenance->load(['infrastructure.zone', 'validator']);

        return view('techniciens.interventions.show', compact('maintenance', 'technician'));
    }

    /**
     * Submit completion report for manager review (Signaler l'intervention terminée).
     */
    public function submitCompletion(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $technician = $this->getTechnician();

        if (!$technician || (int) $maintenance->technician_id !== (int) $technician->id) {
            abort(403, "Accès interdit : Vous n'êtes pas le technicien affecté à cette opération.");
        }

        $validated = $request->validate([
            'result' => ['required', 'string', 'min:10', 'max:5000'],
            'findings' => ['nullable', 'string', 'max:3000'],
            'parts_replaced' => ['nullable', 'string', 'max:2000'],
            'tests_verification' => ['nullable', 'string', 'max:2000'],
            'remaining_issues' => ['nullable', 'string', 'max:2000'],
            'recommendations' => ['nullable', 'string', 'max:2000'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ], [
            'result.required' => "Le compte-rendu technique des travaux réalisés est obligatoire.",
            'result.min' => "Le compte-rendu doit comporter au moins 10 caractères explicatifs.",
            'completed_at.before_or_equal' => "La date d'achèvement ne peut pas être dans le futur.",
        ]);

        try {
            $this->lifecycleService->submitCompletion($maintenance, $technician, $validated);

            return redirect()
                ->route('technicien.interventions.show', $maintenance)
                ->with('success', "Votre rapport a été soumis avec succès ! L'opération est maintenant « En attente de validation » par le gestionnaire.");
        } catch (\InvalidArgumentException|\DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
