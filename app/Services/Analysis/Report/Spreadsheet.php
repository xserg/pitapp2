<?php

namespace App\Services\Analysis\Report;

use App\Models\Project\AnalysisResult;
use App\Models\Project\Environment;

class Spreadsheet extends AbstractReport
{
    /**
     * @param AnalysisResult $analysisResult
     * @return object
     */
    public function generate(AnalysisResult $analysisResult)
    {
        $project = $analysisResult->getProject();
        $existingEnvironment = $analysisResult->getExistingEnvironment();
        $consolidations = [];

        foreach($project->environments as $targetEnvironment) {
            // TODO remove isCloud when we include other types of environments
            if (!$targetEnvironment->is_existing && $targetEnvironment->isCloud()) {
                $consolidations[] = $this->getConsolidation($existingEnvironment, $targetEnvironment)->formatConsolidation($existingEnvironment, $targetEnvironment);
            }
        }

        return $consolidations;
    }

    /**
     * @param Environment $environment
     * @return string
     */
    protected function getExistingEnvironmentType(Environment $environment)
    {
        switch($environment->existing_environment_type) {
            case Environment::EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM:
                return 'HybridConsolidation';
            case Environment::EXISTING_ENVIRONMENT_TYPE_VM:
                return 'VmConsolidation';
            default:
                return 'PhysicalConsolidation';
        }
    }

    /**
     * @param Environment $environment
     * @return string
     */
    protected function getTargetEnvironmentType(Environment $environment)
    {
        return $environment->isCloud() ? 'Cloud' : 'Default';
    }

    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return Spreadsheet\PhysicalConsolidation|Spreadsheet\HybridConsolidation|Spreadsheet\VmConsolidation
     */
    protected function getConsolidation($existingEnvironment, $targetEnvironment)
    {
        $existingEnvironmentType = $this->getExistingEnvironmentType($existingEnvironment);
        $targetEnvironmentType = $this->getTargetEnvironmentType($targetEnvironment);
        return resolve("App\Services\Analysis\Report\Spreadsheet\\{$targetEnvironmentType}\\{$existingEnvironmentType}");
    }
}
