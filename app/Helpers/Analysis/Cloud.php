<?php
/**
 *
 */

namespace App\Helpers\Analysis;


use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\AzureAds;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;

class Cloud
{
    /**
     * @param Environment $environment
     * @return bool
     */
    public function environmentHasRds(Environment $environment)
    {
        if (!isset($environment->analysis) || !isset($environment->analysis->consolidations)) {
            return false;
        }

        foreach($environment->analysis->consolidations as $consolidation) {
            /** @var false|\stdClass $firstTarget */
            $firstTarget = $consolidation->targets[0] ?? false;
            if ($firstTarget && $firstTarget->instance_type == AmazonServer::INSTANCE_TYPE_RDS) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Environment|\stdClass $environment
     * @return bool
     */
    public function environmentHasAds($environment)
    {
        if (!isset($environment->analysis) || !isset($environment->analysis->consolidations)) {
            return false;
        }

        foreach($environment->analysis->consolidations as $consolidation) {
            /** @var false|\stdClass $firstTarget */
            $firstTarget = $consolidation->targets[0] ?? false;
            if ($firstTarget && $firstTarget->instance_type == AzureAds::INSTANCE_TYPE_ADS) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Environment $environment
     * @return bool|mixed
     */
    public function getEnvironmentRdsDeploymentOption(Environment $environment)
    {
        if (!isset($environment->analysis) || !isset($environment->analysis->consolidations)) {
            return false;
        }

        foreach($environment->analysis->consolidations as $consolidation) {
            /** @var false|\stdClass $firstTarget */
            $firstTarget = $consolidation->targets[0] ?? false;
            if ($firstTarget && $firstTarget->instance_type == AmazonServer::INSTANCE_TYPE_RDS) {
                return $firstTarget->deployment_option;
            }
        }

        return false;
    }

    /**
     * @param Environment $environment
     * @return bool|mixed
     */
    public function getEnvironmentAdsInstance(Environment $environment)
    {
        if (!isset($environment->analysis) || !isset($environment->analysis->consolidations)) {
            return false;
        }

        foreach($environment->analysis->consolidations as $consolidation) {
            /** @var false|\stdClass $firstTarget */
            $firstTarget = $consolidation->targets[0] ?? false;
            if ($firstTarget && $firstTarget->instance_type == AzureAds::INSTANCE_TYPE_ADS) {
                return $firstTarget;
            }
        }

        return false;
    }

    /**
     * @param Environment $environment
     * @return bool
     */
    public function environmentIsAzure(Environment $environment)
    {
        return $environment->isAzure();
    }
    
    /**
     * @param Environment $environment
     * @return bool
     */
    public function environmentIsGoogle(Environment $environment)
    {
        return $environment->isGoogle();
    }
    
    /**
     * @param Environment $environment
     * @return bool
     */
    public function environmentIsIBMPVS(Environment $environment)
    {
        return $environment->isIBMPVS();
    }

    /**
     * @param $cost
     * @return array
     */
    public function tieredCost($cost)
    {
        $tieredCosts = [];
        //10% of 0-10k
        if ($cost < 10000) {
            $c = round(.1 * $cost);
            $tieredCosts[] = $c;
            return $tieredCosts;
        } else {
            $tieredCosts[] = 1000; //10% of 10000
        }

        $cost -= 10000;
        //7% of 10k-80k
        if ($cost < 70000) {
            $c = round(.07 * $cost);
            $tieredCosts[] = $c;
            return $tieredCosts;
        } else {
            $tieredCosts[] = 4900; //7% of 70000
        }

        $cost -= 70000;
        //5% of 80k-250k
        if ($cost < 170000) {
            $c = round(.05 * $cost);
            $tieredCosts[] = $c;
            return $tieredCosts;
        } else {
            $tieredCosts[] = 8500; //5% of 170000
        }

        $cost -= 170000;
        //3% of anything over 250k
        $c = round(.03 * $cost);
        $tieredCosts[] = $c;
        return $tieredCosts;
    }

    /**
     * @param $instance
     * @return float
     */
    public function getAdsStorageCostPerUnit($instance)
    {
        if (($instance->category ?? AzureAds::CATEGORY_GENERAL_PURPOSE) == AzureAds::CATEGORY_BUSINESS_CRITICAL) {
            return AzureAds::STORAGE_COST_PER_GB_BUSINESS_CRITICAL;
        }

        return AzureAds::STORAGE_COST_PER_GB_GENERAL_PURPOSE;
    }
}