<?php
/**
 *
 */
namespace App\Services\Consolidation\CloudConsolidator;

use App\Exceptions\ConsolidationException;
use App\Models\Hardware\AmazonServer;
use App\Models\Project\Environment;
use App\Models\Hardware\ServerConfiguration;
use Illuminate\Support\Collection;

class VmCloudConsolidator extends HybridCloudConsolidator
{
    /**
     * @param Collection $serverConfigurations
     * @return mixed
     */
    public function getExistingServerConfigurations($serverConfigurations)
    {
        $serverConfigurations->each(function(ServerConfiguration $serverConfiguration){
            $serverConfiguration->makePhysicalCompatible();
        });

        return $serverConfigurations;
    }

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
        $totals = AbstractCloudConsolidator::totalCloudConsolidations($consolidations, $cpuUtilization, $ramUtilization, $targetEnvironment, $existingEnvironment);

        $totals->existing->socket_qty = 0;
        $totals->existing->servers = $totals->existing->vms;

        return $totals;
    }
}