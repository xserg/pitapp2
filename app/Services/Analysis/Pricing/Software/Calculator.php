<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Software;


use App\Models\Hardware\Processor;
use App\Models\Project\Environment;
use App\Models\Software\Software;
use App\Models\Software\SoftwareCost;
use App\Models\Software\SoftwareType;
use App\Services\Analysis\PricingAccessTrait;
use App\Services\Currency\CurrencyConverter;

class Calculator
{
    use PricingAccessTrait;
    use MapAccessTrait;

    /**
     * The only purpose of this function is determine the license cost for the below funciton (supportCostPerYear)
     * @note ASB - 10/6/18
     * @param Software $software
     * @param Processor $processor
     * @param Environment|null $environment
     * @return float|int
     */
    public function licenseCost($software, $processor, Environment $environment = null)
    {
        if(!$software) {
            return 0;
        }

        $this->softwareMap()->setScope($software, $environment);

        $modifier = (object) ['license_cost_discount' => 0];
        foreach ($environment->softwareCosts as $sc) {
            if ($sc->software_type_id == $software->id) {
                $modifier->license_cost_discount = $sc->license_cost_modifier;
                break;
            }
        }
        //Otherwise, use the list price
        $license = $software->license_cost * ((100 - $modifier->license_cost_discount) / 100);
        $costPerMultiplier = 1;
        switch($software->cost_per) {
            case Software::COST_PER_NUP:
                $costPerMultiplier *= $software->nup;
                break;
            case Software::COST_PER_CORE:
                $costPerMultiplier *= $this->softwareMap()->getVmAggregateValue('physical_cores', $software->getCoreQty($processor));
                break;
            case Software::COST_PER_PROCESSOR:
                if(!$processor->isAWS) {
                    $costPerMultiplier *= $this->softwareMap()->getVmAggregateValue('physical_processors', $processor->socket_qty);
                }
                break;
            default:
                break;
        }

        $license *= $costPerMultiplier;
        if ($software->softwareType->isDatabaseOrMiddleware() && $software->multiplier != null) {
            $license *= $software->multiplier;
        }

        return $license;
    }

    /**
     * @param Software $software
     * @param $license_cost
     * @param Processor $processor
     * @param Environment $environment
     * @param $qty
     * @return float|int
     */
    public function supportCostPerYear($software, $license_cost, $processor, Environment $environment, $qty, $drive_qty = 0, $licensed_cores = 0)
    {
        if (!$software) {
            return 0;
        }

        $sc = null;

        $modifier = (object)['support_cost_discount' => 0];
        foreach ($environment->softwareCosts as $cost) {
            if ($cost->software_type_id == $software->id) {
                $sc = $cost;
                $modifier->support_cost_discount = $cost->support_cost_modifier;
                break;
            }
        }

        $this->softwareMap()->setScope($software, $environment);

        if (isset($environment->drive_qty)) {
            $this->softwareMap()->setData('drive_qty', $environment->drive_qty);
        } else {
            $this->softwareMap()->addData('drive_qty', $drive_qty);
        }


        // Support per vm core
        $coreQty = $software->getCoreQty($processor);

        // Based on percentage of license cost
        $this->softwareMap()->addData('cores',$this->softwareMap()->getVmAggregateValue('physical_cores', $coreQty * $qty));

        $this->softwareMap()->addData('licensed_cores', $licensed_cores * $qty);

        $this->softwareMap()->setData('core_unit', $software->ifVmArchitecture('VM cores', 'cores'));

        $this->softwareMap()->addData('processors',$this->softwareMap()->getVmAggregateValue('physical_processors', $processor->socket_qty * $qty));

        $this->softwareMap()->addData('servers', $qty);

        if ($software->support_type == 0) {
            $listPrice = round(($software->support_cost_percent / 100) * $license_cost);
            $perYear = (($software->support_cost_percent / 100) * $license_cost) * ((100 - $modifier->support_cost_discount) / 100);
            $this->softwareMap()->addData('totalCost', $listPrice * $qty);
        } else {
            $formulaCost = $software->support_cost;
            $software->calculatedFormula = "(" . CurrencyConverter::convertAndFormat($formulaCost) . ' * ' . $modifier->support_cost_discount . '% discount';

            $formulaCost = round($software->support_cost);
            $software->formula = "(" . CurrencyConverter::convertAndFormat($formulaCost) . " * discount";
            //Otherwise, use the list price
            $perYear = $software->support_cost * ((100 - $modifier->support_cost_discount) / 100);
            $costPerMultiplier = 1;
            switch ($software->annual_cost_per) {
                case Software::COST_PER_NUP:
                    $costPerMultiplier *= $software->nup;
                    break;
                case Software::COST_PER_CORE:
                    $costPerMultiplier *= $this->softwareMap()->getVmAggregateValue('physical_cores', $coreQty);
                    break;
                case Software::COST_PER_PROCESSOR:
                    if (!$processor->isAWS) {
                        $costPerMultiplier *= $this->softwareMap()->getVmAggregateValue('physical_processors', $processor->socket_qty);
                    }
                    break;
                default:
                    break;
            }

            $perYear *= $costPerMultiplier;
            if ($software->softwareType->isDatabaseOrMiddleware() && $software->support_multiplier != null) {
                $perYear *= $software->support_multiplier;
            }
        }

        $perYear *= $qty;
        return $perYear;
    }

    /**
     * @param Software $software
     * @param Environment $environment
     * @return bool
     */
    public function isInEnvironment($software, $environment)
    {
        if(!$environment || !$software) {
            return true;
        }

        foreach($environment->softwareCosts as $cost) {
            if($cost->software->name == $software->name) {
                return true;
            }
        }

        return false;
    }
}
