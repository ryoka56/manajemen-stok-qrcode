<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Railway (dan kebanyakan platform hosting modern) terminate SSL di
        // edge proxy-nya, lalu terusin traffic ke container app secara
        // internal via http biasa. Laravel gak otomatis tau requestnya
        // "aslinya" https, jadi asset()/url() bisa generate link http://
        // yang ke-block browser sebagai mixed content di halaman https.
        // Paksa https di production biar semua URL yang di-generate
        // (termasuk URL foto barang) selalu benar.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
