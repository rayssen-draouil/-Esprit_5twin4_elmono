<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use App\Models\MaintenanceAssignmentInvitation;
use App\Models\Intervention;
use App\Models\Technicien;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        return match (strtolower((string) $request->user()->role)) {
            'admin' => redirect()->route('admin.dashboard'),
            'manager', 'gestionnaire' => redirect()->route('manager.dashboard'),
            'technician' => redirect()->route('technicien.interventions.index'),
            default => redirect()->route('citizen.dashboard'),
        };
    }

    public function manager(): View
    {
        // 1. Interventions awaiting manager validation (Priority #1)
        $awaitingValidation = Maintenance::with(['infrastructure.zone', 'technician'])
            ->where('status', 'awaiting_validation')
            ->orderByDesc('completion_submitted_at')
            ->get();

        // 2. Pending invitations waiting for technician response/confirmation
        $pendingInvitations = MaintenanceAssignmentInvitation::with(['maintenance.infrastructure', 'technician', 'manager'])
            ->where('status', MaintenanceAssignmentInvitation::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('invited_at')
            ->get();

        // 3. Recent invitations with status updates (Accepted/Confirmed, Declined)
        $recentInvitations = MaintenanceAssignmentInvitation::with(['maintenance.infrastructure', 'technician', 'manager'])
            ->whereIn('status', [
                MaintenanceAssignmentInvitation::STATUS_ACCEPTED,
                MaintenanceAssignmentInvitation::STATUS_DECLINED,
            ])
            ->orderByDesc('responded_at')
            ->limit(6)
            ->get();

        // 4. Maintenances currently in progress
        $inProgressMaintenances = Maintenance::with(['infrastructure.zone', 'technician'])
            ->where('status', 'in_progress')
            ->orderByDesc('started_at')
            ->limit(8)
            ->get();

        $inProgressInterventions = Intervention::with(['incident', 'technician'])
            ->where('status', 'in_progress')
            ->orderByDesc('started_at')
            ->limit(8)
            ->get();

        $approvedTechnicians = Technicien::withTechnicianAccount()
            ->with('user')
            ->orderBy('name')
            ->get();

        $activeTechnicianIds = $inProgressMaintenances->pluck('technician_id')
            ->merge($inProgressInterventions->pluck('technician_id'))
            ->filter()
            ->unique();
        $onSiteTechnicians = $approvedTechnicians
            ->whereIn('id', $activeTechnicianIds)
            ->values();

        // 5. Recently completed & validated maintenances
        $recentlyValidated = Maintenance::with(['infrastructure', 'technician', 'validator'])
            ->where('status', 'completed')
            ->orderByDesc('completion_validated_at')
            ->limit(5)
            ->get();

        $recentlyCompletedInterventions = Intervention::with(['incident', 'technician'])
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get();

        // Summary KPI stats
        $stats = [
            'awaiting_validation_count' => $awaitingValidation->count(),
            'pending_invitations_count' => $pendingInvitations->count(),
            'in_progress_count' => Maintenance::where('status', 'in_progress')->count()
                + Intervention::where('status', 'in_progress')->count(),
            'completed_month_count' => Maintenance::where('status', 'completed')
                ->whereBetween('completion_validated_at', [now()->startOfMonth(), now()])
                ->count()
                + Intervention::where('status', 'completed')
                    ->whereBetween('completed_at', [now()->startOfMonth(), now()])
                    ->count(),
        ];

        return view('dashboards.manager', compact(
            'awaitingValidation',
            'pendingInvitations',
            'recentInvitations',
            'inProgressMaintenances',
            'inProgressInterventions',
            'approvedTechnicians',
            'onSiteTechnicians',
            'recentlyValidated',
            'recentlyCompletedInterventions',
            'stats'
        ));
    }

    public function citizen(): View { return view('dashboards.citizen'); }
}
