<?php
/**
 *
 */

namespace App\Services\Analysis\Report;


use App\Models\Project\AnalysisResult;
use App\Models\Project\Environment;
use App\Models\Project\Project;

class Web extends AbstractReport
{
    /**
     * @param AnalysisResult $analysisResult
     * @return object
     */
    public function generate(AnalysisResult $analysisResult)
    {
        $project = $analysisResult->getProject();
        $existingEnvironment = $analysisResult->getExistingEnvironment();
        $bestTargetEnvironment = $analysisResult->getBestTargetEnvironment();

        $savingsResult = $this->projectSavingsCalculator()->calculateSavings($project, $existingEnvironment, $bestTargetEnvironment);

        $savingsGraph = $this->projectSavingsCalculator()->fetchSavingsGraph(
            $savingsResult->values,
            $savingsResult->settings
        );

        $savingsByCategoryResult = $this->projectSavingsByCategoryCalculator()->calculateSavingsByCategory($project, $existingEnvironment, $bestTargetEnvironment);

        $savingsByCategoryGraph = $this->projectSavingsByCategoryCalculator()->fetchSavingsByCategoryGraph(
            $savingsByCategoryResult->values,
            $savingsByCategoryResult->settings
        );

        /*
         * Prepare the response. Remove some references so this response object is smaller
         */
        foreach($project->environments as &$e) {
            unset($e->target_analysis);
        }

        /*
         * Ensure changes to these objects don't change the environments property on $project
         */
        $bestTargetEnvironment = json_decode(json_encode($bestTargetEnvironment));
        $existingEnvironment = json_decode(json_encode($existingEnvironment));

        /*
         * Remove some references so response object is smaller
         */
        unset($bestTargetEnvironment->analysis);
        unset($existingEnvironment->server_configurations);

        if($project->logo && !starts_with($project->logo, 'api/'))
            $project->logo = 'api/'.$project->logo;
///        $project->logo = $project->local_logo;

        $results = (object) [
            'existingEnvironment' => $existingEnvironment,
            'bestTarget' => $bestTargetEnvironment,
            'project' => $project,
            'totalSavingsGraph' => $savingsGraph,
            'savingsByCatGraph' => $savingsByCategoryGraph
        ];

        return $results;
    }
}
