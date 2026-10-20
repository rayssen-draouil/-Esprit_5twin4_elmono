<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlertRequest;
use App\Models\Alert;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $alerts = Alert::with(['zone', 'incident'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('type', 'like', $term)->orWhere('message', 'like', $term));
            })
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->when($request->filled('zone'), fn ($q) => $q->where('zone_id', $request->zone))
            ->latest()
            ->get();

        $zones = Zone::orderBy('name')->get(['id', 'name']);

        return view('back.alerts.index', compact('alerts', 'zones'));
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

        return view('back.alerts.show', compact('alert'));
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

    /** @return array{zones: \Illuminate\Support\Collection} */
    private function formData(): array
    {
        return ['zones' => Zone::orderBy('name')->get(['id', 'name'])];
    }

    /** @return array<string, mixed> */
    private function payload(AlertRequest $request): array
    {
        $data = $request->safe()->only(['zone_id', 'incident_id', 'type', 'severity', 'message']);
        $data['read_at'] = $request->boolean('read') ? ($request->route('alert')?->read_at ?? now()) : null;

        return $data;
    }
}
