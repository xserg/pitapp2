<?php

namespace App\Providers;

use App\Services\Hardware\InterconnectCalculator;
use Illuminate\Support\ServiceProvider;


class InterconnectCalculatorProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(InterconnectCalculator::class, function ($app) {
            return new InterconnectCalculator();
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
