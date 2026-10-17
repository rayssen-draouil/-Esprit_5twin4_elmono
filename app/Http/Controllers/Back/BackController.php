<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Financement;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Signalement;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BackController extends Controller
{
    public function dashboard(): View
    {
        $infraCount = Infrastructure::count();
        $activeIncidents = Incident::where('status', '!=', 'resolved')->where('status', '!=', 'closed')->count();
        $criticalInfras = Infrastructure::where('status', '!=', 'operational')->count();
        $budgetTotal = (float) Project::sum('budget');
        $fundingTotal = (float) Financement::sum('amount');
        $projectCount = Project::count();
        $inProgressProjects = Project::where('status', 'in_progress')->count();
        $completedProjects = Project::where('status', 'completed')->count();

        $kpis = [
            ['label' => 'Sites surveillés', 'value' => (string) $infraCount, 'trend' => 'Données réelles'],
            ['label' => 'Incidents actifs', 'value' => sprintf('%02d', $activeIncidents), 'trend' => 'Données réelles'],
            ['label' => 'Infrastructures critiques', 'value' => sprintf('%02d', $criticalInfras), 'trend' => 'Données réelles'],
            ['label' => 'Projets actifs', 'value' => (string) $inProgressProjects, 'trend' => 'Données réelles'],
            ['label' => 'Budget total', 'value' => number_format($budgetTotal, 0, ',', ' ') . ' €', 'trend' => number_format($fundingTotal, 0, ',', ' ') . ' € financés'],
        ];

        $activity = [
            'Capteur #A-204 reconnecté',
            'Nouvel incident signalé à Sète',
            'Validation du projet Adour-Garonne',
        ];

        $projectStats = [
            'total' => $projectCount,
            'in_progress' => $inProgressProjects,
            'completed' => $completedProjects,
            'budget' => $budgetTotal,
            'funding' => $fundingTotal,
            'remaining' => max(0, $budgetTotal - $fundingTotal),
        ];

        return view('back.dashboard', compact('kpis', 'activity', 'projectStats'));
    }

    public function incidents(): View
    {
        $incidents = Incident::with('zone')->orderByDesc('id')->get()->map(function ($item) {
            $statusLabels = [
                'reported' => 'Ouvert',
                'in_progress' => 'En cours',
                'resolved' => 'Résolu',
                'closed' => 'Fermé',
            ];

            return [
                'id' => 'INC-' . str_pad($item->id, 4, '0', STR_PAD_LEFT),
                'raw_id' => $item->id,
                'title' => $item->description,
                'type' => $item->type,
                'severity' => $item->severity,
                'site' => $item->location ?? ($item->zone->name ?? 'Zone'),
                'priority' => match ($item->severity) {
                    'critical' => 'Critique',
                    'high' => 'Élevée',
                    'low' => 'Faible',
                    default => 'Moyenne',
                },
                'status' => $statusLabels[$item->status] ?? $item->status,
                'status_raw' => $item->status,
                'date' => $item->reported_at ? $item->reported_at->format('d M Y') : now()->format('d M Y'),
            ];
        });

        $signalements = Signalement::whereNull('incident_id')->latest('reported_at')->get();
        $allSignalements = Signalement::with('incident')->latest('reported_at')->get();

        return view('back.incidents.index', compact('incidents', 'signalements', 'allSignalements'));
    }

    public function confirmSignalement(Request $request, Signalement $signalement): RedirectResponse
    {
        $validated = $request->validate([
            'severity' => 'required|in:low,medium,high,critical',
            'description' => 'required|string|max:5000',
        ]);

        DB::transaction(function () use ($signalement, $validated): void {
            $zone = Zone::firstOrCreate(
                ['name' => 'Zone locale'],
                ['address' => $signalement->location, 'risk_level' => $validated['severity']]
            );

            $incident = Incident::create([
                'zone_id' => $zone->id,
                'type' => $signalement->type,
                'severity' => $validated['severity'],
                'status' => 'in_progress',
                'description' => $validated['description'],
                'location' => $signalement->location,
                'photo_path' => $signalement->photo_path,
                'reported_at' => $signalement->reported_at,
            ]);

            $signalement->update([
                'incident_id' => $incident->id,
                'status' => 'in_progress',
            ]);
        });

        return back()->with('success', 'Le signalement a été lié à un nouvel incident.');
    }

    public function updateSignalement(Request $request, Signalement $signalement): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'description' => 'required|string|max:5000',
            'location' => 'required|string|max:255',
            'priority' => 'nullable|string|max:50',
            'status' => 'required|string|max:50',
        ]);

        $signalement->update($validated);

        return back()->with('success', 'Signalement modifié.');
    }

    public function destroySignalement(Signalement $signalement): RedirectResponse
    {
        DB::transaction(function () use ($signalement): void {
            if ($signalement->photo_path && ! $signalement->incident_id) {
                Storage::disk('public')->delete($signalement->photo_path);
            }
            $signalement->delete();
        });

        return back()->with('success', 'Signalement supprimé.');
    }

    public function updateIncident(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:100',
            'description' => 'required|string|max:5000',
            'location' => 'nullable|string|max:255',
            'severity' => 'required|in:low,medium,high,critical',
            'status' => 'required|in:reported,in_progress,resolved,closed',
        ]);

        $incident->update($validated + [
            'resolved_at' => in_array($validated['status'], ['resolved', 'closed'], true) ? now() : null,
        ]);
        $incident->signalements()->update(['status' => $validated['status']]);

        return back()->with('success', 'Incident modifié.');
    }

    public function destroyIncident(Incident $incident): RedirectResponse
    {
        DB::transaction(function () use ($incident): void {
            $incident->signalements()->update(['incident_id' => null, 'status' => 'new']);
            $incident->delete();
        });

        return back()->with('success', 'Incident supprimé.');
    }

    public function infrastructures(): RedirectResponse
    {
        return redirect()->route('infrastructures.index');
    }

    public function projects(): View
    {
        $projects = Project::all()->map(function ($item) {
            $budgetFormatted = number_format($item->budget / 1000000, 1, ',', ' ') . ' M€';
            if ($item->budget < 1000000) {
                $budgetFormatted = number_format($item->budget / 1000, 0, ',', ' ') . ' k€';
            }

            return [
                'name' => $item->name,
                'owner' => 'Équipe AquaSecure',
                'budget' => $budgetFormatted,
                'progress' => $item->progress,
                'status' => $item->status === 'in_progress' ? 'En cours' : ($item->status === 'completed' ? 'Opérationnel' : 'À l’étude'),
            ];
        });

        return view('back.projects.index', compact('projects'));
    }

    public function funding(): View
    {
        $funding = Financement::with('project')->get()->map(function ($item) {
            $amountFormatted = number_format($item->amount / 1000000, 1, ',', ' ') . ' M€';
            if ($item->amount < 1000000) {
                $amountFormatted = number_format($item->amount / 1000, 0, ',', ' ') . ' k€';
            }

            return [
                'name' => $item->source,
                'amount' => $amountFormatted,
                'used' => $item->status === 'received' ? '68%' : '20%',
                'deadline' => $item->funded_at ? $item->funded_at->format('d M Y') : '31 déc. 2026',
                'status' => $item->status === 'received' ? 'Actif' : 'Clôturé',
            ];
        });

        return view('back.funding.index', compact('funding'));
    }

    public function users(): View
    {
        $roleLabels = [
            'admin' => 'Administratrice',
            'manager' => 'Chef de projet',
            'technician' => 'Technicienne',
            'citizen' => 'Citoyen',
        ];

        $users = User::all()->map(function ($item) use ($roleLabels) {
            return [
                'name' => $item->name,
                'email' => $item->email,
                'role' => $roleLabels[$item->role] ?? ucfirst($item->role),
                'last' => 'Aujourd’hui',
                'state' => 'Actif',
            ];
        });

        return view('back.users.index', compact('users'));
    }
}
