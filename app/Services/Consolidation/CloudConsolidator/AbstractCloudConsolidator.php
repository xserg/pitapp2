<?php
/**
 *
 */

namespace App\Services\Consolidation\CloudConsolidator;

use App\Exceptions\ConsolidationException;
use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\AzureAds;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Cloud\InstanceCategory;
use App\Models\Project\Environment;
use App\Services\Consolidation\AbstractEnvironmentConsolidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class AbstractCloudConsolidator extends AbstractEnvironmentConsolidator
{

    public function consolidate(Environment $existingEnvironment, Environment $targetEnvironment): \stdClass
    {
        $t0 = microtime(true);
        $cpuUtilization = $existingEnvironment->cpu_utilization ? $existingEnvironment->cpu_utilization : 50;
        $ramUtilization = $existingEnvironment->ram_utilization ? $existingEnvironment->ram_utilization : 100;
        $existingEnvironmentConfigs = $this->getExistingServerConfigurations($existingEnvironment->serverConfigurations);
        Log::info("              Time to AbstractCloudConsolidator::consolidate (1): " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);

        $tmpVmConsolidations = false;
        $existingEnvironmentServers = [];

        if ($existingEnvironment->isPhysical() && $targetEnvironment->vms) {
            // Legacy VM support is only allowed on physical environments
            $tmpVmConsolidations = $this->_addLegacyVmServers($existingEnvironmentServers, $existingEnvironmentConfigs, $existingEnvironment, $targetEnvironment);
        } else {
            $this->_addPhysicalServers($existingEnvironmentServers, $existingEnvironmentConfigs, $existingEnvironment, $targetEnvironment);
        }

        Log::info("              Time to AbstractCloudConsolidator::consolidate (2): " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);

        $queryCache = [];
        $ec2Consolidations = [];
        $existingEnvironment->cpuUtilMatch = true;
        $existingEnvironment->ramUtilMatch = true;

        foreach ($existingEnvironmentServers as &$servers) {
            if (!$this->_processExistingServers($servers)) {
                continue;
            }
            foreach ($servers as &$server) {
                $currentTargetConfig = $this->consolidationHelper()->matchTargetConstraints($server, $targetEnvironment->serverConfigurations);
                $this->_consolidateCloud($existingEnvironment, $targetEnvironment, $server, $currentTargetConfig, $ec2Consolidations, $queryCache);
            }
        }

        Log::info("              Time to AbstractCloudConsolidator::consolidate (3): " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);

        $ec2Totals = $this->totalCloudConsolidations($ec2Consolidations, $cpuUtilization, $ramUtilization, $targetEnvironment, $existingEnvironment);

        Log::info("              Time to AbstractCloudConsolidator::consolidate (4): " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);

        if ($targetEnvironment->vms && $tmpVmConsolidations) {
            $this->_handleTmpVmConsolidations($ec2Totals, $tmpVmConsolidations, $cpuUtilization, $ramUtilization, $targetEnvironment, $existingEnvironment);
        }

        if (!empty($ec2Totals)) {
            $this->calculateIncrease($ec2Totals, $existingEnvironment, $targetEnvironment);
        }

        Log::info("              Time to AbstractCloudConsolidator::consolidate (5): " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);

        $analysis = (object)[
            'existing' => $existingEnvironment,
            'target' => $targetEnvironment,
            'consolidations' => $ec2Consolidations,
            'totals' => $ec2Totals,
            'type' => 'AWS',
        ];

        return $analysis;
    }

    /**
     * @param $consolidations
     * @param $cpuUtilization
     * @param $ramUtilization
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return null|object
     */
    public function totalCloudConsolidations($consolidations, $cpuUtilization, $ramUtilization, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        // Return null if no consolidations provided
        if (count($consolidations) == 0) {
            return null;
        }

        $totals = (object)[
            'existing' => (object)[
                'servers' => 0,
                'socket_qty' => 0,
                'total_cores' => 0,
                'ram' => 0,
                'computedRam' => 0,
                'rpm' => 0,
                'computedRpm' => 0,
                'cores' => 0,
                'computedCores' => 0,
                'cpuMatch' => $existingEnvironment->cpuUtilMatch,
                'ramMatch' => $existingEnvironment->ramUtilMatch,
                'cpuUtilization' => $cpuUtilization,
                'ramUtilization' => $ramUtilization,
                'physical_cores' => 0,
                'physical_ram' => 0,
                'vm_cores' => 0,
                'physical_computed_cores' => 0,
                'vms' => 0,
                'physicalCoresUtil' => 0,
                'physicalRamUtil' => 0
            ],
            'target' => (object)[
                'servers' => 0,
                'total_cores' => 0,
                'licensed_cores' => 0,
                'ram' => 0,
                'vms' => 0,
                'rpm' => 0,
                'cpuUtilization' => $targetEnvironment->getCpuUtilization()
            ]
        ];

        foreach ($consolidations as $consolidation) {
            $consolidation->ramTotal = 0;
            $consolidation->computedRamTotal = 0;
            $consolidation->rpmTotal = 0;
            $consolidation->computedRpmTotal = 0;
            $consolidation->targetRamTotal = 0;
            $consolidation->targetRpmTotal = 0;

            $consolidation->coreTotal = 0;
            $consolidation->computedCoreTotal = 0;

            foreach ($consolidation->servers as $server) {

                // IBM VM Cores should be increments of .05
                if ($server->manufacturer && $server->manufacturer->name == "IBM") {
                    $server->vm_cores = ceil($server->vm_cores * 1000 / 50) * 50 / 1000;
                }
                $consolidation->ramTotal += $server->baseRam;
                $consolidation->computedRamTotal += $server->computedRam;
                $consolidation->rpmTotal += $server->baseRpm;
                $consolidation->computedRpmTotal += $server->computedRpm;

                $consolidation->coreTotal += $server->baseCores;
                $consolidation->computedCoreTotal += $server->computedCores;

                $this->_totalExistingServer($totals, $server);
            }

            foreach ($consolidation->targets as &$target) {
                $consolidation->targetRamTotal += $target->ram;
                $consolidation->targetRpmTotal += intval($target->cpm);
                $totals->target->ram += $target->ram;
                $totals->target->total_cores += $target->vcpu_qty;
                $target->processor = (object)['total_cores' => $target->vcpu_qty, 'name' => $target->name];
                if ($target->instance_type == AzureAds::INSTANCE_TYPE_ADS) {
                    $target->processor->ghz = $target->clock_speed;
                    $target->server = (object)['name' => $target->service_type . ' / ' . $target->category];
                }
                // $totals->target->servers++;
                $this->_totalTargetServer($totals, $server);
            }
            $totals->existing->ram += $consolidation->ramTotal;
            $totals->existing->computedRam += $consolidation->computedRamTotal;

            $totals->existing->cores += $consolidation->coreTotal;
            $totals->existing->computedCores += $consolidation->computedCoreTotal;

            $totals->existing->rpm += $consolidation->rpmTotal;
            $totals->existing->computedRpm += $consolidation->computedRpmTotal;

            $totals->target->rpm += $consolidation->targetRpmTotal;

            $this->_totalConsolidation($totals, $consolidation);
        }

        $totals->target->utilRam = $totals->target->ram;

        $this->_totalExistingEnvironment($totals, $targetEnvironment, $existingEnvironment);

        $totals->existing->physicalCoresUtil = $totals->existing->physical_cores * ($totals->existing->cpuUtilization / 100);
        $totals->existing->physicalRamUtil = $totals->existing->physical_ram * ($totals->existing->ramUtilization / 100);

        $totals->target->physical_cores = 0;
        $totals->existing->comparisonCores = $totals->target->total_cores;

        return $totals;
    }

    /**
     * @param $totals
     * @param $existingEnvironment
     */
    public function calculateIncrease(&$totals, $existingEnvironment, $targetEnvironment)
    {
        if ($targetEnvironment->isIBMPVS()) {
            $existing_cpm = $existingEnvironment->existing_environment_type === 'physical_servers_vm'
                ? $totals->existing->physical_rpm
                : $totals->existing->computedRpm;

            $target_cpm = $totals->target->rpm;

            if ($existing_cpm < $target_cpm) {
                $totals->target->cpm_performance_increase = number_format(100 * (($target_cpm - $existing_cpm) / ($existing_cpm > 0 ? $existing_cpm : 1))) . '%';
            }
        }

        $existing_ram = $totals->existing->computedRam;

        $target_ram = $totals->target->utilRam;

        $totals->target->ram_capacity_increase = 'N/A';

        if ($existing_ram < $target_ram) {
            $totals->target->ram_capacity_increase = number_format(100 * (($target_ram - $existing_ram) / ($existing_ram > 0 ? $existing_ram : 1))) . '%';
        }
    }

    /**
     * @param $existing
     * @param $target
     * @param $server
     * @param $currentTargetConfig
     * @param $consolidations
     * @return $this
     * @throws ConsolidationException
     */
    protected function _consolidateCloud(Environment $existing, Environment $target, &$server, $currentTargetConfig, &$consolidations, &$queryCache)
    {
        $cpuUtilization = $existing->getCpuUtilization();
        $ramUtilization = $existing->getRamUtilization();

        if ($server->cpu_utilization && $server->cpu_utilization != $cpuUtilization) {
            $existing->cpuUtilMatch = false;
        }

        if ($server->ram_utilization && $server->ram_utilization != $ramUtilization) {
            $existing->ramUtilMatch = false;
        }

        $cpuUtilization = $server->cpu_utilization ? $server->cpu_utilization : $cpuUtilization;
        $ramUtilization = $server->ram_utilization ? $server->ram_utilization : $ramUtilization;

        $variance = floatval($target->variance);
        $variance = (100 + $variance) / 100;

        $instanceType = $this->_determineInstanceType($target, $currentTargetConfig, $server);

        $osName = $this->_determineOs($currentTargetConfig);


        $cagrMult = $existing->getCagrMultiplier();

        $server->baseRpm = $server->processor ? round($server->processor->rpm * $cagrMult) : 0;
        $server->baseRam = round($server->ram * $cagrMult);
        $server->baseCores = $server->isVm() ? round($server->vm_cores * $cagrMult, 2) : ($server->processor ? round($server->getTotalCores() * $cagrMult) : 0);
        $server->computedRpm = round($server->baseRpm * ($cpuUtilization / 100.0));
        $server->computedRam = ceil($server->baseRam * ($ramUtilization / 100.0));
        // ceil() is important for several reasons, but one of them is because
        // as of December '18 we're going to allow fractional cores on processors that support them
        // (ie, oracle, fujitsu, IBM)
        $server->computedCores = $server->baseCores * ($cpuUtilization / 100.0);
        $cores = ceil($server->baseCores * ($cpuUtilization / 100.0) * (1.0 / $variance));
        $ram = ($server->baseRam * ($ramUtilization / 100) * (1.0 / $variance));

        $consolidation = (object)[
            'servers' => [$server],
            'targets' => [],
            'additionalExisting' => 0
        ];

        $includeHybridPricing = false;
        if ($instanceType === AmazonServer::INSTANCE_TYPE_AZURE || $instanceType === AzureAds::INSTANCE_TYPE_ADS) {
            $includeHybridPricing = AmazonServer::getAzurePaymentOptionById($target->payment_option_id)['is_hybrid'];
        }

        switch ($instanceType) {
            case AmazonServer::INSTANCE_TYPE_EC2:
                $amazonServerQuery = $this->_getEc2Query($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName);
                break;
            case AmazonServer::INSTANCE_TYPE_RDS:
                $amazonServerQuery = $this->_getRdsQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName);
                break;
            case AmazonServer::INSTANCE_TYPE_AZURE:
                $amazonServerQuery = $this->_getAzureQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $includeHybridPricing);
                break;
            case AzureAds::INSTANCE_TYPE_ADS:
                return $this->handleAds($consolidation, $consolidations, $cores, $ram, $currentTargetConfig, $target, $includeHybridPricing);
                break;
            case AmazonServer::INSTANCE_TYPE_GOOGLE:
                $amazonServerQuery = $this->_getGoogleQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName);
                break;
            case AmazonServer::INSTANCE_TYPE_IBMPVS:
                $amazonServerQuery = $this->_getIBMPVSQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, null, $server);
                break;
            default:
                throw $this->consolidationException("Invalid instance type: " . $instanceType);
                break;
        }

        $cacheKey = json_encode(["sql" => $amazonServerQuery->toSql(), "bindings" => $amazonServerQuery->getBindings()]);

        if (config('app.debug')) {
            logger('File: ' . __FILE__ . PHP_EOL . 'Function: ' . __FUNCTION__ . PHP_EOL . 'Line:' . __LINE__);
            logger($cacheKey);
        }

        if (array_key_exists($cacheKey, $queryCache)) {
            $cachedValue = $queryCache[$cacheKey];
            if ($cachedValue) {
                $amazonServer = clone $cachedValue;
            } else {
                $amazonServer = null;
            }
        } else {
            $amazonServer = $amazonServerQuery->first();
            $queryCache[$cacheKey] = $amazonServer;
        }

        //If we do, cool, "consolidation" done.
        if ($amazonServer != null) {
            $amazonServer->utilRam = $amazonServer->ram;
            $amazonServer->os_id = $currentTargetConfig->os_id;
            $amazonServer->os_li_name = $currentTargetConfig->os_li_name;
            $amazonServer->middleware_id = $currentTargetConfig->middleware_id;
            $amazonServer->database_id = $currentTargetConfig->database_id;
            $amazonServer->database_li_name = $currentTargetConfig->database_li_name;
            $consolidation->targets[] = $amazonServer;
        } else {
            // todo Fix this default for nothing found sending back the largest server
            // If we don't have something that fits the specs
            // Get the largest server we can
            $amazonServer = $this->_getLargestServer($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $includeHybridPricing);
            if (!$amazonServer) {
                return;
            }
            //Find how many servers we need to fit the existing server.
            $neededForRam = ceil($ram / $amazonServer->ram);
            $neededForCores = ceil($cores / $amazonServer->vcpu_qty);
            $serversNeeded = max($neededForRam, $neededForCores);
            $amazonServer->utilRam = $amazonServer->ram;
            $amazonServer->os_id = $currentTargetConfig->os_id;
            $amazonServer->os_li_name = $currentTargetConfig->os_li_name;
            $amazonServer->middleware_id = $currentTargetConfig->middleware_id;
            $amazonServer->database_id = $currentTargetConfig->database_id;
            $amazonServer->database_li_name = $currentTargetConfig->database_li_name;
            for ($i = 0; $i < $serversNeeded; ++$i) {
                $consolidation->targets[] = $amazonServer;
            }

            $consolidation->additionalExisting += $serversNeeded - 1;
        }

        $consolidations[] = $consolidation;

        return $this;
    }

    /**
     * Add to the existing environment servers.
     * NOTE - `vm` here does not refer to the special hybrid or vm-only existing environment types
     * It's legacy support for a general number of VMS not associated to specific VM definitions
     * @param $existingEnvironmentServers
     * @param $existingEnvironmentConfigs
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return $this
     */
    protected function _addLegacyVmServers(&$existingEnvironmentServers, $existingEnvironmentConfigs, Environment $existingEnvironment, Environment $targetEnvironment)
    {
        $dbServers = 0;
        $dbId = null;
        $serverArray = [];
        foreach ($existingEnvironmentConfigs as &$config) {
            $dbServers += !!$config->database_id;
            $dbId = !$dbId ? $config->database_id : $dbId;
            $config->qty = $config->qty ? $config->qty : 1;
            $config->processor->total_cores = $config->processor->core_qty * $config->processor->socket_qty;
        }

        $allConfig = [];
        foreach ($existingEnvironmentConfigs as $config) {
            for ($c = 0; $c < $config->qty; $c++) {
                $cc = json_encode($config);
                $clone = json_decode($cc);
                unset($cc);
                $allConfig[] = $clone;
            }
        }
        $vmsPerServer = floor((float)$targetEnvironment->vms / count($allConfig));
        $leftover = $targetEnvironment->vms % count($allConfig);
        $cpuUtilization = $existingEnvironment->cpu_utilization ? $existingEnvironment->cpu_utilization : 50;
        $ramUtilization = $existingEnvironment->ram_utilization ? $existingEnvironment->ram_utilization : 100;
        $tmp = [];
        foreach ($allConfig as $c) {
            $cpuUtilization = $c->cpu_utilization ? $c->cpu_utilization : $cpuUtilization;
            $ramUtilization = $c->ram_utilization ? $c->ram_utilization : $ramUtilization;
            $cagrMult = 1;
            if ($existingEnvironment->project->cagr) {
                for ($i = 0; $i < $existingEnvironment->project->support_years; ++$i) {
                    $cagrMult *= 1 + ($existingEnvironment->project->cagr / 100.0);
                }
            }

            $cc = json_encode($c);
            $clone = json_decode($cc);
            unset($cc);

            $clone->baseRpm = round($clone->processor->rpm * $cagrMult);
            $clone->baseRam = round($clone->ram * $cagrMult);
            $clone->baseCores = round($clone->processor->total_cores * $cagrMult);
            $clone->computedRpm = round($clone->baseRpm * ($cpuUtilization / 100.0));
            $clone->computedRam = round($clone->baseRam * ($ramUtilization / 100.0));
            $clone->computedCores = $clone->baseCores * ($cpuUtilization / 100.0);
            $tmp[] = $clone;
            $tmpConsolidations = [(object)['servers' => $tmp, 'targets' => []]];
        }

        foreach ($allConfig as $index => &$config) {
            $vms = $vmsPerServer + ($index < $leftover);
            $config->processor->total_cores = ceil($config->processor->total_cores / (float)$vms);
            $config->processor->socket_qty = 1;
            $config->ram = ceil($config->ram / (float)$vms);
        }
        $totalConfigs = count($allConfig);

        for ($i = 0; $i < $targetEnvironment->vms; $i++) {
            $index = floor(((float)$i / $targetEnvironment->vms) * $totalConfigs);
            $serverArray[] = $allConfig[$index];
        }

        foreach ($serverArray as &$server) {
            $oldServer = (array)$server;
            $server = new ServerConfiguration();
            foreach ($oldServer as $k => $v) {
                $server->{$k} = $v;
            }
        }

        $existingEnvironmentServers[] = $serverArray;

        return $tmpConsolidations;
    }

    /**
     * @param $existingEnvironmentServers
     * @param $existingEnvironmentConfigs
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return $this
     */
    protected function _addPhysicalServers(&$existingEnvironmentServers, $existingEnvironmentConfigs, Environment $existingEnvironment, Environment $targetEnvironment)
    {
        $totalConfigs = 0;
        foreach ($existingEnvironmentConfigs as &$server) {
            $serverArray = [];
            if (!$server->is_converged && $server->processor) {
                $server->processor->total_cores = $server->processor->core_qty * $server->processor->socket_qty;
            }
            //sanity check
            $server->qty = $server->qty ? $server->qty : 1;
            for ($i = 0; $i < $server->qty; ++$i) {
                $serverArray[] = $server;
                $totalConfigs++;
            }
            $existingEnvironmentServers[] = $serverArray;
        }

        return $this;
    }

    /**
     * @param $ec2Totals
     * @param $tmpConsolidations
     * @param $cpuUtilization
     * @param $ramUtilization
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    protected function _handleTmpVmConsolidations(&$ec2Totals, &$tmpConsolidations, $cpuUtilization, $ramUtilization, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $existingEnvironmentTotals = $this->totalCloudConsolidations($tmpConsolidations, $cpuUtilization, $ramUtilization, $targetEnvironment, $existingEnvironment);
        //Replace the totals with values pulled from the actual hardware instead of those used in the VM calculations
        if ($ec2Totals) {
            $ec2Totals->existing->computedRpm = $existingEnvironmentTotals->existing->computedRpm;
            $ec2Totals->existing->computedRam = $existingEnvironmentTotals->existing->computedRam;
            $ec2Totals->existing->ram = $existingEnvironmentTotals->existing->ram;
            $ec2Totals->existing->socket_qty = $existingEnvironmentTotals->existing->socket_qty;
            $ec2Totals->existing->servers = $existingEnvironmentTotals->existing->servers;
            $ec2Totals->existing->total_cores = $existingEnvironmentTotals->existing->total_cores;
            $ec2Totals->existing->cores = $existingEnvironmentTotals->existing->cores;
            $ec2Totals->existing->computedCores = $existingEnvironmentTotals->existing->computedCores;
        }

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param $currentTargetConfig
     * @param $server
     * @return string
     */
    protected function _determineInstanceType(Environment $targetEnvironment, $currentTargetConfig, $server)
    {
        if ($targetEnvironment->provider && $targetEnvironment->isAzure()) {
            return $currentTargetConfig->ads_database_type
                ? AzureAds::INSTANCE_TYPE_ADS
                : AmazonServer::INSTANCE_TYPE_AZURE;
        } else if ($targetEnvironment->provider && $targetEnvironment->isGoogle()) {
            return AmazonServer::INSTANCE_TYPE_GOOGLE;
        } else if ($targetEnvironment->provider && $targetEnvironment->isIBMPVS()) {
            return AmazonServer::INSTANCE_TYPE_IBMPVS;
        }

        if ($currentTargetConfig->database_li_name) {
            return AmazonServer::INSTANCE_TYPE_RDS;
        }

        return AmazonServer::INSTANCE_TYPE_EC2;
    }

    /**
     * @param $currentTargetConfig
     * @return string
     */
    protected function _determineOs($currentTargetConfig)
    {
        if ($currentTargetConfig->os_li_name) {
            return $currentTargetConfig->os_li_name;
        }

        return 'Linux';
    }

    /**
     * Gets the hybrid pricing server if applicable
     * @param $query
     * @return Builder
     */
    protected function includeHybridPricing(Builder $query)
    {
        $queryWithHybrid = clone $query;
        $queryWithHybrid->where('is_hybrid', '=', 1);
        return $queryWithHybrid->first() ? $queryWithHybrid : $query;
    }

    /**
     * @param $cores
     * @param $ram
     * @param $instanceType
     * @param $currentTargetConfig
     * @param $target
     * @param $osName
     * @param null $amazonServerQuery
     * @param bool $ignoreLicenseModel
     * @return Builder
     */
    protected function _getEc2Query($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $amazonServerQuery = null, $ignoreLicenseModel = false)
    {
        $paymentOption = AmazonServer::getAmazonPaymentOptionById($target->payment_option_id);

        $amazonServerQuery = $amazonServerQuery ?? $this->_getCloudQuery($cores, $ram, $instanceType, $currentTargetConfig, $target);
        $amazonServerQuery->whereRaw('? LIKE CONCAT("%", os_name, "%")', [$osName]);

        if ($paymentOption['id'] != 1) {
            $amazonServerQuery->where('purchase_option', '=', $paymentOption['purchase_option'])
                ->where('offering_class', '=', $paymentOption['offering_class'])
                ->where('lease_contract_length', '=', $paymentOption['lease_contract_length']);
        } else {
            $amazonServerQuery->where('term_type', '=', 'OnDemand');
        }

        if ($currentTargetConfig->database_li_ec2) {
            $amazonServerQuery = $amazonServerQuery->where('pre_installed_sw', '=', $currentTargetConfig->database_li_ec2);
        }

        if (!$ignoreLicenseModel) {
            if ($currentTargetConfig->os_li_name) {
                $amazonServerQuery->where('license_model', 'No License required');
            }
        }

        return $amazonServerQuery;
    }

    /**
     * @param $cores
     * @param $ram
     * @param $instanceType
     * @param $currentTargetConfig
     * @param $osName
     * @param null $amazonServerQuery
     * @return Builder
     */
    protected function _getRdsQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $amazonServerQuery = null)
    {
        $paymentOption = AmazonServer::getAmazonPaymentOptionById($target->payment_option_id);

        if ($currentTargetConfig->database_li_name == "Amazon Aurora") {
            $currentTargetConfig->database_li_name = "Aurora MySQL";
        }

        $database_engine = $currentTargetConfig->database_li_name;

        $is_byol = strpos($currentTargetConfig->database_li_name, 'BYOL') !== false;

        if ($is_byol) {
            $database_engine = trim(str_replace('BYOL', '', $database_engine));
        }

        $amazonServerQuery = $amazonServerQuery ?? $this->_getCloudQuery($cores, $ram, $instanceType, $currentTargetConfig, $target);
        $amazonServerQuery->whereRaw('TRIM(CONCAT( database_engine, " ", database_edition)) LIKE CONCAT(?,"%")', [$database_engine])
            ->where('license_model', $is_byol ? '=' : '!=', 'Bring your own license');

        if ($currentTargetConfig->database_li_name) {
            $do = $currentTargetConfig->deployment_option ? $currentTargetConfig->deployment_option : "Single-AZ";
            $amazonServerQuery = $amazonServerQuery->where('deployment_option', 'LIKE', $do . "%");
        }

        if ($paymentOption['id'] != 1) {
            $amazonServerQuery->where('purchase_option', '=', $paymentOption['purchase_option'])
                ->where('offering_class', '=', $paymentOption['offering_class'])
                ->where('lease_contract_length', '=', $paymentOption['lease_contract_length'])
                ->where('price_per_unit', '>', 0);
        } else {
            $amazonServerQuery->where('term_type', '=', 'OnDemand');
        }

        return $amazonServerQuery;
    }

    /**
     * @param $cores
     * @param $ram
     * @param $instanceType
     * @param $currentTargetConfig
     * @param $osName
     * @param null $amazonServerQuery
     * @param bool $includeHybridPricing
     * @return Builder
     */
    protected function _getAzureQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $includeHybridPricing, $amazonServerQuery = null)
    {
        $paymentOption = AmazonServer::getAzurePaymentOptionById($target->payment_option_id);

        $amazonServerQuery = $amazonServerQuery ?? $this->_getCloudQuery($cores, $ram, $instanceType, $currentTargetConfig, $target);

        if ($currentTargetConfig->database_li_name) {
            $amazonServerQuery = $amazonServerQuery->where('software_type', '=', $currentTargetConfig->database_li_name);
        } else {
            $amazonServerQuery = $amazonServerQuery->whereIn('software_type', ['CentOS or Ubuntu Linux', 'CentOS or Ubuntu']);
        }

        $amazonServerQuery->where('term_type', '=', $paymentOption['term_type'])
            ->where('lease_contract_length', '=', $paymentOption['lease_contract_length']);

        return $includeHybridPricing ? $this->includeHybridPricing($amazonServerQuery) : $amazonServerQuery;
    }

    /**
     * @param $cores
     * @param $ram
     * @param $instanceType
     * @param $currentTargetConfig
     * @param $osName
     * @param null $amazonServerQuery
     * @return Builder
     */
    protected function _getGoogleQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $amazonServerQuery = null)
    {
        $paymentOption = AmazonServer::getGooglePaymentOptionById($target->payment_option_id);

        $amazonServerQuery = $amazonServerQuery ?? $this->_getCloudQuery($cores, $ram, $instanceType, $currentTargetConfig, $target);

        $amazonServerQuery = $amazonServerQuery->where('software_type', 'Debian, CentOS, CoreOS, Ubuntu, User Provided OS');

        $amazonServerQuery->where('term_type', '=', $paymentOption['term_type'])
            ->where('lease_contract_length', '=', $paymentOption['lease_contract_length']);

        return $amazonServerQuery;
    }

    /**
     * @param $cores
     * @param $ram
     * @param $instanceType
     * @param $currentTargetConfig
     * @param $osName
     * @param null $amazonServerQuery
     * @return Builder
     */
    protected function _getIBMPVSQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $amazonServerQuery = null, $server = null)
    {
        $paymentOption = AmazonServer::getIBMPVSPaymentOptionById($target->payment_option_id);

        $amazonServerQuery = $amazonServerQuery ?? $this->_getCloudQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $server);

        if (!empty($currentTargetConfig->database_li_name)) {
            $amazonServerQuery = $amazonServerQuery->where('software_type', $currentTargetConfig->database_li_name);
        }

        return $amazonServerQuery;
    }

    private function columns(): array
    {
        return [
            'id',
            'name',
            'vcpu_qty',
            'ram',
            'server_type',
            'environment_id',
            'instance_type',
            'os_name',
            'price_per_unit',
            'price_unit',
            'purchase_option',
            'offering_class',
            'lease_contract_length',
            'term_type',
            'location',
            'physical_processor',
            'instance_family',
            'clock_speed',
            'storage',
            'license_model',
            'engine_code',
            'pre_installed_sw',
            'database_engine',
            'database_edition',
            'deployment_option',
            'software_type',
            'is_hybrid',
            'cpm'
        ];
    }

    /**
     * @param $cores
     * @param $ram
     * @param $instanceType
     * @param $currentTargetConfig
     * @param $target
     * @param null $server
     * @return Builder
     */
    protected function _getCloudQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $server = null)
    {
        $columns =
        //See if there are any servers the same size or bigger than the one we have.
        //Also pull specific fields so the size of the saved analysis is smaller
        $query = AmazonServer::query()->select($this->columns());

        if ($instanceType === AmazonServer::INSTANCE_TYPE_IBMPVS && isset($server)) {
            $server_cpm = isset($server->computedRpm) ? $server->computedRpm : $server->processor->rpm;
            $query = $query->where('cpm', '>=', $server_cpm);
        } else {
            $query = $query->where('vcpu_qty', '>=', $cores);
        }

        // Exclude AWS and Azure Burastable instances
//        if ($this->isExcludeBurastable($currentTargetConfig, $target)) {
//            $query->where('name', 'not like', 't%')
//                ->where('name', 'not like', 'B%');
//        }

        $query = $query->where('ram', '>=', $ram)
            ->where('instance_type', '=', $instanceType)
            //->whereRaw('? LIKE CONCAT("%", os_name, "%")', [$osName])
            ->orderBy('current_generation', 'desc')
            ->orderBy('price_per_unit', 'asc')
            ->orderBy('vcpu_qty', 'asc')
            ->orderBy('ram', 'asc')
            ->orderBy('cpm', 'asc')
            ->orderBy('deployment_option', 'desc'); // Grabs the singles first

        if ($instanceType == AmazonServer::INSTANCE_TYPE_IBMPVS) {
            $query = $query->where('location', 'LIKE', '%' . trim($target->region->name) . '%');
        } else {
            $query = $query->where('location', '=', trim($target->region->name));
        }

        if (!$currentTargetConfig->database_li_ec2) {
            $currentTargetConfig->database_li_ec2 = "NA";
        }

        if ($currentTargetConfig->instance_category) {
            if ($this->isSpecificInstanceCategory($target, $currentTargetConfig)) {
                $query = $query->where('name', 'like', $currentTargetConfig->instance_category . '%');
            } else {
                $query = $this->addInstanceFamilyClause($query, $currentTargetConfig);
            }
        }

        return $query;
    }

    /**
     * @param $instanceType
     * @param $currentTargetConfig
     * @param $osName
     * @param bool $includeHybridPricing
     * @return array|null|\stdClass
     */
    protected function _getLargestServer($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $includeHybridPricing)
    {
        //If it's bigger than the biggest server we have, grab the biggest one.
        $amazonServerQuery = AmazonServer::select($this->columns())
            ->orderBy('vcpu_qty', 'desc')
            ->orderBy('ram', 'desc')
            ->orderBy('cpm', 'desc')
            ->orderBy('price_per_unit', 'asc')
            ->where('instance_type', '=', $instanceType);

        if ($currentTargetConfig->instance_category) {
            $amazonServerQuery = $this->addInstanceFamilyClause(
                $amazonServerQuery,
                $currentTargetConfig
            );
        }

        switch ($instanceType) {
            case AmazonServer::INSTANCE_TYPE_EC2:
                // Important
                // This is the "fallback" server search
                // In this case we do not apply an EC2 license type filter
                $amazonServerQuery = $this->_getEc2Query($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $amazonServerQuery, true);
                break;
            case AmazonServer::INSTANCE_TYPE_RDS:
                $amazonServerQuery = $this->_getRdsQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $amazonServerQuery);
                break;
            case AmazonServer::INSTANCE_TYPE_AZURE:
                $amazonServerQuery = $this->_getAzureQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $includeHybridPricing, $amazonServerQuery);
                break;
            case AmazonServer::INSTANCE_TYPE_GOOGLE:
                $amazonServerQuery = $this->_getGoogleQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $amazonServerQuery);
                break;
            case AmazonServer::INSTANCE_TYPE_IBMPVS:
                $amazonServerQuery = $this->_getIBMPVSQuery($cores, $ram, $instanceType, $currentTargetConfig, $target, $osName, $amazonServerQuery);
                break;
        }

        return $amazonServerQuery->first();
    }

    /**
     * @param $cores
     * @param $ram
     * @param $database_type
     * @param $service_type
     * @param $category
     * @param $tier
     * @param $target
     * @param bool $includeHybridPricing
     * @return Builder
     */
    protected function _getAdsQuery($cores, $ram, $database_type, $service_type, $category, $tier)
    {
        $adsQuery = AzureAds::query()
            ->where('database_type', $database_type)
            ->where('service_type', $service_type);
            
        if (str_contains($category, 'Zone Redundant')) {
            /* limit query to only servers with "Zone Redundant" */
            $adsQuery->where('name', 'LIKE',  '%Zone Redundant%');
            
        } elseif (str_contains($category, 'Locally Redundant')) {
            /* eliminate servers with "Zone Redundant" from the query result */
            $adsQuery->where('name', 'NOT LIKE',  '%Zone Redundant%');
        }
        
        if ($cores) {
            $adsQuery->where('vcpu_qty', '>=', $cores);
                //* omit entries where memory equals 0 - serverles & provisioned tiers
                // ->where('vcpu_qty', '!=', 0);
                // ->whereNotNull('vcpu_qty');
        }

        if ($ram) {
            $adsQuery->where('ram', '>=', $ram);
                //* omit entries where memory equals 0 - serverles & provisioned tiers
                // ->where('ram', '!=', 0);
                // ->whereNotNull('ram');
        }

        if ($category) {
            $adsQuery->where('category', $category);
        }

        if ($tier) {
            $adsQuery->where('tier', $tier);
        }

        $adsQuery->whereNotNull('ram')
            ->where('ram', '!=', 0);
        $adsQuery->whereNotNull('vcpu_qty')
            ->where('vcpu_qty', '!=', 0);

        return $adsQuery;
    }

    /**
     * Find the closest sized Azure ADS instance based on the provided criteria
     *
     * Sample debugging output:
     * - select service_type, category, name, vcpu_qty, ram, price_per_unit FROM azure_ads where ram >= 22 and vcpu_qty >= 4 and service_type = 'Managed Instance' order by price_per_unit asc, vcpu_qty asc, ram asc limit 1;
     *
     * @param $consolidation
     * @param $consolidations
     * @param $cores
     * @param $ram
     * @param $currentTargetConfig
     * @param $target
     * @param bool $includeHybridPricing
     * @return $this
     */
    public function handleAds(&$consolidation, &$consolidations, $cores, $ram, &$currentTargetConfig, $target, $includeHybridPricing)
    {
        $adsQuery = $this->_getAdsQuery(
            $cores,
            $ram,
            $currentTargetConfig->ads_database_type,
            $currentTargetConfig->ads_service_type,
            $currentTargetConfig->instance_category,
            $currentTargetConfig->ads_compute_tier
        );

        list ($adsQuery, $computedPaymentOptionId) = $this->addPrisingClauses(
            $adsQuery,
            $currentTargetConfig,
            $target
        );

        $adsQuery->orderBy('price_per_unit', 'asc')
            ->orderBy('vcpu_qty', 'asc')
            ->orderBy('ram', 'asc');

        /** @var false|AzureAds $ads */
        $ads = $adsQuery->first();

        if ($ads) {
            $ads->utilRam = $ads->ram;
            $ads->instance_type = AzureAds::INSTANCE_TYPE_ADS;
            $ads->setAdsDefaults();

            if (isset($computedPaymentOptionId)) {
                $ads->computedPaymentOptionId = $computedPaymentOptionId;
            }

            $consolidation->targets[] = $ads;
        } else {
            $adsQuery = $this->_getAdsQuery(
                false,
                false,
                $currentTargetConfig->ads_database_type,
                $currentTargetConfig->ads_service_type,
                $currentTargetConfig->instance_category,
                $currentTargetConfig->ads_compute_tier
            );

            list ($adsQuery, $computedPaymentOptionId) = $this->addPrisingClauses(
                $adsQuery,
                $currentTargetConfig,
                $target
            );

            $adsQuery->orderBy('vcpu_qty', 'desc')
                ->orderBy('ram', 'desc')
                ->orderBy('price_per_unit', 'asc');

            /** @var false|AzureAds $ads */
            $ads = $adsQuery->first();

            if (!$ads) {
                return $this;
            }

            //Find how many servers we need to fit the existing server.
            $neededForRam = ceil($ram / $ads->ram);
            $neededForCores = ceil($cores / $ads->vcpu_qty);
            $serversNeeded = max($neededForRam, $neededForCores);
            $ads->utilRam = $ads->ram;
            $ads->instance_type = AzureAds::INSTANCE_TYPE_ADS;
            $ads->setAdsDefaults();

            if (isset($computedPaymentOptionId)) {
                $ads->computedPaymentOptionId = $computedPaymentOptionId;
            }

            for ($i = 0; $i < $serversNeeded; ++$i) {
                $consolidation->targets[] = $ads;
            }

            $consolidation->additionalExisting += $serversNeeded - 1;
        }

        $consolidations[] = $consolidation;

        return $this;
    }

    /**
     * Add `instance_family` where clause to provided query builder
     *
     * @param Builder $query A query builder instance
     * @param ServerConfiguration $serverConfig The target server configuration
     *
     * @return \Illuminate\Database\Eloquent\Builder The query with the `instance_family` clause appended
     */
    private function addInstanceFamilyClause($query, $serverConfig)
    {
        if ($serverConfig->instance_category == InstanceCategory::CATEGORY_SYSTEM_OPTIMIZED) {
            /* since "System Optimized" maps to all "*Optimized" instance categories,
             we want all servers which "instance_family" is contained in
             "System Optimized"'s list of intance categories */
            $query = $query->whereIn(
                'instance_family',
                InstanceCategory::SYSTEM_OPTIMIZED_CATEGORIES
            );

        } else {
            $query = $query->where(
                'instance_family',
                '=',
                $serverConfig->instance_category
            );
        }

        return $query;
    }

    private function addPrisingClauses($adsQuery, $targetConfig, $target) {
        $isNeedToDowngradePrising = $targetConfig->instance_category === 'Hyperscale'
            || $targetConfig->ads_service_type === 'Instance Pools';

        /*
         * If we didn't find a server with user's payments options
         * then we try to find a server with the downgraded payment options
         * and repeat downgrading until a server is found
         *
         * */
        if ($isNeedToDowngradePrising) {
            $prisingDowngradeMap = AmazonServer::AZURE_ADS_PRISING_DOWNGRADE;

            $paymentOptionId = $target->payment_option_id;

            $isServerExists = false;

            while(!$isServerExists) {
                $adsQueryTest = clone $adsQuery;

                $paymentOption = AmazonServer::getAzurePaymentOptionById($paymentOptionId);

                if ($paymentOption) {
                    $adsQueryTest->where([
                        ['term_type', '=', $paymentOption['term_type']],
                        ['term_length', '=', $paymentOption['lease_contract_length']],
                        ['is_hybrid', '=', $paymentOption['is_hybrid']],
                    ]);

                    $isServerExists = $adsQueryTest->exists();

                    if ($isServerExists) {
                        return [$adsQueryTest, $paymentOptionId];
                    }

                    if (key_exists($paymentOptionId, $prisingDowngradeMap)) {
                        $paymentOptionId = $prisingDowngradeMap[$paymentOptionId];
                    } else {
                        $targetConfig->custom = $paymentOptionId;

                        return [$adsQuery, $paymentOptionId];
                    }
                } else {
                    break;
                }
            }
        }

        $paymentOption = AmazonServer::getAzurePaymentOptionById($target->payment_option_id);

        $adsQuery->where('term_type', '=', $paymentOption['term_type'])
            ->where('term_length', '=', $paymentOption['lease_contract_length'])
            ->where('is_hybrid', '=', $paymentOption['is_hybrid']);

        return [$adsQuery, null];
    }

//    /**
//     * @param $targetConfig
//     * @param Environment $target
//     * @return bool
//     */
//    private function isExcludeBurastable($targetConfig, Environment $target): bool
//    {
//        if ($target->isAws() || $target->isAzure()) {
//            return isset($targetConfig->is_include_burastable) && $targetConfig->is_include_burastable !== 1;
//        }
//
//        return false;
//    }

    /**
     * Checks if given server config's instance category is contained in
     *  environment's provider `server_type`s
     * 
     * @param Environment $environment
     * @param ServerConfiguration $server_configuration
     * 
     * @return bool
     */
    private function isSpecificInstanceCategory(Environment $environment, ServerConfiguration $server_configuration): bool {
        $instance_categories = AmazonServer::getInstanceCategories();
        $instance_category = $server_configuration->instance_category;
        $provider_name = $environment->provider->name;

        return key_exists($provider_name, $instance_categories)
            && in_array($instance_category, $instance_categories[$provider_name]);
    }
}