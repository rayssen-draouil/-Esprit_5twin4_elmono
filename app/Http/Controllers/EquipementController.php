<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipementRequest;
use App\Http\Requests\UpdateEquipementRequest;
use App\Models\Equipement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EquipementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $equipements = Equipement::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%')
                    ->orWhere('serial_number', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%');
            }))
            ->with([
            'interventions' => fn ($query) => $query
                ->with('technician')
                ->whereNotIn('interventions.status', ['completed', 'cancelled'])
                ->orderBy('interventions.scheduled_at'),
            ])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $statusCounts = Equipement::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $inventoryStats = [
            'total' => $statusCounts->sum(),
            'available' => $statusCounts->get('available', 0),
            'in_use' => $statusCounts->get('in_use', 0),
            'maintenance' => $statusCounts->get('maintenance', 0) + $statusCounts->get('out_of_service', 0),
        ];

        return view('equipements.index', compact('equipements', 'inventoryStats', 'search'));
    }

    public function create(): View
    {
        return view('equipements.create');
    }

    public function store(StoreEquipementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['image']);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('equipements', 'public');
            if (! is_string($imagePath)) {
                throw new \RuntimeException('Impossible d’enregistrer l’image de l’équipement.');
            }

            $data['image_path'] = $imagePath;
        }

        $equipement = Equipement::create($data);

        return redirect()->route('equipements.show', $equipement)->with('success', 'Équipement ajouté avec succès.');
    }

    public function show(Equipement $equipement): View
    {
        $equipement->load([
            'interventions' => fn ($query) => $query
                ->with('technician')
                ->whereNotIn('interventions.status', ['completed', 'cancelled'])
                ->orderBy('interventions.scheduled_at'),
        ]);

        return view('equipements.show', compact('equipement'));
    }

    public function edit(Equipement $equipement): View
    {
        return view('equipements.edit', ['equipement' => $equipement]);
    }

    public function update(UpdateEquipementRequest $request, Equipement $equipement): RedirectResponse
    {
        $data = $request->validated();
        unset($data['image']);
        $previousImagePath = $equipement->image_path;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('equipements', 'public');
            if (! is_string($imagePath)) {
                throw new \RuntimeException('Impossible d’enregistrer l’image de l’équipement.');
            }

            $data['image_path'] = $imagePath;
        }

        $equipement->update($data);

        if (isset($data['image_path']) && $previousImagePath) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return redirect()->route('equipements.show', $equipement)->with('success', 'Équipement modifié avec succès.');
    }

    public function destroy(Equipement $equipement): RedirectResponse
    {
        $imagePath = $equipement->image_path;
        $equipement->delete();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('equipements.index')->with('success', 'Équipement supprimé.');
    }
}
