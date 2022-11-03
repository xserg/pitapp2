<?php

namespace App\Services\OptimalTarget;

use App\Models\Hardware\OptimalTarget;
use App\Models\Hardware\ServerConfiguration;
use Exception;


/**
 * TODO: We can make an algorithm more efficient if we can figure out which variable to optimize on then find the target
 *   with the best variable to cost ratio.  But also need to know how many of those servers we would need... Goal is
 *   to be able to loop over the targets once and the servers once and know which target.  Maybe sorted arrays for each
 *   variable... idk
 *   Should be able to get a rough estimate which is best by totalling CPM and RAM and dividing it out for an average.
 *   Find best candidates from there (if within X servers or something, use it as a test). Can we use mean median mode
 *   of the server CPM/RAM to figure out best option?
 *
 * Class BruteForceAlgorithm
 * @package App\Services\OptimalTarget
 */
class BruteForceAlgorithm extends OptimalTargetAlgorithm
{
    private $dataLoader;
    private $i;

    public function __construct($dataLoader = null)
    {
        if ($dataLoader == null) {
            $dataLoader = function (array $processorModelsConstraint = []) {
                $query = OptimalTarget::with(["processor", "processor.manufacturer"]);
                
                if ($processorModelsConstraint) {
                    $query = $query->whereIn('processor_model', $processorModelsConstraint);
                }

                return $query->get();
            };
        }
        
        $this->dataLoader = $dataLoader;
    }

    /**
     * @param ServerConfiguration[] $existingServers
     * @param OptimalTargetConfiguration $optimalTargetConfiguration
     * @return OptimalTarget
     * @throws Exception
     */
    protected function determineOptimalTarget(array $existingServers, OptimalTargetConfiguration $optimalTargetConfiguration): OptimalTarget
    {
        // Load the OptimalTargets
        $dataLoader = $this->dataLoader;
        /** @var OptimalTarget[] $optimalTargets */
        $optimalTargets = $dataLoader($optimalTargetConfiguration->processor_models_constraint);

        $minCost = PHP_FLOAT_MAX;
        $optimal = null;
        $this->i = 0;
        foreach ($optimalTargets as $target) {
            if ($target->processor->manufacturer->name == $optimalTargetConfiguration->manufacturer) {
                $cost = $this->computeCost($existingServers, $optimalTargetConfiguration, $target);

                if ($cost < $minCost) {
                    $minCost = $cost;
                    $optimal = $target;
                }
            }
        }

        //* mess with JSON.parse in front-end code
        // echo "\nWent through " . $this->i . " servers...\n";        

        if ($optimal == null) {
            throw new Exception("No optimal target found!");
        }
        return $optimal;
    }

    /**
     * @param ServerConfiguration[] $existingServers
     * @param OptimalTargetConfiguration $optimalTargetConfiguration
     * @param OptimalTarget $target
     * @return float
     * @throws Exception
     */
    protected function computeCost(array $existingServers, OptimalTargetConfiguration $optimalTargetConfiguration, OptimalTarget $target): float {
        // Simplified consolidation algorithm and cost computation algorithm
        $targetServerCount = 0;

        $targetMaxRam = $target->ram * ($optimalTargetConfiguration->ram_utilization / 100);
        $targetMaxCpm = $target->cpm_value() * ($optimalTargetConfiguration->cpu_utilization / 100);

        $currRamCapacity = PHP_INT_MAX;
        $currCpmCapacity = PHP_INT_MAX;

        foreach ($existingServers as $serverConfig) {
            for ($i = 0; $i < $serverConfig->qty; $i += 1) {
                // TODO: cagrMult?
                $serverRam = $serverConfig->ram * ($serverConfig->ram_utilization / 100.0);
                $serverCpm = $serverConfig->processor->rpm * ($serverConfig->cpu_utilization / 100.0);

                if ($serverRam > $targetMaxRam || $serverCpm > $targetMaxCpm) {
                    // Requires multiple target servers to replace a single existing server
                    $targetServerCount += max(ceil($serverRam / $targetMaxRam), ceil($serverCpm / $targetMaxCpm));
                } else {
                    if ($serverRam + $currRamCapacity > $targetMaxRam || $serverCpm + $currCpmCapacity > $targetMaxCpm) {
                        $targetServerCount += 1;
                        $currRamCapacity = 0;
                        $currCpmCapacity = 0;
                    }

                    $currRamCapacity += $serverRam;
                    $currCpmCapacity += $serverCpm;
                }
                $this->i += 1;
            }
        }

        return $targetServerCount * $this->computeOptimalTargetCost($optimalTargetConfiguration, $target);
    }
}
