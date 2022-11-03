<?php
/**
 *
 */

namespace App\Services\Analysis\Environment;


use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Services\Analysis\Pricing;
use App\Services\Analysis\PricingAccessTrait;

abstract class AbstractAnalyzer
{
    use PricingAccessTrait;

    /**
     * @param Environment $environment
     * @return $this
     */
    public function setEnvironmentDefaults(Environment $environment)
    {
        $environment->total_storage = $environment->useable_storage;
        $environment->ghz = null;
        $environment->cpu_architecture = null;
        $environment->purchase_price = 0;
        $environment->system_software_purchase_price = 0;
        $environment->total_maintenance = 0;
        $environment->total_hardware_maintenance_per_year = 0;
        $environment->total_hardware_usage_per_year = 0;
        $environment->total_system_software_maintenance_per_year = 0;
        $environment->total_hardware_warranty_savings = 0;
        $environment->total_hardware_warranty_per_year = [];
        $environment->power_cost_per_year = 0;
        $environment->os_support_per_year = 0;
        $environment->hypervisor_support_per_year = 0;
        $environment->middleware_support_per_year = 0;
        $environment->database_support_per_year = 0;
        $environment->os_license = 0;
        $environment->hypervisor_license = 0;
        $environment->middleware_license = 0;
        $environment->database_license = 0;
        $environment->total_power = 0;
        $environment->total_storage = 0;
        $environment->compute_power_cost_per_year = 0;
        $environment->storage_power_cost_per_year = 0;
        $environment->migration_services = $environment->migration_services ?: 0;

        if (empty($environment->cost_per_kwh)) {
            $environment->cost_per_kwh = 0;
        }

        $environment->metered_cost = $environment->cost_per_kwh;

        if ($environment->isConverged()) {
            $environment->useable_storage = 0;
        }

        return $this;
    }

    /**
     * @param Environment $environment
     * @return $this
     */
    public function calculateFteCosts(Environment $environment)
    {
        $environment->total_fte_cost = $environment->fte_salary * $environment->fte_qty * $environment->project->support_years;

        return $this;
    }

    /**
     * @param Environment $environment
     * @return $this
     */
    public function setConvergedServerConfigurationData(Environment $environment)
    {
        $groupedNodes = [];
        /** @var ServerConfiguration $config */
        foreach($environment->serverConfigurations as $config) {
            if($config->parent_configuration_id) {
                if(!isset($groupedNodes[$config->parent_configuration_id])) {
                    $groupedNodes[$config->parent_configuration_id] = [];
                }
                if (intval($config->total_qty) === 0) {
                    // Boot the processor;
                    $config->processor;
                }
                $groupedNodes[$config->parent_configuration_id][] = $config;
            }
        }

        $environment->groupedNodes = $groupedNodes;

        if (config('app.debug')) {
            logger('File: ' . __FILE__ . PHP_EOL . 'Function: ' . __FUNCTION__ . PHP_EOL . 'Line:' . __LINE__);
            logger($groupedNodes);
        }

        return $this;
    }
}
