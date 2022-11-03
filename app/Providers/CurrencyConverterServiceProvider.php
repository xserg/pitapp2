<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Currency\RatesServiceInterface;
use App\Services\Currency\CurrencyConverterService;

class CurrencyConverterServiceProvider extends ServiceProvider
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
        $this->app->singleton(CurrencyConverterService::class, function ($app) {
            return new CurrencyConverterService($app->make(RatesServiceInterface::class));
        });
    }
}
