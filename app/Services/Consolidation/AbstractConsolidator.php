<?php
/**
 *
 */

namespace App\Services\Consolidation;


use App\Models\Project\Environment;

abstract class AbstractConsolidator
{
    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return \stdClass
     */
    abstract public function consolidate(Environment $existingEnvironment, Environment $targetEnvironment) : \stdClass;
}