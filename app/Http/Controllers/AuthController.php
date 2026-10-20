<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $key = strtolower($request->string('email')).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Trop de tentatives. Réessayez dans quelques minutes.'])->withInput();
        }

        if (! Auth::validate($request->only('email', 'password'))) {
            RateLimiter::hit($key, 60);
            return back()->withErrors(['email' => 'Ces identifiants sont incorrects.'])->withInput();
        }

        RateLimiter::clear($key);
        $user = User::where('email', $request->string('email'))->firstOrFail();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'auth.2fa.user_id' => $user->getKey(),
                'auth.2fa.remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->except('password_confirmation'),
            'role' => 'citizen',
        ]);
        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')->with('success', 'Votre compte a été créé. Vérifiez votre adresse e-mail.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Vous êtes déconnecté.');
    }
}
