<?php

namespace App\Providers;

use App\Services\ConfigData;
use App\Services\Deployment;
use Illuminate\Support\ServiceProvider;

class DeploymentProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(Deployment::class, function ($app) {
            return new Deployment($app->make(ConfigData::class));
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
