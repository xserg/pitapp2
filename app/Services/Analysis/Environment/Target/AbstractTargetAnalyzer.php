<?php
/**
 *
 */

namespace App\Services\Analysis\Environment\Target;


use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Services\Analysis\Environment\AbstractAnalyzer;
use App\Services\Hardware\InterchassisAccessTrait;

abstract class AbstractTargetAnalyzer extends AbstractAnalyzer
{
    /**
     * @var array
     */
    protected $_copyExistingKeys = ['remaining_deprecation'];

    /**
     * @var array
     */
    protected $_copyBeforeExistingKeys = ['cost_per_kwh'];

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function analyze(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        return $this->setEnvironmentDefaults($targetEnvironment)
            ->calculateServerConfigurationQty($targetEnvironment, $existingEnvironment)
            ->beforeCalculateCosts($targetEnvironment, $existingEnvironment)
            ->calculateCosts($targetEnvironment, $existingEnvironment)
            ->afterCalculateCosts($targetEnvironment, $existingEnvironment);
    }


    /**
     * Calculate the target environment costs
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    abstract public function calculateCosts(Environment $targetEnvironment, Environment $existingEnvironment);

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function beforeCalculateCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        return $this->copyBeforeExistingEnvironmentData($targetEnvironment, $existingEnvironment);
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function afterCalculateCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        return $this->setServerConfigurationData($targetEnvironment)
            ->setExistingEnvironmentData($targetEnvironment, $existingEnvironment)
            ->copyExistingEnvironmentData($targetEnvironment, $existingEnvironment);
    }

    /**
     * @param ServerConfiguration $serverConfiguration
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateServerConfigurationCost(ServerConfiguration $serverConfiguration, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $qty = $serverConfiguration->getQty();

        if (!intval($qty)) {
            return $this;
        }

        if ($serverConfiguration->isPhysical()) {
            $this->pricingService()->serverConfigurationHardwareCalculator()->calculateCosts($serverConfiguration, $targetEnvironment, $existingEnvironment);
        }

        $this->pricingService()->serverConfigurationSoftwareCalculator()->calculateCosts($serverConfiguration, $targetEnvironment, $existingEnvironment);

        return $this;
    }

    /**
     * @param Environment $environment
     * @return $this|AbstractAnalyzer
     */
    public function setEnvironmentDefaults(Environment $environment)
    {
        parent::setEnvironmentDefaults($environment);

        if (!$environment->target_analysis) {
            return $this;
        }

        if (!isset($environment->analysis) || $environment->reset_analysis) {
            $environment->analysis = json_decode($environment->target_analysis);
        }

        return $this;
    }

    /**
     * Set certain server config values after calculating costs
     * @param Environment $environment
     * @return $this
     */
    public function setServerConfigurationData(Environment $environment)
    {
        if (isset($environment->serverConfigurations[0])) {
            // Uncertain as to the purpose of this code
            $environment->serverConfigurations[0]->environment_name = $environment->name;
        }

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function setExistingEnvironmentData(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        if($targetEnvironment->network_costs > $existingEnvironment->max_network) {
            // Existing environment max network must be set to target
            $existingEnvironment->max_network = $targetEnvironment->network_costs;
        }

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function copyBeforeExistingEnvironmentData(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        foreach($this->_copyBeforeExistingKeys as $copyKey) {
            $targetEnvironment->{$copyKey} = $existingEnvironment->{$copyKey};
        }

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function copyExistingEnvironmentData(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        foreach($this->_copyExistingKeys as $copyKey) {
            $targetEnvironment->{$copyKey} = $existingEnvironment->{$copyKey};
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
        if (!isset($environment->analysis)) {
            return $this;
        }

        /** @var ServerConfiguration $config */
        foreach ($environment->serverConfigurations as &$config) {
            if (config('app.debug')) {
                logger('File: ' . __FILE__ . PHP_EOL . 'Function: ' . __FUNCTION__ . PHP_EOL . 'Line:' . __LINE__);
                logger($config->toJson());
            }
            // Default quantity
            if (!$config->total_qty) {
                $config->total_qty = 0;
            }

            $this->_addConsolidationQty($config, $environment)
                ->_addStorageQty($config, $environment)
                ->_addIopsQty($config, $environment);
        }

        return $this;
    }

    /**
     * Add the additional quantity of each node required to meet the RAM / CPM / CPU constraints
     * @param ServerConfiguration $config
     * @param Environment $environment
     * @return $this
     */
    protected function _addConsolidationQty(ServerConfiguration $config, Environment $environment)
    {
        foreach($environment->analysis->consolidations as $cons) {
            foreach($cons->targets as &$tar) {
                if($tar->id == $config->id) {
                    $config->total_qty += ($config->qty ? $config->qty : 1);
                }
                if(isset($tar->configs)) {
                    foreach($tar->configs as $conf) {
                        if($config->id == $conf->id) {
                            $config->total_qty += ($config->qty ? $config->qty : 1);
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Add additional configurations required to meet a storage constraint
     * @param ServerConfiguration $config
     * @param Environment $environment
     * @return $this
     */
    protected function _addStorageQty(ServerConfiguration $config, Environment $environment)
    {
        return $this->_addQty('storage', $config, $environment);
    }

    /**
     * Add additional configurations required to meet an IOPS constraint
     * @param ServerConfiguration $config
     * @param Environment $environment
     * @return $this
     */
    protected function _addIopsQty(ServerConfiguration $config, Environment $environment)
    {
        return $this->_addQty('iops', $config, $environment);
    }

    /**
     * @param $field
     * @param ServerConfiguration $config
     * @param Environment $environment
     * @return $this
     */
    protected function _addQty($field, ServerConfiguration $config, Environment $environment)
    {
        if (isset($environment->analysis->{$field})) {
            foreach ($environment->analysis->{$field} as &$tar) {
                if ($tar->id == $config->id) {
                    $config->total_qty += ($config->qty ? $config->qty : 1);
                }
                if (isset($tar->configs)) {
                    foreach ($tar->configs as $conf) {
                        if ($config->id == $conf->id) {
                            $config->total_qty += ($config->qty ? $config->qty : 1);
                        }
                    }
                }
            }
        }

        return $this;
    }
}
