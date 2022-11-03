<?php
/**
 *
 */

namespace App\Services\Consolidation\DefaultConsolidator;


use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Services\Consolidation\HybridConsolidatorTrait;
use Illuminate\Support\Collection;

class HybridDefaultConsolidator extends AbstractDefaultConsolidator
{
    use HybridConsolidatorTrait {
        _totalConsolidation as _traitTotalConsolidation;
    }

    /**
     * @param ServerConfiguration $existingServer
     * @param ServerConfiguration|\stdClass $targetConfig
     * @param Environment $targetEnvironment
     * @return ServerConfiguration
     */
    protected function _getComparisonServer($existingServer, $targetConfig, Environment $targetEnvironment)
    {
        $comparisonServer = clone $existingServer;
        $comparisonServer->id = null;

        // If the targetConfig is converged, we may have gotten passed a \stdClass instead of ServerConfiguration
        $targetConfig = $this->_getTargetServerConfiguration($targetConfig);

        /*
         * Below formula is:
         *
         *  Target CPM per Core = Target CPM @ utilization / Target Num Cores
         *  Required CPM = CPM of VM @ utilization
         *  Exact Target Cores Required = Required CPM / Target CPM Per Core
         *  Real Target Cores Required = Round Up (Exact Target Cores Required) * Target CPM Per Core
         *  Real CPM = Real Target Cores Required * Target Core Per CPM
         */
        list($realComputedCpm, $realComputedCores) = $this->_getComparisonCpmAndCores($comparisonServer, $targetConfig, 'computedRpm', $targetEnvironment);
        list($realBaseCpm, $realBaseCores) = $this->_getComparisonCpmAndCores($comparisonServer, $targetConfig, 'baseRpm', $targetEnvironment);

        // Below values represent a "resize" of the VM on the new server
        // Essentially, we may be able to get by with fewer cores because
        // each core is capable of doing more "work"
        // It's confusing at first glance because we're setting the values on the
        // comparison server, and not on the target.
        // This is because the target represents the physical / host server
        // while teh comparison server represents the VM resized onto the target
        $comparisonServer->vm_cores = $realComputedCores;
        $comparisonServer->computedRpm = $realComputedCpm;


        // We also need to get the above based on base CPM
        $comparisonServer->baseRpm = $realBaseCpm;

        // The comparison server resulted in a "resize"
        // We need to ensure that resize data is appended to output for the frontend
        // So it can be read / processed by the view
        $existingServer->setComparisonServer($comparisonServer)
            ->append('comparison_server');

        return $comparisonServer;
    }

    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @param $envCpuUtilization
     * @param $envRamUtilization
     * @param $remainingStorage
     * @param $totalStorage
     * @param $totalIops
     * @param $realTotal
     * @param $realTotalIops
     * @return object
     */
    public function getTotals(Environment $existingEnvironment, Environment $targetEnvironment, $envCpuUtilization, $envRamUtilization, $remainingStorage, $totalStorage, $totalIops, $realTotal, $realTotalIops)
    {
        $totals = parent::getTotals($existingEnvironment, $targetEnvironment, $envCpuUtilization, $envRamUtilization, $remainingStorage, $totalStorage, $totalIops, $realTotal, $realTotalIops);

        $totals->existing->vms = 0;
        $totals->target->vms = 0;
        $totals->existing->vm_cores = 0;

        return $totals;
    }

    /**
     * @param $consolidations
     * @param $totals
     * @param $existingServerChunks
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function totalConsolidations(&$consolidations, &$totals, $existingServerChunks, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        /** @var ServerConfiguration[] $existingServers */
        foreach ($existingServerChunks as $existingServers) {
            /** @var ServerConfiguration $serverConfiguration */
            foreach ($existingServers as $serverConfiguration) {
                if ($serverConfiguration->isVm()) {
                    continue;
                }
                $totals->existing->socket_qty += $this->consolidationHelper()->sumSockets($serverConfiguration->processor->socket_qty);
                $totals->existing->servers++;
            }
        }

        return parent::totalConsolidations($consolidations, $totals, $existingServerChunks, $targetEnvironment, $existingEnvironment);
    }

    /**
     * @param $consolidation
     * @param $server
     * @return $this
     */
    protected function _totalExistingConsolidation($consolidation, ServerConfiguration $server)
    {
        $consolidation->comparisonCores += $server->getComparisonServer()->vm_cores;
        return $this;
    }

    /**
     * @param ServerConfiguration $comparisonServer
     * @param ServerConfiguration $targetConfig
     * @param $field
     * @param Environment $targetEnvironment
     * @return array
     */
    protected function _getComparisonCpmAndCores(ServerConfiguration $comparisonServer, ServerConfiguration $targetConfig, $field, Environment $targetEnvironment)
    {
        $targetCpmPerCore = $targetConfig->baseRpm / $targetConfig->getTotalCores();

        if ($targetConfig->isHyperThreadingSupported()) {
            $targetCpmPerCore *= .5;
        }

        $requiredCpm = $comparisonServer->{$field};
        $exactComparisonCores = $requiredCpm / $targetCpmPerCore;

        if ($targetConfig->isPartialCoresSupported() && $targetConfig->manufacturer->name !== "IBM") {
            // Some manufacturers like IBM, Fujitsu, and Oracle
            // Allow partial cores. We are supporting to 2 decimal places
            $realComparisonCores = round($exactComparisonCores,2);
        } elseif ($targetConfig->manufacturer->name == "IBM") {
            // IBM only supports .05 increments
            $realComparisonCores = ceil($exactComparisonCores * 1000 / 50 ) * 50 / 1000;
        } else {
            $realComparisonCores = ceil($exactComparisonCores);
        }
        $realCpm = $realComparisonCores * $targetCpmPerCore;

        return [$realCpm, $realComparisonCores];
    }

    /**
     * @param $totals
     * @param $server
     * @return $this
     */
    protected function _totalTargetServer(&$totals, &$server)
    {
        return $this;
    }

    /**
     * @param $target
     * @return mixed
     */
    protected function _getTotalCores($target)
    {
        return 0;
    }

    /**
     * @param $target
     * @return mixed
     */
    protected function _getPhysicalCores($target)
    {
        return $target->processor->total_cores;
    }

    /**
     * Get a ServerConfiguraiton instance to work with.
     * @param ServerConfiguration|\stdClass $targetConfig
     * @return ServerConfiguration
     */
    protected function _getTargetServerConfiguration($targetConfig)
    {
        if ($targetConfig instanceof ServerConfiguration) {
            return $targetConfig;
        }

        $serverConfiguration = new ServerConfiguration();

        foreach((array)$targetConfig as $key => $value) {
            $serverConfiguration->{$key} = $value;
        }

        return $serverConfiguration;
    }

    /**
     * @param $totals
     * @param $consolidation
     * @return $this
     */
    protected function _totalConsolidation(&$totals, &$consolidation)
    {
        $this->_traitTotalConsolidation($totals, $consolidation);
        $totals->target->total_cores = $totals->existing->comparisonCores;
        return $this;
    }
}