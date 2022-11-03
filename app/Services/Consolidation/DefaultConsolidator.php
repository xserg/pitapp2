<?php
/**
 *
 */

namespace App\Services\Consolidation;


use App\Exceptions\ConsolidationException;
use App\Models\Project\Environment;
use App\Services\Consolidation\DefaultConsolidator\HybridDefaultConsolidator;
use App\Services\Consolidation\DefaultConsolidator\PhysicalDefaultConsolidator;
use App\Services\Consolidation\DefaultConsolidator\VmDefaultConsolidator;

class DefaultConsolidator extends AbstractConsolidator
{
    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return \stdClass
     * @throws \App\Exceptions\ConsolidationException
     */
    public function consolidate(Environment $existingEnvironment, Environment $targetEnvironment): \stdClass
    {
        switch ($existingEnvironment->getExistingEnvironmentType()) {
            case Environment::EXISTING_ENVIRONMENT_TYPE_VM:
                throw new ConsolidationException("VM Only Existing Environments must use the Cloud Consolidator, in: " . __METHOD__);
            case Environment::EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM:
                return $this->hybridDefaultConsolidator()->consolidate($existingEnvironment, $targetEnvironment);
            default:
                return $this->physicalDefaultConsolidator()->consolidate($existingEnvironment, $targetEnvironment);
        }
    }

    /**
     * @return PhysicalDefaultConsolidator
     */
    public function physicalDefaultConsolidator()
    {
        return resolve(PhysicalDefaultConsolidator::class);
    }

    /**
     * @return HybridDefaultConsolidator
     */
    public function hybridDefaultConsolidator()
    {
        return resolve(HybridDefaultConsolidator::class);
    }
}
