<?php

namespace App\Providers;

use App\Services\Currency\CannedRatesService;
use Illuminate\Support\ServiceProvider;
use App\Services\Currency\RatesServiceInterface;
use App\Services\Currency\FixerIntegrationService;

class RatesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(RatesServiceInterface::class, function () {
            if ($this->app->environment("testing")) {
                return new CannedRatesService();
            } else {
                return new FixerIntegrationService();
            }
        });
    }
}
