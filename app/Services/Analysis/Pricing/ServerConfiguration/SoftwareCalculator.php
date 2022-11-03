<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\ServerConfiguration;


use App\Exceptions\AnalysisException;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Models\Software\FeatureCost;
use App\Models\Software\Software;
use App\Models\Software\SoftwareCost;
use App\Services\Analysis\Pricing\Software\MapAccessTrait;
use App\Services\Analysis\PricingAccessTrait;
use Illuminate\Support\Collection;

class SoftwareCalculator
{
    use PricingAccessTrait;
    use MapAccessTrait;

    /**
     * @var array
     */
    protected $_aggregateSoftware = [];

    /**
     * Calculate various software costs for a given environment.
     * If this is a target, we may need to reference the existing environment
     * @param ServerConfiguration $serverConfiguration
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return $this
     */
    public function calculateCosts(ServerConfiguration $serverConfiguration, Environment $environment, Environment $existingEnvironment = null)
    {
        $this->calculateOsCosts($serverConfiguration, $environment, $existingEnvironment)
            ->calculateHypervisorCosts($serverConfiguration, $environment, $existingEnvironment)
            ->calculateMiddlewareCosts($serverConfiguration, $environment, $existingEnvironment)
            ->calculateDatabaseCosts($serverConfiguration, $environment, $existingEnvironment);

        return $this;
    }

    /**
     * @param ServerConfiguration $serverConfiguration
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return $this
     */
    public function calculateOsCosts(ServerConfiguration $serverConfiguration, Environment $environment, Environment $existingEnvironment = null)
    {
        if (!$this->_handleVmAggregateSoftware($serverConfiguration->os, $environment)) {
            return $this;
        }

        if ($this->pricingService()->serverConfigurationGroupSoftwareCalculator()->shouldSkipPhysicalSoftwareInVmGroup($serverConfiguration, $serverConfiguration->os, 'os_id', $environment, $existingEnvironment)) {
            return $this;
        }

        $qty = $serverConfiguration->getQty();
        $configOSCost = $this->pricingService()->softwareCalculator()->licenseCost($serverConfiguration->os, $serverConfiguration->processor, $environment);
        $environment->os_support_per_year += $this->pricingService()->softwareCalculator()->supportCostPerYear(
            $serverConfiguration->os,
            $configOSCost,
            $serverConfiguration->processor,
            $environment,
            $qty,
            null,
            $serverConfiguration->licensed_cores
        );

        if($environment->project->isNewVsNew() || !$this->pricingService()->softwareCalculator()->isInEnvironment($serverConfiguration->os, $existingEnvironment)) {
            $environment->os_license += $configOSCost * $qty;
        } else if($serverConfiguration->os) {
            $this->softwareMap()->setData('ignoreLicense', true, $serverConfiguration->os, $environment);
        }

        $environment->os_license += $this->pricingService()->softwareFeatureCalculator()->licenseFeaturesCost($serverConfiguration->os, $serverConfiguration->processor, $environment, $existingEnvironment) * $qty;
        $environment->os_support_per_year += $this->pricingService()->softwareFeatureCalculator()->supportFeaturesCostPerYear($serverConfiguration->os, $serverConfiguration->processor, $environment) * $qty;

        return $this;
    }

    /**
     * @param ServerConfiguration $serverConfiguration
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return $this
     */
    public function calculateHypervisorCosts(ServerConfiguration $serverConfiguration, Environment $environment, Environment $existingEnvironment = null)
    {
        if (!$this->_handleVmAggregateSoftware($serverConfiguration->hypervisor, $environment)) {
            return $this;
        }

        if ($this->pricingService()->serverConfigurationGroupSoftwareCalculator()->shouldSkipPhysicalSoftwareInVmGroup($serverConfiguration, $serverConfiguration->hypervisor, 'hypervisor_id', $environment, $existingEnvironment)) {
            return $this;
        }

        $qty = $serverConfiguration->getQty();
        $configHypervisorCost = $this->pricingService()->softwareCalculator()->licenseCost($serverConfiguration->hypervisor, $serverConfiguration->processor, $environment);
        $environment->hypervisor_support_per_year += $this->pricingService()->softwareCalculator()->supportCostPerYear(
            $serverConfiguration->hypervisor,
            $configHypervisorCost,
            $serverConfiguration->processor,
            $environment,
            $qty,
            null,
            $serverConfiguration->licensed_cores
        );

        if($environment->project->isNewVsNew() || !$this->pricingService()->softwareCalculator()->isInEnvironment($serverConfiguration->hypervisor, $existingEnvironment)) {
            $environment->hypervisor_license += $configHypervisorCost * $qty;
        } else if($serverConfiguration->hypervisor) {
            $this->softwareMap()->setData('ignoreLicense', true, $serverConfiguration->hypervisor, $environment);
        }

        $environment->hypervisor_license += $this->pricingService()->softwareFeatureCalculator()->licenseFeaturesCost($serverConfiguration->hypervisor, $serverConfiguration->processor, $environment, $existingEnvironment) * $qty;
        $environment->hypervisor_support_per_year += $this->pricingService()->softwareFeatureCalculator()->supportFeaturesCostPerYear($serverConfiguration->hypervisor, $serverConfiguration->processor, $environment) * $qty;

        return $this;
    }

