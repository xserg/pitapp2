<?php
/**
 *
 */

namespace App\Providers;


use App\Services\Consolidation;
use App\Helpers\Consolidation as ConsolidationHelper;

class ConsolidationProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(Consolidation::class, function ($app) {
            return new Consolidation();
        });

        $this->app->singleton(ConsolidationHelper::class, function($app){
            return new ConsolidationHelper();
        });

        $this->app->singleton(Consolidation\CloudConsolidator::class, function ($app) {
            return new Consolidation\CloudConsolidator();
        });

        $this->app->singleton(Consolidation\CloudConsolidator\PhysicalCloudConsolidator::class, function ($app) {
            return new Consolidation\CloudConsolidator\PhysicalCloudConsolidator();
        });

        $this->app->singleton(Consolidation\CloudConsolidator\HybridCloudConsolidator::class, function ($app) {
            return new Consolidation\CloudConsolidator\HybridCloudConsolidator();
        });

        /*$this->app->singleton(Consolidation\CloudConsolidator\VmCloudConsolidator::class, function ($app) {
            @todo - Currently class is abstract
            return new Consolidation\CloudConsolidator\VmCloudConsolidator();
        });*/

        $this->app->singleton(Consolidation\DefaultConsolidator::class, function ($app) {
            return new Consolidation\DefaultConsolidator();
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