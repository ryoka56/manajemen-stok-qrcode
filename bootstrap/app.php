<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\TokenFromQueryString;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'token.query' => TokenFromQueryString::class,
        ]);

        // PENTING: Laravel punya daftar prioritas middleware internal
        // (buat middleware bawaan seperti auth) yang menentukan urutan
        // EKSEKUSI SEBENARNYA - BUKAN urutan literal yang ditulis di array
        // route group. Tanpa baris ini, 'auth:sanctum' bisa saja tetap
        // dieksekusi SEBELUM 'token.query', padahal di routes/api.php
        // 'token.query' ditulis lebih dulu. Akibatnya token dari query
        // string (?token=...) belum sempat disalin ke header Authorization
        // saat auth:sanctum mengecek - jadi selalu dianggap belum login
        // ("Unauthenticated"), walau tokennya valid. Ini penyebab laporan
        // PDF/Excel gagal diakses (khususnya kalau dibuka user yang bukan
        // request pertama kena warm code path berbeda).
        $middleware->priority([
            TokenFromQueryString::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua request ke /api/* selalu dibalas JSON,
        // termasuk saat belum login (401) - bukan mencoba redirect ke halaman login.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated. Silakan login terlebih dahulu.'], 401);
            }
        });

    })->create();
