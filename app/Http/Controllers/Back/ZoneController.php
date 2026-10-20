<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\ZoneRequest;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoneController extends Controller
{
    public function index(Request $request): View
    {
        $zones = Zone::withCount(['infrastructures', 'incidents'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->when($request->filled('risk'), fn ($q) => $q->where('risk_level', $request->risk))
            ->orderBy('name')
            ->get();

        return view('back.zones.index', compact('zones'));
    }

    public function create(): View
    {
        return view('back.zones.create');
    }

    public function store(ZoneRequest $request): RedirectResponse
    {
        Zone::create($request->validated());

        return redirect()->route('back.zones.index')->with('success', 'Zone ajoutée avec succès.');
    }

    public function show(Zone $zone): View
    {
        $zone->load([
            'infrastructures',
            'incidents' => fn ($q) => $q->latest('reported_at'),
            'alerts' => fn ($q) => $q->latest(),
        ]);

        return view('back.zones.show', compact('zone'));
    }

    public function edit(Zone $zone): View
    {
        return view('back.zones.edit', compact('zone'));
    }

    public function update(ZoneRequest $request, Zone $zone): RedirectResponse
    {
        $zone->update($request->validated());

        return redirect()->route('back.zones.show', $zone)->with('success', 'Zone modifiée avec succès.');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        // Les FK infrastructures/incidents sont en restrictOnDelete : on évite l'erreur SQL.
        if ($zone->infrastructures()->exists() || $zone->incidents()->exists()) {
            return redirect()->route('back.zones.index')
                ->with('error', 'Impossible de supprimer cette zone : elle contient des infrastructures ou des incidents.');
        }

        $zone->delete();

        return redirect()->route('back.zones.index')->with('success', 'Zone supprimée.');
    }
}
