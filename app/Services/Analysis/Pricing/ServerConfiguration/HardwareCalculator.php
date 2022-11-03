<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\ServerConfiguration;


use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;

class HardwareCalculator
{
    /**
     * @param ServerConfiguration $serverConfiguration
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return $this
     */
    public function calculateCosts(ServerConfiguration $serverConfiguration, Environment $environment, Environment $existingEnvironment = null)
    {
        $qty = $serverConfiguration->getQty();

        if (!$environment->isCloud() && !$serverConfiguration->isConverged() && $serverConfiguration->isPhysical() && !$serverConfiguration->processor_id) {
            throw new \Exception("Insufficient data to run analysis. Target Environment {$environment->name} has one or more server configurations with no processor.");
        }
        if (!$environment->ghz && !$serverConfiguration->isConverged()) {
            $environment->ghz = $serverConfiguration->processor->ghz;
        } elseif ($serverConfiguration->processor
            && $environment->ghz != $serverConfiguration->processor->ghz
            && $environment->ghz != "Various") {
            $environment->ghz = "Various";
        }


        if ($environment->cpu_architecture == null && !$serverConfiguration->isConverged()) {
            $environment->cpu_architecture = $serverConfiguration->processor->name;
        } else if ($serverConfiguration->processor
            && $environment->cpu_architecture != $serverConfiguration->processor->name
            && $environment->cpu_architecture != "Various") {
            $environment->cpu_architecture = "Various";
        }

        if (!$serverConfiguration->isConverged()) {
            if (!$serverConfiguration->useable_storage) {
                $serverConfiguration->useable_storage = $serverConfiguration->raw_storage / 2.0;
            }
            $environment->useable_storage += $serverConfiguration->useable_storage * $qty;
        }

        $hardwarePurchacePrice = ($serverConfiguration->acquisition_cost * (1.0 - $serverConfiguration->discount_rate / 100.0)) * $qty;

        $environment->purchase_price += $hardwarePurchacePrice;

        $environment->system_software_purchase_price += ($serverConfiguration->system_software_list_price * (1.0 - $serverConfiguration->system_software_discount_rate / 100.0)) * $qty;

        $hardwareMaintenanceCost = $serverConfiguration->annual_maintenance_list_price
            * (1.0 - ($serverConfiguration->annual_maintenance_discount_rate ? $serverConfiguration->annual_maintenance_discount_rate : 0) / 100.0)
            * $qty;

        $environment->total_hardware_maintenance_per_year += $hardwareMaintenanceCost;

        $hardwareUsageCost = $serverConfiguration->annual_usage_list_price
            * (1.0 - ($serverConfiguration->annual_usage_discount_rate ? $serverConfiguration->annual_usage_discount_rate : 0) / 100.0)
            * $qty;

        $environment->total_hardware_usage_per_year += $hardwareUsageCost;
        $environment->total_hardware_usage += $hardwareUsageCost * $environment->project->support_years;

        $environment->total_system_software_maintenance_per_year += $serverConfiguration->annual_system_software_maintenance_list_price
            * (1.0 - ($serverConfiguration->annual_system_software_maintenance_discount_rate ? $serverConfiguration->annual_system_software_maintenance_discount_rate : 0) / 100.0)
            * $qty;

        if (!$environment->isExisting() && $warrantyYears = intval($serverConfiguration->hardware_warranty_period) && $hardwareMaintenanceCost) {
            // Calculate the warranty for this server configuration
            // The warranty should be the hardware maintenance cost (including the discount)
            // Multiplied by either the warranty period, or the project support years
            // Should always be the lesser of the two (if project is longer than the warranty, only use warranty)
            // If project is less than warranty, only use project length
            $warrantyTerm = min($environment->project->support_years, $serverConfiguration->hardware_warranty_period);
            $environment->total_hardware_warranty_savings += $warrantyTerm * $hardwareMaintenanceCost;

            $yearsCalculations = max(1, $environment->project->support_years);
            $warrantyPeriod = $environment->total_hardware_warranty_per_year;
            for ($i = 1; $i <= $yearsCalculations; $i++) {

                if (!isset($environment->total_hardware_warranty_per_year[$i])) {
                    $warrantyPeriod[$i] = 0;
                }
                if ($serverConfiguration->hardware_warranty_period >= $i) {
                    $warrantyPeriod[$i] += $hardwareMaintenanceCost;
                }
            }

            $environment->total_hardware_warranty_per_year = $warrantyPeriod;

        }
        
        // Target environments use the same kwh cost and the existing
        if (!$environment->isExisting() && $existingEnvironment && isset($existingEnvironment->cost_per_kwh) && isset($existingEnvironment->metered_cost)) {
          $environment->cost_per_kwh = $existingEnvironment->cost_per_kwh;
          $environment->metered_cost = $existingEnvironment->metered_cost;
        }

        $metered_cost = $environment->metered_cost ?? 0;

        $environment->power_cost_per_year += $serverConfiguration->kilo_watts * $metered_cost * 24 * 30 * 12 * $qty;
        $environment->compute_power_cost_per_year += $serverConfiguration->kilo_watts * $metered_cost * 24 * 30 * 12 * $qty;

        $environment->total_power += $serverConfiguration->kilo_watts * $qty;

        return $this;
    }
}