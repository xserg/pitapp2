<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Environment;


use App\Models\Project\Environment;
use App\Models\Project\Project;
use Illuminate\Support\Collection;

class NetworkCalculator
{
    /**
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Collection $targetEnvironments
     * @return $this
     */
    public function updateExistingEnvironment(Project $project, Environment $existingEnvironment, Collection $targetEnvironments)
    {
        if($existingEnvironment->network_overhead) {
            $existingEnvironment->network_costs = $existingEnvironment->network_overhead * $project->support_years;
        } else {
            $existingEnvironment->network_costs = $existingEnvironment->max_network * .4;
        }

        $existingEnvironment->network_per_yer = $existingEnvironment->network_costs / $project->support_years;

        return $this;
    }

    /**
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Collection $targetEnvironments
     * @return $this
     */
    public function copyExistingData(Project $project, Environment $existingEnvironment, Collection $targetEnvironments)
    {
        $targetEnvironments->each(function(Environment $targetEnvironment) use ($existingEnvironment, $project) {
            if (!$targetEnvironment->isCloud() && !$targetEnvironment->isTreatAsExisting()) {
                $targetEnvironment->network_costs = $targetEnvironment->network_overhead ? $targetEnvironment->network_overhead * $project->support_years : $existingEnvironment->network_costs;
                $targetEnvironment->network_per_yer = $targetEnvironment->network_costs ? $targetEnvironment->network_costs / $project->support_years : $existingEnvironment->network_per_yer;
                $targetEnvironment->network_overhead = $targetEnvironment->network_overhead ? $targetEnvironment->network_overhead : $existingEnvironment->network_overhead;
                $targetEnvironment->max_network = $existingEnvironment->max_network;
            }
        });

        return $this;
    }
}