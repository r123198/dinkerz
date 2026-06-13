<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageFacility
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->canManageFacility() || $user->tenant_id === null) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