    /**
     * @param ServerConfiguration $serverConfiguration
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return $this
     */
    public function calculateMiddlewareCosts(ServerConfiguration $serverConfiguration, Environment $environment, Environment $existingEnvironment = null)
    {
        if (!$this->_handleVmAggregateSoftware($serverConfiguration->middleware, $environment)) {
            return $this;
        }

        if ($this->pricingService()->serverConfigurationGroupSoftwareCalculator()->shouldSkipPhysicalSoftwareInVmGroup($serverConfiguration, $serverConfiguration->middleware, 'middleware_id', $environment, $existingEnvironment)) {
            return $this;
        }

        $qty = $serverConfiguration->getQty();
        $configMiddlewareCost = $this->pricingService()->softwareCalculator()->licenseCost($serverConfiguration->middleware, $serverConfiguration->processor, $environment);
        $environment->middleware_support_per_year += $this->pricingService()->softwareCalculator()->supportCostPerYear(
            $serverConfiguration->middleware,
            $configMiddlewareCost,
            $serverConfiguration->processor,
            $environment,
            $qty,
            $serverConfiguration->drive_qty,
            $serverConfiguration->licensed_cores
        );

        if($environment->project->isNewVsNew() || !$this->pricingService()->softwareCalculator()->isInEnvironment($serverConfiguration->middleware, $existingEnvironment)) {
            $environment->middleware_license += $configMiddlewareCost * $qty;
        } else if($serverConfiguration->middleware) {
            $this->softwareMap()->setData('ignoreLicense', true, $serverConfiguration->middleware, $environment);
        }

        $environment->middleware_license += $this->pricingService()->softwareFeatureCalculator()->licenseFeaturesCost($serverConfiguration->middleware, $serverConfiguration->processor, $environment, $existingEnvironment) * $qty;
        $environment->middleware_support_per_year += $this->pricingService()->softwareFeatureCalculator()->supportFeaturesCostPerYear($serverConfiguration->middleware, $serverConfiguration->processor, $environment) * $qty;

        return $this;
    }

    /**
     * @param ServerConfiguration $serverConfiguration
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return $this
     */
    public function calculateDatabaseCosts(ServerConfiguration $serverConfiguration, Environment $environment, Environment $existingEnvironment = null)
    {
        if (!$this->_handleVmAggregateSoftware($serverConfiguration->database, $environment)) {
            return $this;
        }

        if ($this->pricingService()->serverConfigurationGroupSoftwareCalculator()->shouldSkipPhysicalSoftwareInVmGroup($serverConfiguration, $serverConfiguration->database, 'database_id', $environment, $existingEnvironment)) {
            return $this;
        }

        $qty = $serverConfiguration->getQty();
        $configDatabaseCost = $this->pricingService()->softwareCalculator()->licenseCost($serverConfiguration->database, $serverConfiguration->processor, $environment);
        $environment->database_support_per_year += $this->pricingService()->softwareCalculator()->supportCostPerYear(
            $serverConfiguration->database,
            $configDatabaseCost,
            $serverConfiguration->processor,
            $environment,
            $qty,
            null,
            $serverConfiguration->licensed_cores
        );

        if($environment->project->isNewVsNew() || !$this->pricingService()->softwareCalculator()->isInEnvironment($serverConfiguration->database, $existingEnvironment)) {
            $environment->database_license += $configDatabaseCost * $qty;
        } else if($serverConfiguration->database) {
            $this->softwareMap()->setData('ignoreLicense', true, $serverConfiguration->database, $environment);
        }
        $environment->database_license += $this->pricingService()->softwareFeatureCalculator()->licenseFeaturesCost($serverConfiguration->database, $serverConfiguration->processor, $environment, $existingEnvironment) * $qty;
        $environment->database_support_per_year += $this->pricingService()->softwareFeatureCalculator()->supportFeaturesCostPerYear($serverConfiguration->database, $serverConfiguration->processor, $environment) * $qty;

        return $this;
    }

    /**
     * @param null|Software $software
     * @param Environment $environment
     * @return bool
     */
    protected function _handleVmAggregateSoftware($software, Environment $environment)
    {
        if (!$environment->isVm() || !$software || !$environment->isExisting()) {
            return true;
        }

        $this->softwareMap()->setScope($software->id, $environment->id);

        $softwareCost = $environment->getSoftwareCostBySoftware($software);

        if (!$softwareCost) {
            return true;
        }

        if (!$software->requiresVmAggregate()) {
            // Ensure all features of non VM aggregate software do not work off aggregate values
            // inverse of below
            /** @var FeatureCost $featureCost */
            foreach ($softwareCost->featureCosts as $featureCost) {
                if ($featureCost->feature->requiresVmAggregate()) {
                    throw new AnalysisException("Software: {$software->name} does not calculate costs by physical cores or physical processors, but one of its features does: {$featureCost->feature->name}.");
                }
            }
            return true;
        }

        if ($this->softwareMap()->alreadyHasVmAggregateSoftware()) {
            return false;
        }

        $this->softwareMap()->setData('physical_processors', $softwareCost->physical_processors ?: 1);
        $this->softwareMap()->setData('physical_cores', $softwareCost->physical_cores ?: 1);
        $this->softwareMap()->setIsVmAggregate();

        // Ensure all features of VM aggregate software work off aggregate values
        // inverse of above
        /** @var FeatureCost $featureCost */
        foreach ($softwareCost->featureCosts as $featureCost) {
            if (!$featureCost->feature->requiresVmAggregate()) {
                throw new AnalysisException("Software: {$software->name} calculates costs by physical cores or physical processors, but one of its features does: {$featureCost->feature->name}.");
            }
        }

        return true;
    }
}