<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is super admin OR has admin role
        if (! $user || (! $user->is_super_admin && ! $user->hasRole('admin'))) {
            abort(403, 'Accès non autorisé. Vous devez être administrateur.');
        }

        return $next($request);
    }
}
