<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Software;


use App\Models\Hardware\Processor;
use App\Models\Project\Environment;
use App\Models\Software\FeatureCost;
use App\Models\Software\Software;
use App\Models\Software\SoftwareCost;
use App\Models\Software\SoftwareType;
use App\Services\Currency\CurrencyConverter;

class FeatureCalculator
{
    use MapAccessTrait;

    /**
     * @param Software $software
     * @param Processor $processor
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return int
     */
    public function licenseFeaturesCost($software, $processor, Environment $environment, Environment $existingEnvironment = null)
    {
        if(!$software) {
            return 0;
        }

        $softwareCost = null;
        $existingCost = null;

        foreach($environment->softwareCosts as $sc) {
            if($sc->software_type_id == $software->id) {
                $softwareCost = $sc;
                break;
            }
        }
        if($existingEnvironment) {
            foreach($existingEnvironment->softwareCosts as $sc) {
                if($sc->software_type_id == $software->id) {
                    $existingCost = $sc;
                    break;
                }
            }
        }
        $totalCost = 0;
        if($softwareCost) {
            foreach($softwareCost->featureCosts as $fc) {
                //Check if the feature is already in the software cost
                if(!$this->isFeatureInSoftwareCost($fc->feature->name, $existingCost)) {
                    $temp = $this->licenseFeatureCost($fc, $processor);
                    $totalCost += $temp;
                }
            }
        }
        return $totalCost;
    }

    /**
     * @param Software $software
     * @param Processor $processor
     * @param Environment $environment
     * @return int
     */
    public function supportFeaturesCostPerYear($software, $processor, Environment $environment)
    {
        if(!$software) {
            return 0;
        }

        $softwareCost = null;
        foreach($environment->softwareCosts as $sc) {
            if($sc->software_type_id == $software->id) {
                $softwareCost = $sc;
                break;
            }
        }
        $totalCost = 0;
        if($softwareCost) {
            foreach($softwareCost->featureCosts as $fc) {
                $totalCost += $this->featureSupportCostPerYer($fc, $this->licenseFeatureCost($fc, $processor), $processor);
            }
        }
        return $totalCost;
    }

    /**
     * @param $featureName
     * @param SoftwareCost $softwareCost
     * @return bool
     */
    public function isFeatureInSoftwareCost($featureName, $softwareCost) {
        if($softwareCost == null) {
            return false;
        }

        foreach($softwareCost->featureCosts as $fc) {
            if($featureName == $fc->feature->name) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param FeatureCost $featureCost
     * @param Processor $processor
     * @return float|int
     */
    public function licenseFeatureCost($featureCost, $processor)
    {
        $license = $featureCost->feature->license_cost * ((100 - $featureCost->license_cost_discount) / 100);
        $costPerMultiplier = 1;
        $feature = $featureCost->feature;
        switch($feature->cost_per) {
            case Software::COST_PER_NUP:
                $costPerMultiplier *= $featureCost->feature->nup;
                break;
            case Software::COST_PER_CORE:
                $costPerMultiplier *= $this->softwareMap()->getVmAggregateValue('physical_cores', $feature->isVMArchitecture() ? $processor->getVmInfo('cores') : $processor->core_qty);
                break;
            case Software::COST_PER_PROCESSOR:
                if(!$processor->isAWS) {
                    $costPerMultiplier *= $this->softwareMap()->getVmAggregateValue('physical_processors', $processor->socket_qty);
                }
                break;
            default:
                break;
        }

        if(in_array($featureCost->softwareCost->software->softwareType->name, [SoftwareType::NAME_DATABASE, SoftwareType::NAME_MIDDLEWARE]) && $featureCost->feature->multiplier != null) {
            $costPerMultiplier *= $featureCost->feature->multiplier;
        }

        $license *= $costPerMultiplier;
        return $license;
    }

    /**
     * @param FeatureCost $featureCost
     * @param $license_cost
     * @param Processor $processor
     * @return float|int
     */
    public function featureSupportCostPerYer($featureCost, $license_cost, $processor)
    {
        if($featureCost->feature->support_type == 0) {
            $formulaCost = round(($featureCost->feature->support_cost_percent / 100) * $license_cost);
            $featureCost->formula = CurrencyConverter::convertAndFormat($formulaCost) . ' * ' . $featureCost->support_cost_discount . '% discount';
            $featureCost->formula .= " * " . $featureCost->softwareCost->environment->project->support_years . ' years';
            $featureCost->feature->formula = round($featureCost->feature->support_cost_percent) . "% of license net cost * discount * # of years";
            $perYear = (($featureCost->feature->support_cost_percent / 100) * $license_cost) * ((100 - $featureCost->support_cost_discount) / 100);
        } else {
            $formulaCost = $featureCost->feature->support_cost;
            $featureCost->formula = "(" . CurrencyConverter::convertAndFormat($formulaCost) . ' * ' . $featureCost->support_cost_discount . '% discount';

            $formulaCost = round($featureCost->feature->support_cost);
            $featureCost->feature->formula = CurrencyConverter::convertAndFormat($formulaCost) . " * discount";
            //Otherwise, use the list price
            $perYear = $featureCost->feature->support_cost * ((100 - $featureCost->support_cost_discount) / 100);
            $costPerMultiplier = 1;
            $feature = $featureCost->feature;
            switch($featureCost->feature->annual_cost_per) {
                case Software::COST_PER_NUP:
                    $featureCost->formula .= " * " . number_format($featureCost->feature->nup) . " NUP";
                    $featureCost->feature->formula .= " * NUP/core";
                    $costPerMultiplier *= $featureCost->feature->nup;
                    break;
                case Software::COST_PER_CORE:
                    if ($this->softwareMap()->isVmAggregate()) {
                        $featureCost->formula .= $this->softwareMap()->getData('cores') . ' cores'.
                        $featureCost->feature->formula .= " * # cores";
                        $costPerMultiplier *= $this->softwareMap()->getData('cores');
                    } else {
                        $featureCost->formula .= ' * ' . $feature->ifVmArchitecture($feature->getCoreQty($processor) . ' VM cores', number_format($processor->core_qty) . " cores/processor");
                        $featureCost->feature->formula .= " * # " . $feature->ifVmArchitecture("VM cores", "cores/processor");
                        $costPerMultiplier *= $feature->getCoreQty($processor);
                    }
                    break;
                case Software::COST_PER_PROCESSOR:
                    if ($this->softwareMap()->isVmAggregate()) {
                        $featureCost->formula .= " * " . $this->softwareMap()->getData('processors') . ' processors';
                        $featureCost->feature->formula .= " * # of processors";
                        $costPerMultiplier *= $this->softwareMap()->getData('processors');
                    } else {
                        $featureCost->formula .= " * " . number_format($processor->socket_qty) . " processors/server";
                        $featureCost->feature->formula .= " * # of processors/server";
                        $costPerMultiplier *= $processor->socket_qty;
                    }
                    break;
                default:
                    break;
            }

            if ($featureCost->softwareCost->software->softwareType->isDatabaseOrMiddleware() && $featureCost->feature->support_multiplier != null) {
                $costPerMultiplier *= $featureCost->feature->support_multiplier;
                $featureCost->formula .= " * " . $featureCost->feature->support_multiplier;
                $featureCost->feature->formula .= " * core multiplier or PVU";
            }

            $featureCost->formula .= " * " . $featureCost->softwareCost->environment->project->support_years . ' years';
            $featureCost->feature->formula .= " * # of years";
            $perYear *= $costPerMultiplier;
        }

        return $perYear;
    }
}