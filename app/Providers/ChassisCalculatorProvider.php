<?php

namespace App\Providers;

use App\Services\Hardware\ChassisCalculator;
use App\Services\Hardware\InterconnectCalculator;
use Illuminate\Support\ServiceProvider;


class ChassisCalculatorProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(ChassisCalculator::class, function ($app) {
            return new ChassisCalculator();
        });

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
