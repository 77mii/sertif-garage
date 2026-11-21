<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedTenant
{
    /**
     * Allow access only to approved penyewa accounts.
     * Pending and rejected tenants are redirected to their status banner;
     * admins receive 403 Forbidden.
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
            abort(403, 'Akses ditolak. Fitur Sewa Baru hanya tersedia untuk Penyewa.');
        }

        if (! $user->isApproved()) {
            return redirect()->route('tenant.status');
        }

        return $next($request);
    }
}
