<?php

namespace App\Services\OptimalTarget;


use App\Models\Hardware\OptimalTarget;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Models\Software\Software;
use Exception;

abstract class OptimalTargetAlgorithm
{
    /**
     * Given a collection of servers and target config, find the optimal target
     *
     * @param ServerConfiguration[] $existingServers the group of servers to compute the optimal target for
     * @param OptimalTargetConfiguration $optimalTargetConfiguration the target configuration
     * @return OptimalTarget
     * @throws Exception
     */
    protected abstract function determineOptimalTarget(array $existingServers, OptimalTargetConfiguration $optimalTargetConfiguration): OptimalTarget;


    /**
     * Given an existing environment, return a ServerConfiguration for the
     *  optimal target environment from the optimal target database
     *
     * @param Environment $existingEnvironment the existing environment
     * @param OptimalTargetConfiguration $optimalTargetConfiguration the target configuration
     * @return ServerConfiguration
     * @throws Exception
     */
    public function computeOptimalServerConfiguration(Environment $existingEnvironment, OptimalTargetConfiguration $optimalTargetConfiguration): OptimalTarget
    {
        // 1. Find all ServerConfigurations that match the optimalTargetConfiguration
        $matchingServers = [];
        foreach ($existingEnvironment->serverConfigurations as /* @var $serverConfiguration ServerConfiguration */ $serverConfiguration) {
            if (
                $serverConfiguration->location == $optimalTargetConfiguration->location &&
                $serverConfiguration->environment_name == $optimalTargetConfiguration->environment_name &&
                $serverConfiguration->environment_detail == $optimalTargetConfiguration->environment_detail &&
                $serverConfiguration->workload_type == $optimalTargetConfiguration->workload_type
            ) {
                if ($existingEnvironment->getExistingEnvironmentType() == Environment::EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM && $serverConfiguration->type == ServerConfiguration::TYPE_PHYSICAL) {
                    // Skip over physical if handling Physical/VM
                    continue;
                } else {
                    if ($serverConfiguration->type == ServerConfiguration::TYPE_VM) {
                        $serverConfiguration->copyPhysicalAttributes();
                    }
                    array_push($matchingServers, $serverConfiguration);
                }
            }
        }

        if (count($matchingServers) == 0) {
            throw new Exception("No servers found in existing environment that match the target configuration");
        }

        return $this->determineOptimalTarget($matchingServers, $optimalTargetConfiguration);
    }

    /**
     * @param OptimalTargetConfiguration $optimalTargetConfiguration
     * @param OptimalTarget $target
     * @return float
     */
    protected function computeOptimalTargetCost(OptimalTargetConfiguration $optimalTargetConfiguration, OptimalTarget $target): float {
        $costOfSoftwareFunction = function (Software $software = null) use ($target) {
            if ($software == null) {
                return 0;
            }

            // This is modified from Calculator::licenseCost
            $costPerMultiplier = 1;
            switch($software->cost_per) {
                case Software::COST_PER_NUP:
                    $costPerMultiplier *= $software->nup;
                    break;
                case Software::COST_PER_CORE:
                    $costPerMultiplier *= $target->total_cores();
                    break;
                case Software::COST_PER_PROCESSOR:
                    $costPerMultiplier *= $target->num_of_processors();
                    break;
                default:
                    break;
            }

            $license = $software->license_cost * $costPerMultiplier;
            $support = $software->support_cost * $costPerMultiplier;

            if ($software->softwareType->isDatabaseOrMiddleware() && $software->multiplier != null) {
                $license *= $software->multiplier;
                $support *= $software->multiplier;
            }

            return $license + $support;
        };

        $softwareCost = 0;
        $softwareCost += $costOfSoftwareFunction($optimalTargetConfiguration->os);
        $softwareCost += $costOfSoftwareFunction($optimalTargetConfiguration->database);
        $softwareCost += $costOfSoftwareFunction($optimalTargetConfiguration->middleware);
        $softwareCost += $costOfSoftwareFunction($optimalTargetConfiguration->hypervisor);

        return $target->total_server_cost + $softwareCost;
    }
}
