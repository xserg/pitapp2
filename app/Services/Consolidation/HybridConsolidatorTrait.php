<?php
/**
 *
 */

namespace App\Services\Consolidation;


use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use Illuminate\Support\Collection;

trait HybridConsolidatorTrait
{
    /**
     * @param Collection $serverConfigurations
     * @return mixed
     */
    public function getExistingServerConfigurations($serverConfigurations)
    {
        $processorCache = [];
        $physicalsCache = [];
        $physicals = $serverConfigurations->where('type', ServerConfiguration::TYPE_PHYSICAL)->all();
        foreach ($physicals as $physical) {
            $physical->setRealProcessor($processorCache);
            $physical->makeVmCompatible();
            $physicalsCache[$physical->id] = $physical;
        }

        $vms = $serverConfigurations->where('type', ServerConfiguration::TYPE_VM)->all();
        foreach ($vms as $vm) {
            $vm->copyPhysicalAttributes($physicalsCache[$vm->physical_configuration_id]);
        }

        return $serverConfigurations;
    }

    /**
     * @param ServerConfiguration[] $existingServers
     * @return bool
     */
    protected function _processExistingServers(& $existingServers)
    {

        if (!parent::_processExistingServers($existingServers)) {
            return false;
        }

        if ($existingServers[0]->hasChildrenVMs()) {
            array_shift($existingServers);
            return false;
        }

        return true;
    }


    /**
     * @param $totals
     * @param $consolidation
     * @return $this
     */
    protected function _totalConsolidation(&$totals, &$consolidation)
    {
        $totals->target->vms += $consolidation->additionalExisting ?: 0;
        return $this;
    }

    /**
     * @param $totals
     * @param ServerConfiguration $server
     * @return $this
     */
    protected function _totalExistingServer(&$totals, &$server)
    {
        $keys = ['vms', 'total_cores'];

        $this->_defaultExistingTotalKeys($keys, $totals);

        // Existing & Target VM numbers should always be the same
        $totals->existing->vms++;
        $totals->target->vms++;
        $totals->existing->total_cores += $server->isVm() ? $server->vm_cores : $server->processor->total_cores;
        $totals->existing->vm_cores += $server->isVm() ? $server->vm_cores : 0;

        return $this;
    }

    /**
     * @param $totals
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    protected function _totalExistingEnvironment($totals, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $keys = ['physical_ram', 'physical_rpm', 'physical_cores', 'physical_computed_cores'];

        $this->_defaultExistingTotalKeys($keys, $totals);

        foreach($this->getExistingServerChunks($existingEnvironment) as $existingServerChunk) {
            /** @var ServerConfiguration $existingServer */
            foreach($existingServerChunk as $existingServer) {
                if ($existingServer->isVm()) {
                    continue;
                }
                $totals->existing->physical_ram += $existingServer->getComputedRam($existingEnvironment);
                $totals->existing->physical_cores += $existingServer->getTotalCores();
                $totals->existing->physical_computed_cores += $existingServer->getComputedCores($existingEnvironment);
                $totals->existing->physical_rpm += $existingServer->getComputedRpm($existingEnvironment);
            }
        }

        return $this;
    }

    /**
     * @param $keys
     * @param $totals
     * @return $this
     */
    protected function _defaultExistingTotalKeys($keys, $totals)
    {
        collect($keys)->each(function($key) use ($totals){
            $totals->existing->{$key} = $totals->existing->{$key} ?? 0;
        });

        return $this;
    }
}