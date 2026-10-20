<?php

namespace App\Http\Controllers;

use App\Models\Technicien;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechnicienController extends Controller
{
    public function index(Request $request): View
    {
        $technicians = Technicien::withCount('interventions')
            ->withExists([
                'interventions as is_on_intervention' => fn ($query) => $query->where('status', 'in_progress'),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('speciality', 'like', $search);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $status = $request->input('status');
                if ($status === 'busy') {
                    $query->where(function ($builder): void {
                        $builder->where('status', 'busy')
                            ->orWhere(function ($activeTechnicians): void {
                                $activeTechnicians->where('status', '!=', 'inactive')
                                    ->whereHas('interventions', fn ($interventions) => $interventions->where('status', 'in_progress'));
                            });
                    });

                    return;
                }

                if ($status === 'available') {
                    $query->where('status', 'available')
                        ->whereDoesntHave('interventions', fn ($interventions) => $interventions->where('status', 'in_progress'));

                    return;
                }

                $query->where('status', $status);
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('techniciens.index', [
            'technicians' => $technicians,
            'statuses' => ['available', 'busy', 'inactive'],
        ]);
    }

    public function show(Technicien $technicien): View
    {
        return view('techniciens.show', [
            'technician' => $technicien
                ->load(['interventions.incident'])
                ->loadExists([
                    'interventions as is_on_intervention' => fn ($query) => $query->where('status', 'in_progress'),
                ]),
        ]);
    }
}