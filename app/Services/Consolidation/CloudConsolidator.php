<?php
/**
 *
 */
namespace App\Services\Consolidation;

use App\Models\Project\Environment;
use App\Services\Consolidation\CloudConsolidator\HybridCloudConsolidator;
use App\Services\Consolidation\CloudConsolidator\PhysicalCloudConsolidator;
use App\Services\Consolidation\CloudConsolidator\VmCloudConsolidator;

class CloudConsolidator extends AbstractConsolidator
{
    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return \stdClass
     * @throws \App\Exceptions\ConsolidationException
     */
    public function consolidate(Environment $existingEnvironment, Environment $targetEnvironment): \stdClass
    {
        $existingEnvironmentType = $existingEnvironment->getExistingEnvironmentType();
        if (config('app.debug')) {
            logger('File: ' . __FILE__ . PHP_EOL . 'Function: ' . __FUNCTION__ . PHP_EOL . 'Line:' . __LINE__);
            logger('Existing environment type: ' . $existingEnvironmentType);
        }
        switch ($existingEnvironmentType) {
            case Environment::EXISTING_ENVIRONMENT_TYPE_VM:
                return $this->VmCloudConsolidator()->consolidate($existingEnvironment, $targetEnvironment);
            case Environment::EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM:
                return $this->HybridCloudConsolidator()->consolidate($existingEnvironment, $targetEnvironment);
            default:
                return $this->PhysicalCloudConsolidator()->consolidate($existingEnvironment, $targetEnvironment);
        }
    }

    /**
     * @return PhysicalCloudConsolidator
     */
    public function PhysicalCloudConsolidator()
    {
        return resolve(PhysicalCloudConsolidator::class);
    }

    /**
     * @return HybridCloudConsolidator
     */
    public function HybridCloudConsolidator()
    {
        return resolve(HybridCloudConsolidator::class);
    }

    /**
     * @return VmCloudConsolidator
     */
    public function VmCloudConsolidator()
    {
        return resolve(VmCloudConsolidator::class);
    }
}
