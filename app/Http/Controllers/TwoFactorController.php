<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class TwoFactorController extends Controller
{
    public function challenge(): View
    {
        abort_unless(session()->has('auth.2fa.user_id'), 403);

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $user = User::findOrFail(session('auth.2fa.user_id'));
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        $valid = $provider->verify($secret, $validated['code']);

        if (! $valid && $user->two_factor_recovery_codes) {
            $codes = $user->recoveryCodes();
            $recoveryCode = collect($codes)->first(fn (string $code) => hash_equals($code, $validated['code']));
            if ($recoveryCode) {
                $user->replaceRecoveryCode($recoveryCode);
                $valid = true;
            }
        }
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Le code à deux facteurs est invalide.']);
        }

        Auth::login($user, session('auth.2fa.remember', false));
        $request->session()->forget(['auth.2fa.user_id', 'auth.2fa.remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $action): RedirectResponse
    {
        $action($request->user());

        return back()->with('success', 'Scannez le QR code puis confirmez l’activation.');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $action): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $action($request->user(), $request->string('code'));

        return back()->with('success', 'La double authentification est activée.');
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $action): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password'], 'code' => ['required', 'digits:6']]);
        $user = $request->user();
        $provider = app(TwoFactorAuthenticationProvider::class);
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        if (! $provider->verify($secret, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'Le code à deux facteurs est invalide.']);
        }
        $action($user);

        return back()->with('success', 'La double authentification est désactivée.');
    }

    public function recoveryCodes(Request $request): View
    {
        abort_unless($request->user()->hasEnabledTwoFactorAuthentication(), 404);

        return view('auth.recovery-codes');
    }

    public function showRecoveryCodes(Request $request): View
    {
        $request->validate(['password' => ['required', 'current_password']]);

        return view('auth.recovery-codes', ['codes' => $request->user()->recoveryCodes()]);
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $action): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $action($request->user());

        return back()->with('success', 'De nouveaux codes de récupération ont été générés.');
    }
}
