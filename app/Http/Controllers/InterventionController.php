<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        return view('interventions.index', [
            'interventions' => $interventions,
            'technicians' => User::technicians()->orderBy('name')->get(['id', 'name']),
            'statuses' => ['planned', 'in_progress', 'completed', 'cancelled'],
        ]);
    }

    public function create(): View
    {
        return view('interventions.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $intervention = Intervention::create($request->validate($this->rules()));

        return redirect()->route('interventions.show', $intervention)->with('success', 'Intervention créée avec succès.');
    }

    public function show(Intervention $intervention): View
    {
        return view('interventions.show', ['intervention' => $intervention->load(['incident', 'technician'])]);
    }

    public function edit(Intervention $intervention): View
    {
        return view('interventions.edit', array_merge(
            ['intervention' => $intervention],
            $this->formOptions(),
        ));
    }

    public function update(Request $request, Intervention $intervention): RedirectResponse
    {
        $intervention->update($request->validate($this->rules()));

        return redirect()->route('interventions.show', $intervention)->with('success', 'Intervention modifiée avec succès.');
    }

    public function destroy(Intervention $intervention): RedirectResponse
    {
        $intervention->delete();

        return redirect()->route('interventions.index')->with('success', 'Intervention supprimée avec succès.');
    }

    private function rules(): array
    {
        return [
            'incident_id' => ['required', 'integer', 'exists:incidents,id'],
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'technician')],
            'team' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'status' => ['required', 'in:planned,in_progress,completed,cancelled'],
            'result' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }

    private function formOptions(): array
    {
        return [
            'incidents' => Incident::with('zone')->latest('reported_at')->get(),
            'technicians' => User::technicians()->orderBy('name')->get(),
        ];
    }
}