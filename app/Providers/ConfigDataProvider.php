<?php

namespace App\Providers;

use App\Services\ConfigData;
use Illuminate\Support\ServiceProvider;

class ConfigDataProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(ConfigData::class, function ($app) {
            return new ConfigData();
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
