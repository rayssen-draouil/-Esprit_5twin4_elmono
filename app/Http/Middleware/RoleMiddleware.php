<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $role = strtolower((string) ($user?->role ?? ''));
        $aliases = ['gestionnaire' => 'manager', 'citoyen' => 'citizen'];
        $allowedRoles = array_map(
            fn (string $value) => $aliases[strtolower($value)] ?? strtolower($value),
            $roles
        );

        if (! $user || ! in_array($aliases[$role] ?? $role, $allowedRoles, true)) {
            abort(403);
        }

        if ($role === 'technician' && in_array('technician', $allowedRoles, true) && ! $user->isTechnicianApproved()) {
            abort(403, 'Votre compte technicien doit être approuvé par un administrateur.');
        }

        return $next($request);
    }
}
