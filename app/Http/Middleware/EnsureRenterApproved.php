<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya penyewa yang sudah disetujui admin yang boleh memakai fitur sewa (BR-09.3, REQ-VER-03).
 */
class EnsureRenterApproved
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isApprovedRenter()) {
            return $next($request);
        }

        $message = 'Akun Anda belum disetujui admin, sehingga belum dapat menyewa garasi.';

        if ($request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, $message);
        }

        return redirect()->route('renter.dashboard')->with('error', $message);
    }
}
