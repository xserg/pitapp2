<?php

namespace App\Providers;


use App\Services\OptimalTarget\BruteForceAlgorithm;
use App\Services\OptimalTarget\OptimalTargetAlgorithm;

class OptimalTargetProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(OptimalTargetAlgorithm::class, function ($app) {
            return new BruteForceAlgorithm();
        });
    }
}
