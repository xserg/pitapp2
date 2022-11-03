<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Environment;


use App\Models\Project\Environment;
use App\Services\Currency\CurrencyConverter;

class StorageCalculator
{
    /**
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return $this
     */
    public function calculateCosts(Environment $environment, Environment $existingEnvironment = null)
    {
        if ($environment->raw_storage) {
            $storagePowerCooling = 0;
            switch ($environment->storage_type) {
                case Environment::STORAGE_TYPE_HDD_15K:
                    $environment->pCost = 143.32;
                    $environment->cCost = 143.29;
                    $environment->dFactor = 1.667;
                    $environment->driveSize = 600;
                    $environment->driveType = "HDD 15k";
                    $storagePowerCooling = ($environment->metered_cost * 143.32 + $environment->metered_cost * 143.29) * (5.0 / 3.0) * $environment->raw_storage;
                    break;
                case Environment::STORAGE_TYPE_HDD_10K:
                    $environment->pCost = 69.51;
                    $environment->cCost = 69.41;
                    $environment->dFactor = 1.11;
                    $environment->driveSize = 900;
                    $environment->driveType = "HDD 10k";
                    $storagePowerCooling = ($environment->metered_cost * 69.51 + $environment->metered_cost * 69.41) * (10.0 / 9.0) * $environment->raw_storage;
                    break;
                case Environment::STORAGE_TYPE_HDD_7_2K:
                    $environment->pCost = 99.06;
                    $environment->cCost = 99.03;
                    $environment->dFactor = .5;
                    $environment->driveSize = "2,048";
                    $environment->driveType = "HDD 7.2k";
                    $storagePowerCooling = ($environment->metered_cost * 99.06 + $environment->metered_cost * 99.03) * (.5) * $environment->raw_storage;
                    break;
                case Environment::STORAGE_TYPE_SSD:
                    $environment->pCost = 35.5;
                    $environment->cCost = 35.49;
                    $environment->dFactor = 1.25;
                    $environment->driveSize = "800";
                    $environment->driveType = "SSD";
                    $storagePowerCooling = ($environment->metered_cost * 35.5 + $environment->metered_cost * 35.49) * (1.25) * $environment->raw_storage;
                    break;
            }
            $environment->power_cost_per_year += $storagePowerCooling;
            $environment->storage_power_cost_per_year = $storagePowerCooling;
        }

        $environment->power_cost = $environment->power_cost_per_year * $environment->project->support_years;
        $environment->compute_power_cost = $environment->compute_power_cost_per_year * $environment->project->support_years;
        $environment->storage_power_cost = $environment->storage_power_cost_per_year * $environment->project->support_years;
        // Subtract the warranty from the total. Since the warranty period can be different for different appliances / servers
        // We don't factor it into the per-year cost
        $environment->total_hardware_maintenance = ($environment->total_hardware_maintenance_per_year * $environment->project->support_years) - floatval($environment->total_hardware_warranty_savings);
        $environment->total_system_software_maintenance = $environment->total_system_software_maintenance_per_year * $environment->project->support_years;

        //If it's an existing environment, we exclude any license/hardware purchase costs.

        $mCost = round($environment->metered_cost, 2);

        $mCost = $mCost > 0 ? CurrencyConverter::convertAndFormat($mCost, null, 2) : '0';

        $environment->power_cost_formula = $environment->total_power . 'kW * ' . $mCost .
            ' kWH * 24 hrs/day * 30 days/mo * 12 mo/yr * ' .
            $environment->project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($environment->compute_power_cost, null, 2);

        $environment->storage_power_cost_formula = $environment->driveType . ' Power/Cooling Cost = Power ((' . $mCost . ' * ' . $environment->pCost . ' kWh/yr/' . $environment->driveSize . 'GB drive) + ' .
            ' Cooling (' . $mCost . ' * ' . $environment->cCost . ' kWh/yr/' . $environment->driveSize . 'GB drive)) * ' .
            $environment->dFactor . ' TB drive factor * ' . $environment->raw_storage . 'TB * ' . $environment->project->support_years .
            ' years = ' . CurrencyConverter::convertAndFormat(round($environment->storage_power_cost));

        if ($environment->isTreatAsExisting()) {
            $this->calculateExistingCosts($environment);
        } else {
            $this->calculateTargetCosts($environment, $existingEnvironment);
        }

        return $this;
    }

    /**
     * Calculate costs specific to existing environments
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateExistingCosts(Environment $existingEnvironment)
    {
        switch($existingEnvironment->getEnvironmentType()->name) {
            case Environment::ENVIRONMENT_TYPE_CLOUD:
                // do nothing
                // currently NewVsNew Cloud has multiple issues and doesn't function
                break;
            default:
                $existingEnvironment->total_storage_maintenance = $existingEnvironment->storage_maintenance * $existingEnvironment->project->support_years;
                $existingEnvironment->storage_purchase_price = $existingEnvironment->isExisting() ? 0 : $existingEnvironment->storage_purchase;
                $existingEnvironment->total_storage = $existingEnvironment->useable_storage;
                break;
        }

        return $this;
    }

    /**
     * Calculate costs specific to target environments
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateTargetCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        switch($targetEnvironment->getEnvironmentType()->name) {
            case Environment::ENVIRONMENT_TYPE_CLOUD:
                // do nothing
                // cloud doesn't have storage costs in the same way (it's all calculated)
                break;
            case Environment::ENVIRONMENT_TYPE_COMPUTE:
                // Compute just uses the existing environment
                $targetEnvironment->storage_maintenance = $existingEnvironment->storage_maintenance;
                $targetEnvironment->total_storage_maintenance = $existingEnvironment->total_storage_maintenance;
                $targetEnvironment->total_storage = $existingEnvironment->total_storage;
                $targetEnvironment->useable_storage = $existingEnvironment->useable_storage;
                $targetEnvironment->storage_purchase_price = 0;
                break;
            default:
                // converged & compute + storage both have storage on their environments
                $targetEnvironment->total_storage = $targetEnvironment->useable_storage;
                $targetEnvironment->storage_maintenance = $targetEnvironment->storage_maintenance ?: 0;
                $targetEnvironment->storage_purchase = $targetEnvironment->storage_purchase ?: 0;
                $targetEnvironment->total_storage_maintenance = $targetEnvironment->storage_maintenance * $targetEnvironment->project->support_years;
                $targetEnvironment->storage_purchase_price = $targetEnvironment->storage_purchase;
                break;
        }

        return $this;
    }
}