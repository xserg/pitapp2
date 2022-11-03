<?php

namespace App\Providers;

use App\Helpers\Analysis\Cloud;
use App\Helpers\Analysis\Cloud as CloudHelper;
use App\Services\Analysis;
use Illuminate\Support\ServiceProvider;

class AnalysisProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(Analysis::class, function ($app) {
            return new Analysis();
        });

        $this->app->singleton(Analysis\Pricing::class, function ($app) {
            return new Analysis\Pricing();
        });

        $this->app->singleton(Analysis\Pricing\Environment\TotalsCalculator::class, function ($app) {
            return new Analysis\Pricing\Environment\TotalsCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Environment\StorageCalculator::class, function ($app) {
            return new Analysis\Pricing\Environment\StorageCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Environment\NetworkCalculator::class, function ($app) {
            return new Analysis\Pricing\Environment\NetworkCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Environment\Cloud\BandwidthCalculator::class, function ($app) {
            return new Analysis\Pricing\Environment\Cloud\BandwidthCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Software\Calculator::class, function ($app) {
            return new Analysis\Pricing\Software\Calculator();
        });

        $this->app->singleton(Analysis\Pricing\Software\Map::class, function ($app) {
            return new Analysis\Pricing\Software\Map();
        });

        $this->app->singleton(CloudHelper::class, function($app){
            return new CloudHelper();
        });

        $this->app->singleton(Analysis\Pricing\Software\Map\Calculator::class, function ($app) {
            return new Analysis\Pricing\Software\Map\Calculator();
        });

        $this->app->singleton(Analysis\Pricing\Software\FeatureCalculator::class, function ($app) {
            return new Analysis\Pricing\Software\FeatureCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Software\FeatureCalculator::class, function ($app) {
            return new Analysis\Pricing\Software\FeatureCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Project\SavingsCalculator::class, function ($app) {
            return new Analysis\Pricing\Project\SavingsCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Project\SavingsByCategoryCalculator::class, function ($app) {
            return new Analysis\Pricing\Project\SavingsByCategoryCalculator();
        });

        $this->app->singleton(Analysis\Pricing\ServerConfiguration\SoftwareCalculator::class, function ($app) {
            return new Analysis\Pricing\ServerConfiguration\SoftwareCalculator();
        });

        $this->app->singleton(Analysis\Pricing\ServerConfiguration\GroupSoftwareCalculator::class, function ($app) {
            return new Analysis\Pricing\ServerConfiguration\GroupSoftwareCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Software\CloudCalculator::class, function ($app) {
            return new Analysis\Pricing\Software\CloudCalculator();
        });

        $this->app->singleton(Analysis\Report\Web::class, function($app){
            return new Analysis\Report\Web();
        });

        $this->app->singleton(Analysis\Report\Pdf::class, function($app){
            return new Analysis\Report\Pdf();
        });

        $this->app->singleton(Analysis\Report\Pdf\Physical\Consolidation::class, function($app){
            return new Analysis\Report\Pdf\Physical\Consolidation();
        });

        $this->app->singleton(Analysis\Report\Pdf\Hybrid\Consolidation::class, function($app){
            return new Analysis\Report\Pdf\Hybrid\Consolidation();
        });

        $this->app->singleton(Analysis\Report\Pdf\Vm\Consolidation::class, function($app){
            return new Analysis\Report\Pdf\Vm\Consolidation();
        });

        $this->app->singleton(Analysis\Report\WordDoc::class, function($app){
            return new Analysis\Report\WordDoc();
        });

        $this->app->singleton(Analysis\Report\WordDoc\PhysicalConsolidation::class, function($app){
            return new Analysis\Report\WordDoc\PhysicalConsolidation();
        });

        $this->app->singleton(Analysis\Report\WordDoc\HybridConsolidation::class, function($app){
            return new Analysis\Report\WordDoc\HybridConsolidation();
        });

        $this->app->singleton(Analysis\Report\WordDoc\VmConsolidation::class, function($app){
            return new Analysis\Report\WordDoc\VmConsolidation();
        });

        $this->app->singleton(Analysis\ProjectAnalyzer::class, function($app){
            return new Analysis\ProjectAnalyzer();
        });

        $this->app->singleton(Analysis\Environment\Existing\DefaultExistingAnalyzer::class, function($app){
            return new Analysis\Environment\Existing\DefaultExistingAnalyzer();
        });

        $this->app->singleton(Analysis\Environment\Target\DefaultTargetAnalyzer::class, function($app){
            return new Analysis\Environment\Target\DefaultTargetAnalyzer();
        });

        $this->app->singleton(Analysis\Environment\Target\DefaultTargetAnalyzer\HybridCopier::class, function($app){
            return new Analysis\Environment\Target\DefaultTargetAnalyzer\HybridCopier();
        });

        $this->app->singleton(Analysis\Environment\Target\CloudTargetAnalyzer::class, function($app){
            return new Analysis\Environment\Target\CloudTargetAnalyzer();
        });

        $this->app->singleton(Analysis\Pricing\Environment\Cloud\StorageCalculator::class, function($app){
            return new Analysis\Pricing\Environment\Cloud\StorageCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Environment\Cloud\InstanceCalculator::class, function($app){
            return new Analysis\Pricing\Environment\Cloud\InstanceCalculator();
        });

        $this->app->singleton(Analysis\Pricing\Environment\Cloud\Instance\LowestCostCalculator::class, function($app){
            return new Analysis\Pricing\Environment\Cloud\Instance\LowestCostCalculator();
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
