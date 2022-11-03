<?php

namespace App\Providers;

use App\Services\LanguageTranslations;
use Illuminate\Support\ServiceProvider;

class LanguageTranslationsProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(LanguageTranslations::class, function ($app) {
            return new LanguageTranslations();
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
