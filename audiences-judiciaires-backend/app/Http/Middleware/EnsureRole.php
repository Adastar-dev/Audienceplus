<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur || ! in_array($utilisateur->role, $roles, true)) {
            return response()->json(['message' => 'Accès refusé pour ce rôle.'], 403);
        }

        return $next($request);
    }
}
