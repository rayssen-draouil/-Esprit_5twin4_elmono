<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Equipement;
use App\Models\Intervention;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\EquipmentReservationService;
use App\Services\MaintenanceLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        if (! $user || ! $user->isTechnician()) {
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
        $interventionQuery = Intervention::with(['incident.infrastructure', 'incident.zone', 'equipements'])
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
        } elseif ($filter === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        if ($filter !== 'all') {
            $interventionQuery->where('status', $filter);
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

        $assignedInterventions = $interventionQuery
            ->with(['incident.zone', 'equipements'])
            ->latest('scheduled_at')
            ->paginate(10, ['*'], 'interventions_page')
            ->withQueryString();

        $equipements = Equipement::query()
            ->with(['interventions' => fn ($query) => $query
                ->with('technician')
                ->whereNotIn('interventions.status', ['completed', 'cancelled'])
                ->orderBy('interventions.scheduled_at')])
            ->orderBy('name')
            ->get();
        $availableEquipementIds = $equipements
            ->filter(fn (Equipement $equipement): bool => $equipement->status === 'available' && $equipement->interventions->isEmpty())
            ->pluck('id')
            ->all();

        // Counts for tabs
        $stats = [
            'all' => Maintenance::where('technician_id', $technician?->id ?? 0)->count() + Intervention::where('technician_id', $technician?->id ?? 0)->count(),
            'in_progress' => Maintenance::where('technician_id', $technician?->id ?? 0)->where('status', 'in_progress')->count() + Intervention::where('technician_id', $technician?->id ?? 0)->where('status', 'in_progress')->count(),
            'awaiting_validation' => Maintenance::where('technician_id', $technician?->id ?? 0)->where('status', 'awaiting_validation')->count(),
            'planned' => Maintenance::where('technician_id', $technician?->id ?? 0)->where('status', 'planned')->count() + Intervention::where('technician_id', $technician?->id ?? 0)->where('status', 'planned')->count(),
            'completed' => Maintenance::where('technician_id', $technician?->id ?? 0)->where('status', 'completed')->count() + Intervention::where('technician_id', $technician?->id ?? 0)->where('status', 'completed')->count(),
        ];

        return view('techniciens.interventions.index', compact(
            'maintenances', 'assignedInterventions', 'equipements', 'availableEquipementIds', 'technician', 'filter', 'stats'
        ));
    }

    public function respondToIntervention(Request $request, Intervention $intervention): RedirectResponse
    {
        $technician = $this->getTechnician();
        if (! $technician || (int) $intervention->technician_id !== (int) $technician->id) {
            abort(403, 'Vous ne pouvez répondre qu’à vos propres interventions.');
        }
        if ($intervention->technician_response !== 'pending' || $intervention->status !== 'planned') {
            return redirect()->route('technicien.interventions.index')->with('error', 'Cette intervention ne peut plus recevoir de réponse.');
        }

        $validated = $request->validate([
            'response' => ['required', Rule::in(['confirmed', 'refused'])],
            'equipements' => ['nullable', 'array'],
            'equipements.*' => ['integer', 'distinct', Rule::exists('equipements', 'id')],
        ]);
        if ($validated['response'] === 'confirmed' && empty($validated['equipements'])) {
            return back()->withErrors(['equipements' => 'Sélectionnez au moins un équipement avant de confirmer.'])->withInput();
        }

        DB::transaction(function () use ($intervention, $validated, $technician): void {
            $lockedIntervention = Intervention::query()->whereKey($intervention->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedIntervention->technician_id !== (int) $technician->id
                || $lockedIntervention->technician_response !== 'pending'
                || $lockedIntervention->status !== 'planned') {
                abort(403, 'Cette intervention ne peut plus recevoir de réponse.');
            }

            $selectedIds = $validated['equipements'] ?? [];
            $lockedIds = Equipement::query()->where('status', 'available')->whereIn('id', $selectedIds)
                ->orderBy('id')->lockForUpdate()->pluck('id')->all();
            if (count($lockedIds) !== count($selectedIds)) {
                throw ValidationException::withMessages(['equipements' => 'Un ou plusieurs équipements ne sont plus disponibles.']);
            }

            $reservedIds = $selectedIds === [] ? [] : DB::table('equipement_intervention')
                ->join('interventions', 'interventions.id', '=', 'equipement_intervention.intervention_id')
                ->whereIn('equipement_intervention.equipement_id', $selectedIds)
                ->where('interventions.id', '!=', $lockedIntervention->id)
                ->whereNotIn('interventions.status', ['completed', 'cancelled'])
                ->pluck('equipement_intervention.equipement_id')->unique()->values()->all();
            if ($reservedIds !== []) {
                throw ValidationException::withMessages(['equipements' => 'Un ou plusieurs équipements sont déjà réservés par une intervention active.']);
            }

            $confirmed = $validated['response'] === 'confirmed';
            $lockedIntervention->equipements()->sync($confirmed ? $selectedIds : []);
            $lockedIntervention->update([
                'technician_response' => $validated['response'],
                'technician_response_at' => now(),
                ...($confirmed ? ['status' => 'in_progress', 'started_at' => now()] : []),
            ]);
            if ($confirmed) {
                app(EquipmentReservationService::class)->syncStatus($selectedIds);
            }
        });

        return redirect()->route('technicien.interventions.index')->with('success', $validated['response'] === 'confirmed'
            ? 'Intervention confirmée et démarrée avec les équipements sélectionnés.'
            : 'Intervention refusée.');
    }

    /**
     * Display details of a specific assigned intervention and work report form.
     */
    public function show(Maintenance $maintenance): View
    {
        $technician = $this->getTechnician();

        // Enforce authorization: technician can only access their own assignments
        if (! $technician || (int) $maintenance->technician_id !== (int) $technician->id) {
            abort(403, 'Accès interdit : Cette intervention ne vous est pas assignée.');
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

        if (! $technician || (int) $maintenance->technician_id !== (int) $technician->id) {
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
            'result.required' => 'Le compte-rendu technique des travaux réalisés est obligatoire.',
            'result.min' => 'Le compte-rendu doit comporter au moins 10 caractères explicatifs.',
            'completed_at.before_or_equal' => "La date d'achèvement ne peut pas être dans le futur.",
        ]);

        try {
            $this->lifecycleService->submitCompletion($maintenance, $technician, $validated);

            return redirect()
                ->route('technicien.interventions.show', $maintenance)
                ->with('success', "Intervention confirmée et achevée avec succès ! Le statut est désormais « Terminée & Validée » (Étape 3/3). La santé de l'ouvrage a été actualisée et le gestionnaire a été notifié par email et notification.");
        } catch (\InvalidArgumentException|\DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Request a prolongation/postponement of the intervention.
     */
    public function prolong(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $technician = $this->getTechnician();

        if (! $technician || (int) $maintenance->technician_id !== (int) $technician->id) {
            abort(403, "Accès interdit : Vous n'êtes pas le technicien affecté à cette opération.");
        }

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:60'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'days.required' => 'Veuillez indiquer le nombre de jours supplémentaires.',
            'days.min' => "La prolongation doit être d'au moins 1 jour.",
            'reason.required' => 'Veuillez expliquer la raison de ce report pour le gestionnaire.',
            'reason.min' => 'Le motif doit comporter au moins 5 caractères.',
        ]);

        try {
            $this->lifecycleService->prolongIntervention($maintenance, $technician, (int) $validated['days'], $validated['reason']);

            return redirect()
                ->route('technicien.interventions.show', $maintenance)
                ->with('success', "Demande de prolongation enregistrée (+{$validated['days']} jours). La date d'intervention a été décalée et le gestionnaire a été prévenu.");
        } catch (\InvalidArgumentException|\DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
