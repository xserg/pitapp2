<?php


namespace App\Services\Consolidation\Report;


use App\Models\Project\AnalysisResult;
use App\Models\Project\Environment;

class SpreadSheet extends AbstractReport
{
    public function generate(AnalysisResult $analysisResult)
    {
        $project = $analysisResult->getProject();
        $existingEnvironment = $analysisResult->getExistingEnvironment();
        $consolidations = [];

        if ($project->isNewVsNew()) {
            $project->environments->shift();
        }

        foreach ($project->environments as $targetEnvironment) {
            // fix for target cpu util being incorrect
            $targetEnvironment->cpu_utilization = $existingEnvironment->cpu_utilization;
            if (!$targetEnvironment->is_existing && !$targetEnvironment->isCloud()) {
                $consolidation = $this->getConsolidation($existingEnvironment)->formatConsolidation($existingEnvironment, $targetEnvironment);
                if ($consolidation) {
                    $consolidations[] = $consolidation;
                }
            }
        }

        return $consolidations;
    }

    protected function getExistingEnvironmentType(Environment $environment)
    {
        switch ($environment->existing_environment_type) {
            case Environment::EXISTING_ENVIRONMENT_TYPE_PHYSICAL:
                return 'PhysicalConsolidation';
            case Environment::EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM:
                return 'HybridConsolidation';
            default:
                return false;
        }
    }

    protected function getConsolidation($existingEnvironment)
    {
        $existingEnvironmentType = $this->getExistingEnvironmentType($existingEnvironment);

        if (!$existingEnvironmentType) {
            return false;
        }

        return resolve("App\Services\Consolidation\Report\Spreadsheet\\{$existingEnvironmentType}");
    }
}
