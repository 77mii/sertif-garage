<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUnverifiedTenant
{
    /**
     * Restrict the Account Status Banner to pending or rejected tenants.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.garages.index');
        }

        if ($user->isApproved()) {
            return redirect()->route('tenant.rentals.create');
        }

        return $next($request);
    }
}
