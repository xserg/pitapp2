<?php
/**
 *
 */

namespace App\Services\Analysis\Environment\Target;


use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\AzureAds;
use App\Models\Hardware\Server;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Models\Project\Provider;
use App\Models\Software\Software;
use Illuminate\Support\Facades\Log as LLog;

class CloudTargetAnalyzer extends AbstractTargetAnalyzer
{
    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function analyze(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $targetEnvironment->setCloudInstanceType();

        parent::analyze($targetEnvironment, $existingEnvironment);

        $targetEnvironment->setCloudSummary([
            'purchase' => $targetEnvironment->purchase_price,
            'support' => $targetEnvironment->total_hardware_maintenance,
            'purchase_support' => $targetEnvironment->purchase_price + $targetEnvironment->total_hardware_maintenance,
            'type' => $targetEnvironment->lowest_price
        ]);

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $consolidationMap = [];

        // (1) Bandwidth / network costs
        $this->pricingService()->cloudBandwidthCalculator()->calculateCosts($targetEnvironment);

        // (2) Base monthly storage costs (prices per unit)
        $this->pricingService()->cloudStorageCalculator()->determineMonthlyStorageCosts($targetEnvironment, $existingEnvironment );

        // (3) Consolidate alike AmazonServers
        foreach($targetEnvironment->analysis->consolidations as $consolidation) {
            //Find the different costs for this server with the specific OS
            $target = $consolidation->targets[0];

            if ($consolidation->servers[0]->is_converged) {
                // If New vs New with converged, the middleware / database information may be on the first non-appliance node in use in the app
                $consolidation->servers[0]->middleware_id = $consolidation->servers[0]->configs[0]->middleware_id;
                $consolidation->servers[0]->database_id = $consolidation->servers[0]->configs[0]->database_id;
            }


            $target->instances = count($consolidation->targets);
            $target->middlewareInstances = $consolidation->targets[0]->middleware_id ? (count($consolidation->targets)): 0;
            $target->databaseInstances = $consolidation->targets[0]->database_id ? (count($consolidation->targets)): 0;

            $this->addToConsolidationMap($consolidationMap, $target);
        }

        // (4) Calculate costs for each target
        $this->pricingService()->cloudInstanceCalculator()->calculateCosts($consolidationMap, $targetEnvironment, $existingEnvironment);

        // (5) Calculate totalStorageCosts
        $this->pricingService()->cloudStorageCalculator()->calculateCosts($targetEnvironment, $existingEnvironment);

        // (6) Now find the lowest cost contract
        // Not sure why so much code is needed for this, since the longer the term + more up front will always be the cheapest
        $this->pricingService()->cloudInstanceLowestCostCalculator()->calculateLowestCosts($consolidationMap, $targetEnvironment, $existingEnvironment);

        $targetEnvironment->consolidationMap = $consolidationMap;

        // (7) Now comes calculating software costs

        $this->pricingService()->cloudSoftwareCalculator()->calculateCosts($consolidationMap, $targetEnvironment, $existingEnvironment);

        // (8) FTE Costs
        $this->calculateFteCosts($targetEnvironment);

        $paymentOptionId = $targetEnvironment->payment_option_id;

        // set the targetEnvironment payment option
        $targetEnvironment->payment_option = $targetEnvironment->isAws() ? AmazonServer::getAmazonPaymentOptionById($targetEnvironment->payment_option_id) :
            ($targetEnvironment->isAzure() ? AmazonServer::getAzurePaymentOptionById($paymentOptionId) :
                                             ($targetEnvironment->isGoogle() ? AmazonServer::getGooglePaymentOptionById($targetEnvironment->payment_option_id) : 
                    AmazonServer::getIBMPVSPaymentOptionById($targetEnvironment->payment_option_id)));

        foreach($consolidationMap as &$target) {
            $template = $this->getCloudServerDescription($target, $targetEnvironment);
            if ($targetEnvironment->isAws()) {
                $target->payment_option = AmazonServer::getAmazonPaymentOptionById($targetEnvironment->payment_option_id);
                $target->cloudServerDescription = str_replace("{{type}}", " - " . $target->payment_option['name'], $template);
                $target->cloudServerDescriptionOnDemand = str_replace("{{type}}", "On-Demand", $template);
                $target->cloudServerDescriptionPartialUpfront = str_replace("{{type}}", "Reserved Instance - Partial Upfront", $template);
            } else if ($targetEnvironment->isAzure()) {
                $paymentOptionId = $paymentOptionId = property_exists($target, 'computedPaymentOptionId')
                    ? $target->computedPaymentOptionId
                    : $targetEnvironment->payment_option_id;
                $target->payment_option = AmazonServer::getAzurePaymentOptionById($paymentOptionId);
                $target->cloudServerDescription = str_replace("{{type}}", " - " . $target->payment_option['name'], $template);
                $target->cloudServerDescriptionOnDemand = str_replace("{{type}}", "On-Demand", $template);
                $target->cloudServerDescription1Year = str_replace("{{type}}", "1-Year Reserved", $template);
                $target->cloudServerDescription3Year = str_replace("{{type}}", "3-Year Reserved", $template);
            } else if ($targetEnvironment->isGoogle()) {
              $target->payment_option = AmazonServer::getGooglePaymentOptionById($targetEnvironment->payment_option_id);
              $target->cloudServerDescription = str_replace("{{type}}", " - " . $target->payment_option['name'], $template);
              $target->cloudServerDescriptionOnDemand = str_replace("{{type}}", "On demand", $template);
              $target->cloudServerDescription1Year = str_replace("{{type}}", "1 year commitment", $template);
              $target->cloudServerDescription3Year = str_replace("{{type}}", "3 year commitment", $template);
            } else {
              $target->payment_option = AmazonServer::getIBMPVSPaymentOptionById($targetEnvironment->payment_option_id);
              $target->cloudServerDescription = str_replace("{{type}}", " - " . $target->payment_option['name'], $template);
              $target->cloudServerDescriptionOnDemand = str_replace("{{type}}", "On demand", $template);
            }
        }

        return $this;
    }

