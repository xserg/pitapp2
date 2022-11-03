<?php
/**
 *
 */

namespace App\Services\Analysis\Environment\Target;


use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Models\Project\EnvironmentType;
use App\Services\Analysis\Environment\Target\DefaultTargetAnalyzer\HybridCopier;
use App\Services\Hardware\ChassisCalculator;
use App\Services\Hardware\InterchassisAccessTrait;
use App\Services\Hardware\InterconnectCalculator;

class DefaultTargetAnalyzer extends AbstractTargetAnalyzer
{
    use InterchassisAccessTrait;

    /**
     * DefaultTargetAnalyzer constructor.
     */
    public function __construct()
    {
        $this->_copyExistingKeys = collect($this->_copyExistingKeys)
            ->merge(collect(['network_costs', 'network_per_year', 'max_network']))
            ->toArray();
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        foreach($targetEnvironment->serverConfigurations as $config) {
            $this->calculateServerConfigurationCost($config, $targetEnvironment, $existingEnvironment);
        }

        if ($existingEnvironment->isPhysicalVm() && $targetEnvironment->getAdditionalServerConfigurations()) {
            /** @var ServerConfiguration $serverConfiguration */
            foreach ($targetEnvironment->getAdditionalServerConfigurations() as $config) {
                $this->calculateServerConfigurationCost($config, $targetEnvironment, $existingEnvironment);
            }
        }

        $this->calculateFteCosts($targetEnvironment);

        $this->pricingService()->environmentStorageCalculator()->calculateCosts($targetEnvironment, $existingEnvironment);

        switch($targetEnvironment->getEnvironmentType()->name) {
            case Environment::ENVIRONMENT_TYPE_COMPUTE:
            case Environment::ENVIRONMENT_TYPE_COMPUTE_STORAGE:
                $this->calculateChassisCosts($targetEnvironment, $existingEnvironment);
                break;
            case Environment::ENVIRONMENT_TYPE_CONVERGED:
                $this->calculateInterconnectCosts($targetEnvironment, $existingEnvironment);
                break;
        }

        return $this;
    }

    /**
     * @param Environment $environment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateServerConfigurationQty(Environment $environment, Environment $existingEnvironment)
    {
        parent::calculateServerConfigurationQty($environment, $existingEnvironment);

        if (!isset($environment->analysis)) {
            return $this;
        }

        if ($existingEnvironment->isPhysicalVm()) {
            $this->hybridCopier()->copyVmServers($environment, $existingEnvironment);
        }

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateChassisCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        if (!$targetEnvironment->analysis) {
            return $this;
        }

        $targetEnvironment->analysis->interchassisResult = $this->chassisCalculator()->calculateNeededChassisInterconnect($targetEnvironment->analysis->consolidations);

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateInterconnectCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        if (!$targetEnvironment->analysis) {
            return $this;
        }

        // Always factor the needed consolidations into the analysis
        $consolidations = $targetEnvironment->analysis->consolidations;

        $storage = [];

        if (isset($targetEnvironment->analysis->storage)) {
            // Factor in additional nodes needed to meet a storage constraint
            $storage = $targetEnvironment->analysis->storage;
            if (isset($targetEnvironment->analysis->iops)) {
                // Factor in additional nodes needed to meet an iops constraint
                $storage = collect($storage)->merge(collect($targetEnvironment->analysis->iops))->toArray();
            }
        }

        $targetEnvironment->analysis->interchassisResult = $this->interconnectCalculator()->calculateNeededChassisInterconnect($consolidations, $storage);

        return $this;
    }

    /**
     * @param Environment $environment
     * @return $this|AbstractTargetAnalyzer
     */
    public function setServerConfigurationData(Environment $environment)
    {
        parent::setServerConfigurationData($environment);

        if ($environment->isConverged()) {
            $this->setConvergedServerConfigurationData($environment);
        }

        return $this;
    }

    public function calculateVmServerConfigurationCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {

    }

    /**
     * @return HybridCopier
     */
    public function hybridCopier()
    {
        return resolve(HybridCopier::class);
    }
}