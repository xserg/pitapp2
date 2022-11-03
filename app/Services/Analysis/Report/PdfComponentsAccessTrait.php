<?php
/**
 *
 */

namespace App\Services\Analysis\Report;


use App\Models\Project\Environment;
use App\Services\Analysis\Report\Pdf;

trait PdfComponentsAccessTrait
{
    /**
     * @param $existingEnvironmentType
     * @return Pdf\Hybrid\Consolidation|Pdf\Physical\Consolidation|Pdf\Vm\Consolidation
     */
    public function pdfConsolidation($existingEnvironmentType)
    {
        switch($existingEnvironmentType) {
            case Environment::EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM:
                return resolve(Pdf\Hybrid\Consolidation::class);
                break;
            case Environment::EXISTING_ENVIRONMENT_TYPE_VM:
                return resolve(Pdf\Vm\Consolidation::class);
                break;
            default:
                return resolve(Pdf\Physical\Consolidation::class);
                break;
        }
    }
}