    /**
     * @param Environment $environment
     * @return $this|\App\Services\Analysis\Environment\AbstractAnalyzer|AbstractTargetAnalyzer
     */
    public function setEnvironmentDefaults(Environment $environment)
    {
        parent::setEnvironmentDefaults($environment);

        $environment->cost_per_year = 0;
        $environment->power_cost = 0;
        $environment->power_cost_per_year = 0;
        $environment->power_cost_formula = "0";

        $environment->total_fte_cost = $environment->fte_salary * $environment->fte_qty * $environment->project->support_years;

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this|AbstractTargetAnalyzer
     */
    public function copyBeforeExistingEnvironmentData(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        parent::copyBeforeExistingEnvironmentData($targetEnvironment, $existingEnvironment);

        $targetEnvironment->total_storage = $existingEnvironment->useable_storage;
        $targetEnvironment->useable_storage = $existingEnvironment->useable_storage;

        return $this;
    }

    /**
     * @return \App\Helpers\Analysis\Cloud
     */
    public function cloudHelper()
    {
        return resolve(\App\Helpers\Analysis\Cloud::class);
    }

    /**
     * @param $map
     * @param \stdClass $target
     * @return CloudTargetAnalyzer
     */
    public function addToConsolidationMap(&$map, $target)
    {
        foreach ($map as &$server) {
            if ($server->instance_type != $target->instance_type) {
                continue;
            }
            if ($server->instance_type !== AzureAds::INSTANCE_TYPE_ADS
                && $server->name == $target->name
                && $server->os_name == $target->os_name
                && $target->os_id == $server->os_id
                && $target->os_li_name == $server->os_li_name
                && $target->middleware_id == $server->middleware_id
                && $target->database_id == $server->database_id
                && $target->database_li_name == $server->database_li_name)
            {
                $server->instances += $target->instances;
                $server->middlewareInstances += $target->middlewareInstances;
                $server->databaseInstances += $target->databaseInstances;
                return $this;
            } else if ($server->instance_type === AzureAds::INSTANCE_TYPE_ADS && AzureAds::doAzureDatabaseInstancesMatch($server, $target)) {
                $server->instances += $target->instances;
                return $this;
            }
        }
        $map[] = $target;

        return $this;
    }

    /**
     * @param $target
     * @param Environment $targetEnvironment
     * @return string
     */
    public function getCloudServerDescription($target, Environment $targetEnvironment)
    {
        $parts = collect([
            $this->_updateName($targetEnvironment->name, $target) . " {{type}}",
            $this->_getCloudOSDescription($target, $targetEnvironment),
            $this->_getCloudMiddlewareDescription($target, $targetEnvironment),
            $this->_getCloudDbDescription($target, $targetEnvironment)
        ])->filter(function($item){
            return strlen($item) > 0;
        })->all();


        return implode(" - ", $parts) . ' Cost';
    }

    /**
     * @param string $name
     * @param $target
     * @return string
     */
    protected function _updateName(string $name, $target)
    {
        return $name;
    }

    /**
     * @param $target
     * @param Environment $targetEnvironment
     * @return string
     */
    protected function _getCloudOSDescription($target, Environment $targetEnvironment)
    {
        if ($target->os_li_name) {
            return $this->mapLIName($target->os_li_name) . ' (OS LI)';
        }


        if ($target->os_name && ($targetEnvironment->isAws() || !$target->database_li_name)) {
            $osName = $target->os_name;
            return $osName . ' (OS BYOL)';
        }

        if ($targetEnvironment->isAzure() && $target->database_li_name && $this->_isAzureOs($target->database_li_name)) {
            return $target->database_li_name . ' (LI)';
        }

        if ($targetEnvironment->isGoogle() && $target->database_li_name) {
            return $target->database_li_name . ' (LI)';
        }

        if ($targetEnvironment->isIBMPVS() && $target->database_li_name) {
            return $target->database_li_name . ' (LI)';
        }

        return '';
    }

    /**
     * @param $target
     * @param Environment $targetEnvironment
     * @return string
     */
    protected function _getCloudMiddlewareDescription($target, Environment $targetEnvironment)
    {
        if ($target->middleware_id) {
            try {
                $software = Software::findOrFail($target->middleware_id);
                return $software->name . ' (MD BYOL)';
            } catch (\Throwable $e) {
                return '';
            }
        }

        return '';
    }

    /**
     * @param $target
     * @param Environment $targetEnvironment
     * @return string
     */
    protected function _getCloudDbDescription($target, Environment $targetEnvironment)
    {
        if ($targetEnvironment->isAws()) {
            if ($target->database_li_name) {
                return $this->mapLIName($target->database_li_name) . ' (DB LI)';
            }

            if ($target->pre_installed_sw && trim(strtolower($target->pre_installed_sw)) !== 'na') {
                return $target->pre_installed_sw . ' (DB PRE-INSTALL LI)';
            }

            if ($target->database) {
                return $target->database->name . ' (DB BYOL)';
            }

            return '';
        } else if ($targetEnvironment->isAzure()) {

            $str = '';

            if ($target->database) {
                $str .= $target->database->name . ' (DB BYOL)';
            }

            if ($target->database_li_name && $this->_isAzureDb($target->database_li_name)) {
                $str .= (strlen($str) ? ' ' : '') . $target->database_li_name . ' (DB LI)';
            }

            if ($target->instance_type === AzureAds::INSTANCE_TYPE_ADS) {
                $str .= " ({$target->database_type} LI)";
            }

            return $str;
        }
    }

    /**
     * @param $name
     * @return string
     */
    public function mapLIName($name) {
        switch($name) {
            case "Oracle Standard One":
                return "Oracle Standard Edition 1 (LI-RDS)";
            case "Oracle Standard Two":
                return "Oracle Standard Edition 2 (LI-RDS)";
            case "MySQL":
                return "MySQL (LI-RDS)";
            case "Amazon Aurora":
                return "Amazon Aurora (LI-RDS)";
            case "Aurora MySQL":
                return "Aurora MySQL (LI-RDS)";
            case "Aurora PostgreSQL":
                return "Aurora PostgreSQL (LI-RDS)";
            case "PostGreSQL":
                return "PostGreSQL (LI-RDS)";
            case "MariaDB":
                return "MariaDB (LI-RDS)";
            case "SQL Server Standard":
                return "Microsoft SQL Server Standard (LI-RDS)";
            case "SQL Server Enterprise":
                return "Microsoft SQL Server Enterprise (LI-RDS)";
            default:
                return $name;
        }
    }

    /**
     * @param $software
     * @return bool
     */
    protected function _isAzureOs($software)
    {
        return !$this->_isAzureDb($software);
    }

    protected function _isAzureDb($software)
    {
        return preg_match('/(SQL Server|BizTalk|Sharepoint)/i', $software);
    }
}