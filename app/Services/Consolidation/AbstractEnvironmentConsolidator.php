<?php
/**
 *
 */

namespace App\Services\Consolidation;

use App\Exceptions\ConsolidationException;
use App\Helpers\Consolidation as ConsolidationHelper;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;

class AbstractEnvironmentConsolidator
{
    /**
     * @return ConsolidationHelper
     */
    public function consolidationHelper()
    {
        return resolve(ConsolidationHelper::class);
    }

    /**
     * @param $msg
     * @param $data
     */
    public function consolidationException($msg, $data = null)
    {
        return (new ConsolidationException($msg))->setData($data);
    }

    /**
     * @param $serverConfigurations
     * @return mixed
     */
    public function getExistingServerConfigurations($serverConfigurations)
    {
        $cache = [];
        /** @var ServerConfiguration $serverConfiguration */
        foreach($serverConfigurations as $serverConfiguration) {
            if ($serverConfiguration instanceof ServerConfiguration && !$serverConfiguration->isConverged()) {
                $serverConfiguration->setRealProcessor($cache);
            }
        }
        return $serverConfigurations;
    }

    /**
     * @param ServerConfiguration[] $existingServers
     * @return bool
     */
    protected function _processExistingServers(&$existingServers)
    {
        if(!count($existingServers)) {
            return false;
        }

        return true;
    }

    /**
     * @param $totals
     * @param ServerConfiguration $server
     * @return $this
     */
    protected function _totalExistingServer(&$totals, &$server)
    {
        $totals->existing->servers++;
        $totals->existing->socket_qty += $this->consolidationHelper()->sumSockets($server->processor->socket_qty);
        $totals->existing->total_cores += $server->processor->total_cores;

        return $this;
    }

    /**
     * @param $totals
     * @param $servegetExistingServerChunksr
     * @return $this
     */
    protected function _totalTargetServer(&$totals, &$server)
    {
        return $this;
    }

    /**
     * @param $totals
     * @param $consolidation
     * @return $this
     */
    protected function _totalConsolidation(&$totals, &$consolidation)
    {
        return $this;
    }

    /**
     * @param mixed $totals
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    protected function _totalExistingEnvironment($totals, Environment $targetEnvironment,Environment $existingEnvironment)
    {
        return $this;
    }

    /**
     * @param Environment $existingEnvironment
     * @return array
     */
    public function getExistingServerChunks(Environment $existingEnvironment)
    {
        if ($existingEnvironment->isConverged()) {
            $existingConfigs = $this->consolidationHelper()->combineConverged($existingEnvironment->serverConfigurations);
        } else {
            $existingConfigs = $this->getExistingServerConfigurations($existingEnvironment->serverConfigurations);
        }
        $existingServerChunks = [];
        foreach($existingConfigs as &$server) {
            $serverArray = [];
            if(!(bool)$server->is_converged && $server->type != ServerConfiguration::TYPE_VM) {
                if ($server instanceof ServerConfiguration) {
                    $server->setRealProcessor();
                }
                $server->processor->total_cores = $server->processor->core_qty * $server->processor->socket_qty;
            }
            // sanity check
            $server->qty = $server->qty ? $server->qty : 1;
            for($i = 0; $i < $server->qty; ++$i) {
                $serverArray[] = $server;
            }
            $existingServerChunks[] = $serverArray;
        }

        return $existingServerChunks;
    }
}