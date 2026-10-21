<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInterventionRequest;
use App\Http\Requests\UpdateInterventionRequest;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use App\Services\BrevoEmailService;
use App\Services\EquipmentReservationService;
use App\Services\InterventionSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InterventionController extends Controller
{
    public function index(Request $request): View
    {
        $interventions = Intervention::with(['incident', 'technician'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('technician_id'), fn ($query) => $query->where('technician_id', $request->integer('technician_id')))
            ->latest('scheduled_at')
            ->paginate(10)
            ->withQueryString();

        $statusCounts = Intervention::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('interventions.index', [
            'interventions' => $interventions,
            'technicians' => User::technicians()->where('technician_approval_status', 'approved')->orderBy('name')->get(['id', 'name']),
            'statuses' => ['planned', 'in_progress', 'completed', 'cancelled'],
            'interventionStats' => [
                'total' => $statusCounts->sum(),
                'planned' => $statusCounts->get('planned', 0),
                'in_progress' => $statusCounts->get('in_progress', 0),
                'completed' => $statusCounts->get('completed', 0),
            ],
        ]);
    }

    public function create(): View
    {
        return view('interventions.create', array_merge(['intervention' => null], $this->formOptions()));
    }

    public function store(StoreInterventionRequest $request, InterventionSchedulingService $scheduling): RedirectResponse
    {
        $data = $request->validated();
        $intervention = DB::transaction(function () use ($data, $scheduling): Intervention {
            $scheduling->assertAvailable((int) $data['technician_id'], $data['scheduled_at'], (int) $data['scheduled_duration_minutes']);

            return Intervention::create($data);
        });

        $emailSent = $this->notifyTechnicianOfAssignment($intervention);

        return redirect()->route('interventions.show', $intervention)
            ->with($emailSent ? 'success' : 'warning', $emailSent
                ? 'Intervention créée avec succès. Le technicien a été notifié par email.'
                : 'Intervention créée, mais le courriel de notification au technicien n’a pas pu être envoyé.');
    }

    public function show(Intervention $intervention): View
    {
        return view('interventions.show', ['intervention' => $intervention->load(['incident', 'technician', 'equipements'])]);
    }

    public function edit(Intervention $intervention): View
    {
        return view('interventions.edit', array_merge(['intervention' => $intervention], $this->formOptions()));
    }

    public function update(UpdateInterventionRequest $request, Intervention $intervention, InterventionSchedulingService $scheduling, EquipmentReservationService $reservations): RedirectResponse
    {
        $validated = $request->validated();
        if (($validated['status'] ?? null) === 'completed' && empty($validated['completed_at'])) {
            $validated['completed_at'] = now();
        }

        $technicianChanged = (int) $intervention->technician_id !== (int) ($validated['technician_id'] ?? 0);
        $technicianRemoved = $intervention->technician_id !== null && empty($validated['technician_id']);

        DB::transaction(function () use ($intervention, $validated, $technicianChanged, $technicianRemoved, $scheduling, $reservations): void {
            if (! empty($validated['technician_id'])) {
                $scheduling->assertAvailable((int) $validated['technician_id'], $validated['scheduled_at'], (int) $validated['scheduled_duration_minutes'], $intervention->id);
            }

            $equipmentIds = $intervention->equipements()->pluck('equipements.id');
            if ($technicianRemoved) {
                $validated['status'] = 'planned';
                $validated['started_at'] = null;
                $validated['completed_at'] = null;
            }
            $intervention->update($validated);
            if ($technicianChanged) {
                $intervention->equipements()->detach();
                $intervention->update(['technician_response' => 'pending', 'technician_response_at' => null]);
            }
            $reservations->syncStatus($equipmentIds);
        });

        if ($technicianChanged && ! $technicianRemoved) {
            $emailSent = $this->notifyTechnicianOfAssignment($intervention->fresh());

            return redirect()->route('interventions.show', $intervention)
                ->with($emailSent ? 'success' : 'warning', $emailSent
                    ? 'Intervention modifiée. Le nouveau technicien a été notifié par email.'
                    : 'Intervention modifiée, mais le courriel de notification au technicien n’a pas pu être envoyé.');
        }

        return redirect()->route('interventions.show', $intervention)->with('success', 'Intervention modifiée avec succès.');
    }

    public function destroy(Intervention $intervention, EquipmentReservationService $reservations): RedirectResponse
    {
        DB::transaction(function () use ($intervention, $reservations): void {
            $equipmentIds = $intervention->equipements()->pluck('equipements.id');
            $intervention->delete();
            $reservations->syncStatus($equipmentIds);
        });

        return redirect()->route('interventions.index')->with('success', 'Intervention supprimée avec succès.');
    }

    private function formOptions(): array
    {
        return [
            'incidents' => Incident::with('zone')->latest('reported_at')->get(),
            'technicians' => User::technicians()->where('technician_approval_status', 'approved')->orderBy('name')->get(),
        ];
    }

    private function notifyTechnicianOfAssignment(Intervention $intervention): bool
    {
        $intervention->loadMissing(['incident', 'technician']);
        $technician = $intervention->technician;
        if (! $technician) {
            Log::warning('Intervention assignment email not sent: no technician assigned.', ['intervention_id' => $intervention->id]);

            return false;
        }

        try {
            $result = app(BrevoEmailService::class)->sendInterventionAssignmentNotification($intervention, $technician);
        } catch (\Throwable $exception) {
            Log::warning('Intervention assignment email dispatch failed.', [
                'intervention_id' => $intervention->id,
                'technician_id' => $technician->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $result['success']) {
            Log::warning('Intervention assignment email was not sent.', [
                'intervention_id' => $intervention->id,
                'technician_id' => $technician->id,
                'error' => $result['error'],
            ]);
        }

        return $result['success'];
    }
}
