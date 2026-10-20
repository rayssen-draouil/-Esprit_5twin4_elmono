<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Ces identifiants sont incorrects.'])->withInput();
        }

        $user = $request->user();
        if (! $user->isTechnicianApproved()) {
            Auth::logout();

            $message = $user->technician_approval_status === User::TECHNICIAN_APPROVAL_REJECTED
                ? 'Votre demande de compte technicien a été refusée. Contactez un administrateur.'
                : 'Votre compte technicien est en attente de validation par un administrateur.';

            return back()->withErrors(['email' => $message])->withInput();
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['password_confirmation', 'speciality', 'skills']);
        $speciality = $request->safe()->only('speciality')['speciality'] ?? null;
        $skills = $request->safe()->only('skills')['skills'] ?? null;
        if ($request->role === 'technician') {
            $data['technician_approval_status'] = User::TECHNICIAN_APPROVAL_PENDING;
        }

        $user = DB::transaction(function () use ($data, $speciality, $skills): User {
            $user = User::create($data);
            if ($user->role === 'technician') {
                $user->technicien()->create([
                    'name' => $user->name,
                    'email' => $user->email,
                    'speciality' => $speciality,
                    'skills' => $skills,
                    'status' => 'available',
                ]);
            }

            return $user;
        });

        if ($user->role === 'technician') {
            return redirect()
                ->route('login')
                ->with('success', 'Votre demande de compte technicien a été envoyée. Vous pourrez vous connecter après approbation par un administrateur.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send verification email on registration: ' . $e->getMessage());
        }

        return redirect()->route('dashboard')->with('success', 'Votre compte a été créé.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Vous êtes déconnecté.');
    }
}
