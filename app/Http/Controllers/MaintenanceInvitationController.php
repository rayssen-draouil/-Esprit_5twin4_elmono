<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceAssignmentInvitation;
use App\Services\TechnicianAssignmentInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceInvitationController extends Controller
{
    protected TechnicianAssignmentInvitationService $invitationService;

    public function __construct(TechnicianAssignmentInvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * Display the invitation confirmation page (GET request does NOT mutate state).
     * Protects against email security link scanners.
     */
    public function show(string $token): View
    {
        $invitation = $this->invitationService->findByRawToken($token);

        return view('maintenances.invitation-response', [
            'token' => $token,
            'invitation' => $invitation,
            'maintenance' => $invitation?->maintenance,
            'technician' => $invitation?->technician,
        ]);
    }

    /**
     * Explicit acceptance by technician (POST request with CSRF).
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $result = $this->invitationService->acceptInvitation($token);

        if (!$result['success']) {
            return redirect()
                ->route('maintenances.invitation.show', ['token' => $token])
                ->with('error', $result['message']);
        }

        return redirect()
            ->route('maintenances.invitation.show', ['token' => $token])
            ->with('success', $result['message']);
    }

    /**
     * Explicit decline by technician (POST request with CSRF).
     */
    public function decline(Request $request, string $token): RedirectResponse
    {
        $reason = $request->input('decline_reason');
        $result = $this->invitationService->declineInvitation($token, $reason);

        if (!$result['success']) {
            return redirect()
                ->route('maintenances.invitation.show', ['token' => $token])
                ->with('error', $result['message']);
        }

        return redirect()
            ->route('maintenances.invitation.show', ['token' => $token])
            ->with('info', $result['message']);
    }
}
