<?php
/**
 *
 */

namespace App\Services\Analysis\Environment\Existing;


use App\Models\Hardware\Server;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Services\Analysis\Environment\AbstractAnalyzer;
use Illuminate\Support\Facades\Log;

class AbstractExistingAnalyzer extends AbstractAnalyzer
{
    /**
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function analyze(Environment $existingEnvironment)
    {
        return $this->setEnvironmentDefaults($existingEnvironment)
            ->calculateCosts($existingEnvironment)
            ->afterCalculateCosts($existingEnvironment);
    }


    /**
     * Calculate the existing environment costs.
     * NOTE - Possible point of confusion. Although this environment is itself "existing"
     * it will be passed to calculators as "environment' while "existingEnvironment" is null
     * That's because existingEnvironment only factors into the calculator when it's a target
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateCosts(Environment $existingEnvironment) 
    {
        $physicals = $existingEnvironment->serverConfigurations->where('type', ServerConfiguration::TYPE_PHYSICAL)->all();
        $physicalsCache = [];
        foreach ($physicals as $physical) {
            $physicalsCache[$physical->id] = $physical;
        }
        /** @var ServerConfiguration $config */
        foreach($existingEnvironment->serverConfigurations as $config) {
            if (!$existingEnvironment->isCloud() && !$config->isConverged() && $config->isPhysical() && !$config->processor_id) {
                throw new \Exception("Insufficient data to run analysis. Existing Environment has one or more server configurations with no processor.");
            }
            if ($existingEnvironment->isPhysicalVm()) {
                if ($config->isVm()) {
                    $config->copyPhysicalAttributes($physicalsCache[$config->physical_configuration_id], false);
                } else {
                    $config->makeVmCompatible();
                }
            } else if ($existingEnvironment->isVm()) {
                $config->makePhysicalCompatible();
            }
            $this->calculateServerConfigurationCost($config, $existingEnvironment);
        }

        if ($existingEnvironment->isVm() && floatval($existingEnvironment->vm_hardware_annual_maintenance)) {
            $existingEnvironment->total_hardware_maintenance_per_year += floatval($existingEnvironment->vm_hardware_annual_maintenance);
        }

        $this->calculateFteCosts($existingEnvironment);

        $this->pricingService()->environmentStorageCalculator()->calculateCosts($existingEnvironment);

        return $this;
    }

    /**
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function afterCalculateCosts(Environment $existingEnvironment)
    {
        $project = $existingEnvironment->project;

        return $this;
    }

    /**
     * @param ServerConfiguration $serverConfiguration
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateServerConfigurationCost(ServerConfiguration $serverConfiguration, Environment $existingEnvironment)
    {
        $qty = $serverConfiguration->getQty();

        if (!intval($qty)) {
            return $this;
        }

        if ($serverConfiguration->isPhysical()) {
            $this->pricingService()->serverConfigurationHardwareCalculator()->calculateCosts($serverConfiguration, $existingEnvironment);
        }

        $this->pricingService()->serverConfigurationSoftwareCalculator()->calculateCosts($serverConfiguration, $existingEnvironment);

        return $this;
    }

    /**
     * @param Environment $environment
     * @return $this
     */
    public function setEnvironmentDefaults(Environment $environment)
    {
        foreach($environment->serverConfigurations as &$config) {
            $config->qty = $config->qty ? $config->qty : 1;
        }

        $environment->max_network = 0;

        return parent::setEnvironmentDefaults($environment);
    }
}