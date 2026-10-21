<?php

namespace App\Http\Controllers;

use App\Services\TechnicianAssignmentInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function show(string $token): View|RedirectResponse
    {
        $invitation = $this->invitationService->findByRawToken($token);

        if (! $invitation || ! $invitation->maintenance) {
            return view('maintenances.invitation-response', [
                'token' => $token,
                'invitation' => null,
                'maintenance' => null,
                'technician' => null,
            ]);
        }

        // Role verification: If logged in with another non-technician role, show creative page
        if (Auth::check()) {
            $user = Auth::user();
            if (! $user->isTechnician() || ((int) $user->id !== (int) $invitation->technician_id && $user->role !== 'admin')) {
                return view('maintenances.not-concerned', [
                    'token' => $token,
                    'invitation' => $invitation,
                    'maintenance' => $invitation->maintenance,
                    'technician' => $invitation->technician,
                ]);
            }

            // If already accepted by this technician, redirect directly to technician workspace
            if ($invitation->isAccepted()) {
                return redirect()
                    ->route('technicien.interventions.index')
                    ->with('info', "Cette intervention (« {$invitation->maintenance->reference_code} ») a déjà été confirmée et se trouve dans vos ordres de mission.");
            }
        } else {
            // Frictionless secure login for the invited technician via verified secret token
            if ($invitation->technician && $invitation->isPending() && ! $invitation->hasExpired()) {
                Auth::login($invitation->technician);
            }
        }

        return view('maintenances.invitation-response', [
            'token' => $token,
            'invitation' => $invitation,
            'maintenance' => $invitation->maintenance,
            'technician' => $invitation->technician,
        ]);
    }

    /**
     * Explicit acceptance by technician (POST request with CSRF).
     */
    public function accept(Request $request, string $token): View|RedirectResponse
    {
        $invitation = $this->invitationService->findByRawToken($token);

        if (! $invitation || ! $invitation->maintenance) {
            return redirect()
                ->route('maintenances.invitation.show', ['token' => $token])
                ->with('error', "Jeton d'invitation introuvable ou expiré.");
        }

        // If authenticated user is NOT the technician, display creative not-concerned page
        if (Auth::check()) {
            $user = Auth::user();
            if (! $user->isTechnician() || ((int) $user->id !== (int) $invitation->technician_id && $user->role !== 'admin')) {
                return view('maintenances.not-concerned', [
                    'token' => $token,
                    'invitation' => $invitation,
                    'maintenance' => $invitation->maintenance,
                    'technician' => $invitation->technician,
                ]);
            }
        } else {
            // Auto-login the recipient technician
            if ($invitation->technician) {
                Auth::login($invitation->technician);
            }
        }

        $result = $this->invitationService->acceptInvitation($token);

        if (! $result['success']) {
            return redirect()
                ->route('maintenances.invitation.show', ['token' => $token])
                ->with('error', $result['message']);
        }

        // Only redirect to technician interventions list if role is technician
        if (Auth::check() && Auth::user()->isTechnician()) {
            return redirect()
                ->route('technicien.interventions.index')
                ->with('success', "Ordre de mission confirmé avec succès ! L'intervention {$invitation->maintenance->reference_code} est désormais en cours d'exécution dans votre planning.");
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

        if (! $result['success']) {
            return redirect()
                ->route('maintenances.invitation.show', ['token' => $token])
                ->with('error', $result['message']);
        }

        return redirect()
            ->route('maintenances.invitation.show', ['token' => $token])
            ->with('info', $result['message']);
    }
}
