<?php
/**
 *
 */

namespace App\Providers;


use App\Services\CpmImport;

class CpmImportProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(CpmImport::class, function ($app) {
            return new CpmImport();
        });
    }
}