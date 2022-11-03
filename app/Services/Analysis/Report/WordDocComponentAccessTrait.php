<?php
/**
 *
 */

namespace App\Services\Analysis\Report;


use App\Models\Project\Environment;
use App\Services\Analysis\Report\WordDoc;

trait WordDocComponentAccessTrait
{
    /**
     * @param $existingEnvironmentType
     * @return WordDoc\PhysicalConsolidation|WordDoc\HybridConsolidation|WordDoc\VmConsolidation
     */
    public function wordDocConsolidation($existingEnvironmentType)
    {
        switch($existingEnvironmentType) {
            case Environment::EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM:
                return resolve(WordDoc\HybridConsolidation::class);
                break;
            case Environment::EXISTING_ENVIRONMENT_TYPE_VM:
                return resolve(WordDoc\VmConsolidation::class);
                break;
            default:
                return resolve(WordDoc\PhysicalConsolidation::class);
                break;
        }
    }
}