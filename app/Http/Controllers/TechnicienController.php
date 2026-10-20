<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TechnicienController extends Controller
{
    /**
     * Le technicien est un rôle applicatif : la page gère les utilisateurs
     * portant le rôle `technician` (et les comptes historiciquement affectés
     * comme intervenants).
     */
    public function index(Request $request): View
    {
        $technicians = User::query()
            ->where(function ($query) {
                $query->where('role', 'technician')
                    ->orWhereHas('maintenances')
                    ->orWhereHas('interventions');
            })
            ->withCount('interventions')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('speciality', 'like', $search);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('techniciens.index', [
            'technicians' => $technicians,
            'statuses' => ['available', 'busy', 'inactive'],
        ]);
    }

    public function create(): View
    {
        return view('techniciens.create', ['technician' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $technician = new User();
        $technician->fill($validated);
        $technician->role = 'technician';
        $technician->password = $validated['password'] ?? User::DEFAULT_PASSWORD;
        $technician->email_verified_at = now();
        $technician->save();

        return redirect()->route('techniciens.show', $technician)->with('success', 'Technicien ajouté avec succès.');
    }

    public function show(User $technicien): View
    {
        return view('techniciens.show', [
            'technician' => $technicien->load(['interventions.incident', 'maintenances.infrastructure']),
        ]);
    }

    public function edit(User $technicien): View
    {
        return view('techniciens.edit', ['technician' => $technicien]);
    }

    public function update(Request $request, User $technicien): RedirectResponse
    {
        $validated = $request->validate($this->rules($technicien));

        $technicien->fill($validated);

        if (! empty($validated['password'])) {
            $technicien->password = $validated['password'];
        }

        $technicien->save();

        return redirect()->route('techniciens.show', $technicien)->with('success', 'Technicien modifié avec succès.');
    }

    public function destroy(User $technicien): RedirectResponse
    {
        $technicien->delete();

        return redirect()->route('techniciens.index')->with('success', 'Technicien supprimé. Les interventions associées restent sans technicien.');
    }

    private function rules(?User $technicien = null): array
    {
        $email = $technicien
            ? Rule::unique('users', 'email')->ignore($technicien->id)
            : Rule::unique('users', 'email');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $email],
            'phone' => ['nullable', 'string', 'max:30'],
            'speciality' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:available,busy,inactive'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }
}
