<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Link download laporan (Excel/PDF) dibuka lewat browser baru (url_launcher),
 * jadi tidak bisa menyisipkan header "Authorization: Bearer ..." seperti
 * request API biasa dari dalam aplikasi. Middleware ini mengizinkan token
 * Sanctum dikirim lewat query string (?token=...) sebagai gantinya, lalu
 * menyalinnya ke header Authorization sebelum masuk ke guard Sanctum.
 */
class TokenFromQueryString
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->headers->has('Authorization') && $request->query('token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        return $next($request);
    }
}
