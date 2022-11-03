<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Environment\Cloud\Instance;


use App\Models\Hardware\AmazonServer;
use App\Models\Project\Environment;

class LowestCostCalculator
{
    /**
     * @param $consolidationMap
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateLowestCosts(&$consolidationMap, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        list (
            $onDemandPurchase,
            $onDemandMaintenance,
            $upfrontPurchase,
            $upfront3Purchase,
            $upfrontMaintenance,
            $totalCostOnDemand,
            $totalCostUpfront,
            $totalCostUpfront3
        ) = $this->totalTargetCosts(
            $consolidationMap,
            $targetEnvironment,
            $existingEnvironment
        );

        $targetEnvironment->onDemandPurchase = $onDemandPurchase;
        $targetEnvironment->onDemandMaintenance = $onDemandMaintenance;
        $targetEnvironment->upfrontPurchase = $upfrontPurchase;
        $targetEnvironment->upfront3Purchase = $upfront3Purchase;
        $targetEnvironment->upfrontMaintenance = $upfrontMaintenance;
        // System Software is always 0.00 for Cloud
        $targetEnvironment->total_system_software_maintenance_per_year = 0.00;
        $targetEnvironment->total_system_software_maintenance = 0.00;

        if (!$this->cloudHelper()->environmentIsAzure($targetEnvironment) && 
            !$this->cloudHelper()->environmentIsGoogle($targetEnvironment) && 
            !$this->cloudHelper()->environmentIsIBMPVS($targetEnvironment)) {
            if (($totalCostOnDemand < $totalCostUpfront && $totalCostOnDemand !== null) || $totalCostUpfront === null) {
                $targetEnvironment->purchase_price = $onDemandPurchase;
                $targetEnvironment->total_hardware_maintenance = $onDemandMaintenance;
                $targetEnvironment->total_hardware_maintenance_per_year = $onDemandMaintenance / $targetEnvironment->project->support_years;
                $targetEnvironment->lowest_price = "On Demand";
            } else {
                $targetEnvironment->purchase_price = $upfrontPurchase;
                $targetEnvironment->total_hardware_maintenance = $upfrontMaintenance;
                $targetEnvironment->total_hardware_maintenance_per_year = $upfrontMaintenance / $targetEnvironment->project->support_years;
                $targetEnvironment->lowest_price = "Partial Upfront";
            }

            if ($targetEnvironment->cloud_support_costs !== Environment::CLOUD_SUPPORT_COSTS_DEFAULT) {
                $targetEnvironment->total_hardware_maintenance = $targetEnvironment->custom_cloud_support_cost * $targetEnvironment->project->support_years;
                $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->custom_cloud_support_cost;
            }
        } else if (!$this->cloudHelper()->environmentIsGoogle($targetEnvironment) && !$this->cloudHelper()->environmentIsIBMPVS($targetEnvironment)) {
            if ((($totalCostOnDemand < $totalCostUpfront && $totalCostOnDemand !== null) || $totalCostUpfront === null) &&
                (($totalCostOnDemand < $totalCostUpfront3 && $totalCostOnDemand !== null) || $totalCostUpfront3 === null)) {
                $targetEnvironment->purchase_price = $onDemandPurchase;
                $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->getCloudSupportCostPerYear();
                $targetEnvironment->total_hardware_maintenance = $targetEnvironment->total_hardware_maintenance_per_year * $targetEnvironment->project->support_years;
                $targetEnvironment->lowest_price = "On Demand";
            } else if ((($totalCostUpfront < $totalCostOnDemand && $totalCostUpfront !== null) || $totalCostOnDemand === null) &&
                (($totalCostUpfront < $totalCostUpfront3 && $totalCostUpfront !== null) || $totalCostUpfront3 === null)) {
                $targetEnvironment->purchase_price = $upfrontPurchase;
                $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->getCloudSupportCostPerYear();
                $targetEnvironment->total_hardware_maintenance = $targetEnvironment->total_hardware_maintenance_per_year * $targetEnvironment->project->support_years;
                $targetEnvironment->lowest_price = "Partial Upfront";
            } else if ((($totalCostUpfront3 < $totalCostOnDemand && $totalCostUpfront3 !== null) || $totalCostOnDemand === null) &&
                (($totalCostUpfront3 < $totalCostUpfront && $totalCostUpfront3 !== null) || $totalCostUpfront === null)) {
                $targetEnvironment->purchase_price = $upfront3Purchase;
                $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->getCloudSupportCostPerYear();
                $targetEnvironment->total_hardware_maintenance = $targetEnvironment->total_hardware_maintenance_per_year * $targetEnvironment->project->support_years;
                $targetEnvironment->lowest_price = "Partial Upfront 3";
            }
        } else if (!$this->cloudHelper()->environmentIsIBMPVS($targetEnvironment)) {
          if ((($totalCostOnDemand < $totalCostUpfront && $totalCostOnDemand !== null) || $totalCostUpfront === null) &&
              (($totalCostOnDemand < $totalCostUpfront3 && $totalCostOnDemand !== null) || $totalCostUpfront3 === null)) {
              $targetEnvironment->purchase_price = $onDemandPurchase;
              $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->getCloudSupportCostPerYear();
              $targetEnvironment->total_hardware_maintenance = $targetEnvironment->total_hardware_maintenance_per_year * $targetEnvironment->project->support_years;
              $targetEnvironment->lowest_price = "On Demand";
          } else if ((($totalCostUpfront < $totalCostOnDemand && $totalCostUpfront !== null) || $totalCostOnDemand === null) &&
              (($totalCostUpfront < $totalCostUpfront3 && $totalCostUpfront !== null) || $totalCostUpfront3 === null)) {
              $targetEnvironment->purchase_price = $upfrontPurchase;
              $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->getCloudSupportCostPerYear();
              $targetEnvironment->total_hardware_maintenance = $targetEnvironment->total_hardware_maintenance_per_year * $targetEnvironment->project->support_years;
              $targetEnvironment->lowest_price = "Partial Upfront";
          } else if ((($totalCostUpfront3 < $totalCostOnDemand && $totalCostUpfront3 !== null) || $totalCostOnDemand === null) &&
              (($totalCostUpfront3 < $totalCostUpfront && $totalCostUpfront3 !== null) || $totalCostUpfront === null)) {
              $targetEnvironment->purchase_price = $upfront3Purchase;
              $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->getCloudSupportCostPerYear();
              $targetEnvironment->total_hardware_maintenance = $targetEnvironment->total_hardware_maintenance_per_year * $targetEnvironment->project->support_years;
              $targetEnvironment->lowest_price = "Partial Upfront 3";
          }
        } else if ($this->cloudHelper()->environmentIsIBMPVS($targetEnvironment)) {
          $targetEnvironment->purchase_price = $onDemandPurchase;
            $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->getCloudSupportCostPerYear();
            $targetEnvironment->total_hardware_maintenance = 0;
          $targetEnvironment->lowest_price = "On Demand";

            if ($targetEnvironment->cloud_support_costs === Environment::CLOUD_SUPPORT_COSTS_CUSTOM) {
                $targetEnvironment->total_hardware_maintenance_per_year = $targetEnvironment->custom_cloud_support_cost;
                $targetEnvironment->total_hardware_maintenance = $targetEnvironment->custom_cloud_support_cost * $targetEnvironment->project->support_years;
            }
        }

        return $this;
    }

    public function totalTargetCosts(&$consolidationMap, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $totalCostOnDemand = 0;
        $totalCostUpfront = 0;
        $totalCostUpfront3 = 0;
        $upfrontPurchase = 0;
        $upfront3Purchase = 0;
        $upfrontMaintenance = 0;
        $onDemandPurchase = 0;
        $onDemandMaintenance = 0;
        $totalInstances = 0;

        $targetEnvironment->cpu_architecture = null;
        $targetEnvironment->ghz = null;

        /** @var AmazonServer $server */
        foreach ($consolidationMap as &$server) {
            $totalInstances += $server->instances;
            if ($targetEnvironment->cpu_architecture == null) {
                $targetEnvironment->cpu_architecture = $server->physical_processor;
            } else if ($targetEnvironment->cpu_architecture != $server->physical_processor) {
                $targetEnvironment->cpu_architecture = "Various";
            }

            $ghz = preg_replace("/[^0-9.]/", "", $server->clock_speed);
            if ($targetEnvironment->ghz == null) {
                $targetEnvironment->ghz = $ghz;
            } else if ($targetEnvironment->ghz != $ghz) {
                $targetEnvironment->ghz = $ghz;
            }

            $paymentOption = $targetEnvironment->provider->name === AmazonServer::INSTANCE_TYPE_AZURE
                ? AmazonServer::getAzurePaymentOptionById($targetEnvironment->payment_option_id)
                : AmazonServer::getAmazonPaymentOptionById($targetEnvironment->payment_option_id);

            if ($server->upfrontHourly !== null || $server->upfront !== null) {
                $server->discountRate = $targetEnvironment->discount_rate ?  $targetEnvironment->discount_rate : 0;
                $upfrontDiscount = $server->upfront * ($server->discountRate/100);
                $hourlyDiscount = $server->upfrontHourly * ($server->discountRate/100);
                $server->calculatedUpfront = ($server->upfront - $upfrontDiscount) * $server->instances;
                $server->calculatedUpfrontPerYear = ($server->upfrontHourly - $hourlyDiscount) * 8760 * $server->instances;
                
                // If the payment option contract length is 3 years do not multiply by the support_years
                if ($paymentOption['lease_contract_length'] == 3) {
                    $server->calculatedUpfrontTotal = $server->calculatedUpfront + $server->calculatedUpfrontPerYear * 3;
                } else {
                    $server->calculatedUpfrontTotal = ($server->calculatedUpfront + $server->calculatedUpfrontPerYear) * $targetEnvironment->project->support_years;
                }

                $server->upfrontSupportTiers = $this->cloudHelper()->tieredCost($server->calculatedUpfrontTotal);
                $server->upfrontMonthlySupportTiers = $this->cloudHelper()->tieredCost($server->calculatedUpfrontPerYear / 12);
                $server->upfrontSupport = round(collect($server->upfrontSupportTiers)->sum());
                $server->upfrontMonthlySupport = round(collect($server->upfrontMonthlySupportTiers)->sum());
                $server->upfrontBusinessSupport = round($server->upfrontMonthlySupport * 12 * $targetEnvironment->project->support_years);
                $server->upfrontTotalSupport = round($server->upfrontSupport + $server->upfrontBusinessSupport);
                $server->totalCostUpfront = round($server->upfrontTotalSupport + $server->calculatedUpfrontTotal);

                $upfrontPurchase += $server->calculatedUpfrontTotal;
                $upfrontMaintenance += $server->upfrontTotalSupport;
                $totalCostUpfront += $server->upfrontTotalSupport + $server->calculatedUpfrontTotal;
            } else {
                $totalCostUpfront = null;
            }

            if ($server->onDemandHourly !== null) {
                $server->discountRate = $targetEnvironment->discount_rate ?  $targetEnvironment->discount_rate : 0;
                $discount = $server->onDemandHourly * ($server->discountRate/100);
                $server->onDemandPerYear = round(($server->onDemandHourly - $discount) * 8760 * ($targetEnvironment->max_utilization / 100.0) * $server->instances);
                $server->onDemandTotal = round($server->onDemandPerYear * $targetEnvironment->project->support_years);
                $server->onDemandPerMonth = round($server->onDemandPerYear / 12);

                // Azure/Google/IBM PVS don't have the tiered support costs
                if (!$this->cloudHelper()->environmentIsAzure($targetEnvironment) && 
                    !$this->cloudHelper()->environmentIsGoogle($targetEnvironment) &&
                    !$this->cloudHelper()->environmentIsIBMPVS($targetEnvironment)) {
                    $server->onDemandSupportTiers = $this->cloudHelper()->tieredCost($server->onDemandPerMonth);
                    $server->onDemandSupportPerMonth = round(collect($server->onDemandSupportTiers)->sum());
                    $server->onDemandSupportTotal = round($server->onDemandSupportPerMonth * 12 * $targetEnvironment->project->support_years);
                } else {
                    $server->onDemandSupportTotal = 0;
                }
                $server->totalCostOnDemand = round($server->onDemandSupportTotal + $server->onDemandTotal);

                $onDemandPurchase += $server->onDemandTotal;
                $onDemandMaintenance += $server->onDemandSupportTotal;
                $totalCostOnDemand += $server->onDemandSupportTotal + $server->onDemandTotal;
            } else {
                $totalCostOnDemand = null;
            }

            //Azure/Google has 2 reserved costs, 1 year and 3 year
            if (isset($server->upfront3Hourly) && $server->upfront3Hourly !== null) {
                $server->discountRate = $targetEnvironment->discount_rate ?  $targetEnvironment->discount_rate : 0;
                $discount = $server->upfront3Hourly * ($server->discountRate/100);
                $server->calculatedUpfront3PerYear = round(($server->upfront3Hourly - $discount) * 8760 * $server->instances);
                $server->calculatedUpfront3Total = round($server->calculatedUpfront3PerYear * $targetEnvironment->project->support_years);
                $server->totalCostUpfront3 = round($server->calculatedUpfront3Total);

                $upfront3Purchase += $server->calculatedUpfront3Total;
                $totalCostUpfront3 += $server->calculatedUpfront3Total;
            } else {
                $totalCostUpfront3 = null;
            }
        }

        return [$onDemandPurchase, $onDemandMaintenance, $upfrontPurchase, $upfront3Purchase, $upfrontMaintenance,
            $totalCostOnDemand, $totalCostUpfront, $totalCostUpfront3];
    }

    /**
     * @return \App\Helpers\Analysis\Cloud
     */
    public function cloudHelper()
    {
        return resolve(\App\Helpers\Analysis\Cloud::class);
    }
}