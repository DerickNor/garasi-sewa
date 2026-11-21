<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya meneruskan request bila role pengguna sesuai, mis. `role:admin` (REQ-AUTH-03).
 */
class EnsureRole
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $role  nilai UserRole yang diizinkan (admin/penyewa)
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role !== UserRole::from($role)) {
            abort(Response::HTTP_FORBIDDEN, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
