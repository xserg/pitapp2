<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Environment;


use App\Models\Project\Environment;
use App\Models\Project\Project;
use App\Services\Analysis\Pricing\Software\MapAccessTrait;

class TotalsCalculator
{
    /**
     * Total the costs of a given environment. Keep in mind that the existing environment
     * is also passed into this function
     * @param Project $project
     * @param Environment $environment
     * @return $this
     */
    public function calculateCosts(Project $project, Environment $environment)
    {
        $keys = [
            'total_hardware_usage',
            'total_hardware_maintenance',
            'total_system_software_maintenance',
            'power_cost',
            'total_fte_cost',
            'total_storage_maintenance',
            'storage_purchase_price',
            'network_costs',
            'iops_purchase_price'
        ];

        if (!$environment->isExisting()) {
            $keys2 = collect(
                ['purchase_price', 'system_software_purchase_price', 'migration_services', 'remaining_deprecation']
            );
            $keys = collect($keys)->merge($keys2)->toArray();
        }

        // Environment costs
        $environment->total_cost = collect($keys)->reduce(function($carry, $key) use ($environment){
            return $carry + $environment->{$key};
        }, 0.00);

        // Software costs
        // Get the support + (optionally) license cost for each software, and each software feature
        /** @var \stdClass $software */
        foreach($project->softwares as $software) {

            $softwareEnvironment = collect($software->envs)->where('id', $environment->id)->first();

            if (!$softwareEnvironment) {
                continue;
            }

            $environment->total_cost += $softwareEnvironment->supportCost;
            if (!$softwareEnvironment->ignoreLicense) {
                $environment->total_cost += $softwareEnvironment->licenseCost;
            }

            /** @var \stdClass $feature */
            foreach($software->features as $feature) {
                $featureEnvironment = collect($feature->envs)->where('id', $environment->id)->first();

                if (!$featureEnvironment) {
                    continue;
                }

                $environment->total_cost += $featureEnvironment->supportCost;
                if (!$featureEnvironment->ignoreLicense) {
                    $environment->total_cost += $featureEnvironment->licenseCost;
                }
            }
        }

        // Interconnect chassis costs
        if (isset($environment->analysis) && isset($environment->analysis->interchassisResult)) {
            $interchassisResult = $environment->analysis->interchassisResult;
            $environment->total_cost += $interchassisResult->purchase_cost;
            $environment->total_cost += $interchassisResult->annual_maintenance * $project->support_years;
        }

        return $this;
    }
}