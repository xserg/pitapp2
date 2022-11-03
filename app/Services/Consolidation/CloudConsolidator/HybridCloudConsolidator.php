<?php

/**
 *
 */
namespace App\Services\Consolidation\CloudConsolidator;

use App\Exceptions\ConsolidationException;
use App\Models\Hardware\AmazonServer;
use App\Models\Project\Environment;
use App\Models\Hardware\ServerConfiguration;
use App\Services\Consolidation\HybridConsolidatorTrait;
use Illuminate\Support\Collection;

class HybridCloudConsolidator extends AbstractCloudConsolidator
{
    use HybridConsolidatorTrait;

    /**
     * @param $consolidations
     * @param $cpuUtilization
     * @param $ramUtilization
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function totalCloudConsolidations($consolidations, $cpuUtilization, $ramUtilization, Environment $targetEnvironment,Environment $existingEnvironment)
    {
        $totals = parent::totalCloudConsolidations($consolidations, $cpuUtilization, $ramUtilization, $targetEnvironment, $existingEnvironment);
        
        if (!empty($totals) && is_object($totals->existing)) {
          $totals->existing->socket_qty = 0;
          $totals->existing->servers = 0;
        }

        /** @var ServerConfiguration[] $existingServers */
        foreach ($this->getExistingServerChunks($existingEnvironment) as $existingServers) {
            /** @var ServerConfiguration $serverConfiguration */
            foreach ($existingServers as $serverConfiguration) {
                if ($serverConfiguration->isVm()) {
                    continue;
                }
                if (!empty($totals) && is_object($totals->existing)) {
                  $totals->existing->socket_qty += $this->consolidationHelper()->sumSockets($serverConfiguration->processor->socket_qty);
                  $totals->existing->servers++;
                }
            }
        }

        return $totals;
    }
}