<?php

namespace App\Providers;

use App\Services\ConfigData;
use App\Services\Deployment;
use App\Services\Revenue;
use Illuminate\Support\ServiceProvider;

class RevenueProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(Revenue::class, function ($app) {
            return new Revenue();
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
