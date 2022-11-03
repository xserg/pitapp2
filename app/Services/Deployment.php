<?php
/**
 *
 */

namespace App\Services;


use Carbon\Carbon;

class Deployment
{
    /**
     *
     */
    const CONFIG_PATH = 'deployment_last';

    /**
     * @var ConfigData
     */
    protected $configDataService;

    /**
     * @var string
     */
    protected $_deploymentKey;

    public function __construct(ConfigData $configDataService)
    {
        $this->configDataService = $configDataService;
    }

    /**
     * @return string
     */
    public function getDeploymentKey()
    {
        if (is_null($this->_deploymentKey)) {
            $this->_deploymentKey = md5($this->getLastDeployment());
        }

        return $this->_deploymentKey;
    }

    /**
     * @return string
     */
    public function getLastDeployment()
    {
        if (\App::environment('local')) {
          //   Always use current date time in development
            return $this->getCurrentDateTime();
        }

        if (!$this->configDataService->hasConfig(self::CONFIG_PATH)) {
            $this->setLastDeployment();
        }

        return $this->configDataService->getConfig(self::CONFIG_PATH);
    }

    /**
     * @return $this
     */
    public function setLastDeployment()
    {
        $lastDeployment = $this->getCurrentDateTime();
        $this->configDataService->setConfig(self::CONFIG_PATH, $lastDeployment);
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrentDateTime()
    {
        return Carbon::now('UTC')->toDateTimeString();
    }
}