<?php
/**
 *
 */
namespace App\Services\Consolidation\DefaultConsolidator;
use App\Exceptions\ConsolidationException;
use App\Models\Hardware\Server;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Services\Consolidation\AbstractEnvironmentConsolidator;
use App\Services\Consolidation\Analyzer\AbstractAnalyzer;
use App\Services\Revenue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

abstract class AbstractDefaultConsolidator extends AbstractEnvironmentConsolidator
{
    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return \stdClass
     */
    public function consolidate(Environment $existingEnvironment, Environment $targetEnvironment): \stdClass
    {
        // Initial Utilization
        $envCpuUtilization = $existingEnvironment->getCpuUtilization();
        $envRamUtilization = $existingEnvironment->getRamUtilization();

        $cagrMult = $existingEnvironment->getCagrMultiplier();

        // Get existing servers chunks, in format:
        // Basically if a server has more than qty 1 it ends up getting duplicated
        // But when we do a comparison we only look at the first (index 0) server
        // in that chunk
        // [
        //    [
        //       ServerConfiguration X qty 1
        //       ServerConfiguration X qty 2
        //       ServerConfiguration X qty 3
        //    ],
        //    [
        //       ServerConfiguration Y qty 1
        //       ServerConfiguration Y qty 2
        //       ServerConfiguration Y qty 3
        //    ]
        // ]
        $existingServerChunks = $this->getExistingServerChunks($existingEnvironment);

        if (!$existingEnvironment->isExisting() && $existingEnvironment->isConverged()) {
            $this->_setNvnConvergedData($existingEnvironment, $existingServerChunks);
        }

        // Get target configs
        $targetConfigs = $this->getTargetConfigs($targetEnvironment);

        // Validate them
        $this->validateServerConfigurations($targetConfigs, $existingServerChunks, $existingEnvironment, $targetEnvironment);


        $existingEnvironment->cpuUtilMatch = true;
        $existingEnvironment->ramUtilMatch = true;

        $totalIops = 0;
        $totalStorage = 0;

        // Consolidate all the servers based off RAM & CPU & CPM constraints
        $consolidations = $this->consolidateServers($existingServerChunks, $targetConfigs, $envCpuUtilization, $envRamUtilization, $totalIops, $totalStorage, $cagrMult, $targetEnvironment, $existingEnvironment);

        $remainingStorage = 0;
        $storageConsolidation = [];
        $iopsConsolidation = [];
        $convReq = [];
        $realTotalIops = $totalIops;
        $realTotal = $totalStorage;

        if ($targetEnvironment->isConverged()) {
            $this->handleConvergedConstraints(
                $totalStorage,
                $remainingStorage,
                $totalIops,
                $realTotal,
                $realTotalIops,
                $consolidations,
                $targetConfigs,
                $storageConsolidation,
                $iopsConsolidation,
                $convReq,
                $existingEnvironment,
                $targetEnvironment
            );
        }

        $totals = $this->getTotals(
            $existingEnvironment,
            $targetEnvironment,
            $envCpuUtilization,
            $envRamUtilization,
            $remainingStorage,
            $totalStorage,
            $totalIops,
            $realTotal,
            $realTotalIops
        );

        $this->totalConsolidations($consolidations, $totals, $existingServerChunks, $targetEnvironment, $existingEnvironment);

        if ($targetEnvironment->isConverged()) {
            // We have additional constraints for converged
            // That potentially result in new nodes being required
            // And therefore new consolidations
            $this->totalStorageConsolidations($storageConsolidation, $totals)
                ->totalIopsConsolidations($iopsConsolidation, $totals)
                ->totalConvReq($convReq, $totals);
        }

        $this->calculateIncrease($totals, $existingEnvironment);

        $analysis = (object) [
            'consolidations' => $consolidations,
            'storage' => $storageConsolidation,
            'iops' => $iopsConsolidation,
            'converged' => $convReq,
            'totals' => $totals,
            'type' => 'Physical'
        ];

        foreach($analysis->consolidations as &$con) {
            foreach($con->servers as &$server) {
                $this->_unsetServerData($server);
            }
            foreach($con->targets as &$target) {
                $this->_unsetServerData($target);
            }
        }

        return $analysis;
    }

    /**
     * @param $server
     * @return $this
     */
    protected function _unsetServerData(&$server)
    {
        unset($server->os_annual_maintenance);
        unset($server->hdw_annual_maintenance);
        unset($server->discount_rate);
        unset($server->storage_type);
        unset($server->bandwidth_per_month);
        unset($server->created_at);
        unset($server->updated_at);
        unset($server->raw_storage_unit);
        unset($server->useable_storage_unit);
        unset($server->bandwidth_per_month_unit);
        unset($server->os_mod_id);
        unset($server->hypervisor_mod_id);
        unset($server->middleware_mod_id);
        unset($server->database_mod_id);
        unset($server->pending_review);

        if($server->server) {
            unset($server->server->created_at);
            unset($server->server->updated_at);
            unset($server->server->max_ram);
            unset($server->server->min_ram);
            unset($server->server->max_cpu);
            unset($server->server->min_cpu);
            unset($server->server->manufacturer_id);
        }

        if($server->manufacturer) {
            unset($server->manufacturer->created_at);
            unset($server->manufacturer->updated_at);
        }

        unset($server->processor->created_at);
        unset($server->processor->updated_at);
        unset($server->processor->announced_date);

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @return array|mixed
     */
    public function getTargetConfigs(Environment $targetEnvironment)
    {
        if ($targetEnvironment->isConverged()) {
            $targetConfigs = $this->consolidationHelper()->combineConverged($targetEnvironment->serverConfigurations);
        } else {
            $targetConfigs = $targetEnvironment->serverConfigurations;
        }

        $targetObj = new \stdClass();
        $targetObj->cpuUtilization = $targetEnvironment->cpu_utilization ? $targetEnvironment->cpu_utilization : 100;
        $targetObj->ramUtilization = $targetEnvironment->ram_utilization ? $targetEnvironment->ram_utilization : 100;
        $targetObj->variance = floatval($targetEnvironment->variance);

        //Walk through each target server (possible for converged-> Should be one for compute)
        $cache = [];
        $time = 0;
        foreach ($targetConfigs as &$config) {
            if ($config instanceof ServerConfiguration) {
                $config->setRealProcessor($cache, $time);
            }
            $config->processor;
            $config->baseRpm = $config->processor->rpm;
            $config->utilRpm = round($config->processor->rpm * ($targetObj->cpuUtilization / 100.0));
            $config->computedRpm = round(($config->processor->rpm * ($targetObj->cpuUtilization / 100.0)) * ((100 + $targetObj->variance) / 100.0));
            $config->utilRam = round($config->ram * ($targetObj->ramUtilization / 100.0));
            $config->computedRam = round(($config->ram * ($targetObj->ramUtilization / 100.0)) * ((100 + $targetObj->variance) / 100.0));
            if (!$config->is_converged) {
                $config->processor->total_cores = $config->processor->core_qty * $config->processor->socket_qty;
                if ($config instanceof ServerConfiguration) {
                    $config->is_hyperthreading_supported = $config->isHyperThreadingSupported();
                }
            }
        }

        Log::info("Time to AbstractDefaultConsolidator::setRealProcessor: " . ($time * 1000.0) . "ms");

        return $targetConfigs;
    }

    /**
     * @param $targetConfigs
     * @param $existingServerChunks
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return $this
     */
    public function validateServerConfigurations($targetConfigs, $existingServerChunks, Environment $existingEnvironment, Environment $targetEnvironment)
    {
        if (!count($targetConfigs) || !count($existingServerChunks)) {
            $err = (object)["message" => "Insufficient data to run analysis."];
            $err->environments = (object)[
                'existing' => (!count($existingServerChunks) ? $existingEnvironment->id : null),
                'target' => (!count($targetConfigs) ? $targetEnvironment->id : null),
                'existingName' => $existingEnvironment->name,
                'targetName' => $targetEnvironment->name,
                'existingIsExisting' => $existingEnvironment->is_existing
            ];
            throw $this->consolidationException($err->message, $err);
        }

        return $this;
    }

    /**
     * @param $serverArray
     * @return bool
     */
    protected function _serversExist($serverArray)
    {
        if (!$serverArray) {
            return false;
        }

        foreach ($serverArray as $servers) {
            if (count($servers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consolidate all the servers based off RAM & CPU & CPM constraints
     * @param $existingServerChunks
     * @param $targetConfigs
     * @param $envCpuUtilization
     * @param $envRamUtilization
     * @param $totalIops
     * @param $totalStorage
     * @param $cagrMult
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return array
     */
    public function consolidateServers($existingServerChunks, &$targetConfigs, $envCpuUtilization, $envRamUtilization, &$totalIops, &$totalStorage, $cagrMult, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        // These are all the consolidations we'll make
        $consolidations = [];

        // We continue to consolidate while we have existing server chunks
        // The exception to this is the `break` statement further down
        // - this statement is hit if we could not consolidate because there are no
        // - target configurations in the existing group
        // - in that case we punt on it and just don't include in the final output
        while($this->_serversExist($existingServerChunks)) {
            $additionalExisting = 0;
            // While we still have existing servers to consolidate
            //Set the RAM and RPM to 0 for this consolidation
            $currentRPM = 0;
            $currentRAM = 0;
            $perConsolidation = [];
            $targets = [];
            $consolidated = false;
            /** @var null|ServerConfiguration $targetConfig */
            $targetConfig = null;
            $targetObj = null;

            //Go through each of the separate existing servers
            /** @var ServerConfiguration[] $existingServers */
            foreach($existingServerChunks as &$existingServers) {

                if (!$this->_processExistingServers($existingServers)) {
                    continue;
                }

                /** @var ServerConfiguration $currentTargetConfig */
                $currentTargetConfig = $this->consolidationHelper()->matchTargetConstraints($existingServers[0], $targetConfigs);

                if (!$targetConfig && !$targetObj) {
                    $targetConfig = $currentTargetConfig;
                    $targetObj = new \stdClass();

                    $targetObj->cpuUtilization = $targetEnvironment->cpu_utilization ? $targetEnvironment->cpu_utilization : 100;
                    $targetObj->ramUtilization = $targetEnvironment->ram_utilization ? $targetEnvironment->ram_utilization : 100;
                    $targetObj->variance = floatval($targetEnvironment->variance);
                    $targetObj->maxRpm = $targetConfig->computedRpm;
                    $targetObj->maxRam = $targetConfig->computedRam;
                    $targetConfig->additionalExisting = 0;
                } else if ($currentTargetConfig->id !== $targetConfig->id) {
                    continue;
                }

                /** @var ServerConfiguration $existingServer */
                $existingServer = $existingServers[0];

                if(!$targetConfig->useable_storage) {
                    $targetConfig->useable_storage = $targetConfig->raw_storage / 2.0;
                }

                $cpuUtilization = $existingServer->cpu_utilization ? $existingServer->cpu_utilization : $envCpuUtilization;
                if ($existingServer->cpu_utilization && $existingServer->cpu_utilization != $envCpuUtilization) {
                    $existingEnvironment->cpuUtilMatch = false;
                }

                $ramUtilization = $existingServer->ram_utilization ? $existingServer->ram_utilization : $envRamUtilization;
                if ($existingServer->ram_utilization &&
                    $existingServer->ram_utilization != $envRamUtilization) {
                    $existingEnvironment->ramUtilMatch = false;
                }

                // Calculate the RPM and RAM used for the server
                $existingServer->baseRpm = round($existingServer->processor->rpm * $cagrMult);
                $existingServer->baseRam = round($existingServer->ram * $cagrMult);
                $existingServer->computedRpm = round($existingServer->baseRpm * ($cpuUtilization / 100.0));
                $existingServer->computedRam = round($existingServer->baseRam * ($ramUtilization / 100.0));

                /*
                 * General idea of persisting VMs through the process is that we have to "resize" the VM's number of cores based on the CPM values.
                 * However, the number of cores must be a whole number. For that reason, we have to actually "round up" partial core amounts to determine the actual CPM required for each VM.
                 * In the event the VM requires more CPM than a physical server can offer, we'll just fall back to the default functionality of duplicating the target X times to satisfy the VM constraints
                 * The comparison server is the attempt to abstract this functionality, in the event we ever want to also resize physical servers
                 * NOTE rpm = cpm
                 */
                /** @var ServerConfiguration $comparisonServer */
                $comparisonServer = $this->_getComparisonServer($existingServer, $targetConfig, $targetEnvironment);

                // Check if the existing server is larger than the target-> This may happen in the case of
                // converged to compute-> We also don't want to start trying to consolidate the targets into
                // existing if we already started the other way around
                $reverseConsolidation = false;
                $existingLargerThanTarget = $comparisonServer->computedRpm > $targetObj->maxRpm || $comparisonServer->computedRam > $targetObj->maxRam;
                $extraComparisonServers = 0;
                if (!$consolidated && $existingLargerThanTarget) {
                    $reverseConsolidation = true;
                    // Make sure this block of logic wasn't hit because RAM is not input
                    // If we have no RAM numbers we simply want to throw an exception
                    // The user needs to fix that
                    $this->validateServerConfigurationRam($existingServers, $targetObj, $existingEnvironment, $targetEnvironment);
                }

                if ($reverseConsolidation) {
                    // In the event our target is smaller than the existing
                    // Duplicate the target until we've satisfied ram and rpm
                    $i = 0;
                    while(($currentRPM <= $comparisonServer->computedRpm) || ($currentRAM <= $comparisonServer->computedRam)) {
                        // Increase the RAM and RPM
                        $currentRPM += $targetObj->maxRpm;
                        $currentRAM += $targetObj->maxRam;
                        $totalIops += $targetConfig->iops;
                        $totalStorage += $targetConfig->useable_storage;
                        // Add this server to the current consolidation
                        $targets[] = $targetConfig;
                        $consolidated = true;
                        if ($existingLargerThanTarget && $i++ > 0) {
                            $additionalExisting++;
                            $extraComparisonServers++;
                        }
                    }
                    // Add this server to the current consolidation
                    $perConsolidation[] = $existingServer;
                    // Remove this server from the list of servers that need to be consolidated
                    array_shift($existingServers);
                } else {
                    $comparisonServer->environment_name = $existingEnvironment->name;
                    $env = $comparisonServer->environment_detail;
                    $workload =$comparisonServer->workload_type;
                    $location = $comparisonServer->location;

                    $i = 0;
                    // Check if we're in the right environment (Production, Dev, etc)
                    while(count($existingServers) &&
                        (($currentRPM + $comparisonServer->computedRpm) <= $targetObj->maxRpm) &&
                        (($currentRAM + $comparisonServer->computedRam) <= $targetObj->maxRam) &&
                        ((!count($perConsolidation)) || (count($perConsolidation)
                                && $comparisonServer->environment_detail == $env
                                && $comparisonServer->workload_type == $workload
                                && $comparisonServer->location == $location))
                    ) {
                        // Increase the RAM and RPM
                        $currentRPM += $comparisonServer->computedRpm;
                        $currentRAM += $comparisonServer->computedRam;
                        // Add this server to the current consolidation
                        $perConsolidation[] = $existingServer;
                        // Remove this server from the list of servers that need to be consolidated
                        array_shift($existingServers);
                        $consolidated = true;
                        if ($existingLargerThanTarget && $i++ > 0) {
                            $additionalExisting++;
                            $extraComparisonServers++;
                        }
                    }
                    if (count($targets) === 0) {
                        $targets[] = $targetConfig;
                        $totalIops += $targetConfig->iops;
                        $totalStorage += $targetConfig->useable_storage;
                    }
                }

                $comparisonServer->extra_qty = max(0,$extraComparisonServers);
            }

            // We couldn't find a way to consolidate the servers-> Probably because at least one of the existing
            // environment servers didn't have a target in the required group (environment, workload, etc)
            // this is most likely the result of bad user input
            if (!$consolidated) {
                break;
            }

            //$targetEnvironment->processor
            $consolidation = (object) array(
                'servers' => $perConsolidation,
                'targets' => $targets,
                'additionalExisting' => $additionalExisting,
                'collapsed' => false
            );
            //Once we've tried to fit all the servers in, add the servers found to a single consolidation
            $consolidations[] = $consolidation;
        }

        return $consolidations;
    }

    /**
     * @param $existingServers
     * @param $targetObj
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return $this
     */
    public function validateServerConfigurationRam($existingServers, $targetObj, Environment $existingEnvironment, Environment $targetEnvironment)
    {
        if(!$existingServers[0]->computedRam || !$targetObj->maxRam) {
            $err = (object)["message" => "Insufficient data to run analysis."];
            $err->environments = (object)[
                'existing' => ((!$existingServers[0]->computedRam) ? $existingEnvironment->id : null),
                'target' => ((!$targetObj->maxRam) ? $targetEnvironment->id : null),
                'existingName' => $existingEnvironment->name,
                'targetName' => $targetEnvironment->name,
                'existingIsExisting' => $existingEnvironment->isExisting()
            ];
            throw $this->consolidationException($err->message, $err);
        }

        return $this;
    }

    /***
     * Determine if there are additional nodes necessary for converged based on IOPS or Storage
     * @param $totalStorage
     * @param $remainingStorage
     * @param $totalIops
     * @param $realTotal
     * @param $realTotalIops
     * @param $consolidations
     * @param $targetConfigs
     * @param $storageConsolidation
     * @param $iopsConsolidation
     * @param $convReq
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return $this
     */
    public function handleConvergedConstraints(&$totalStorage, &$remainingStorage, &$totalIops, &$realTotal, &$realTotalIops, &$consolidations, &$targetConfigs, &$storageConsolidation, &$iopsConsolidation, &$convReq, Environment $existingEnvironment, Environment $targetEnvironment)
    {
        $totalStorage = round($totalStorage, 2);
        $conf = $targetConfigs[0];
        if(!$conf->useable_storage) {
            $conf->useable_storage = ($conf->raw_storage / 2.0);
        }
        if($totalStorage < $existingEnvironment->useable_storage) {
            $remainingStorage = $existingEnvironment->useable_storage - $totalStorage;
            $numConfigs = $conf->useable_storage > 0 ? ceil($remainingStorage / $conf->useable_storage) : $conf->useable_storage;
            $realTotal = $totalStorage + $conf->useable_storage * $numConfigs;
            for($i = 0; $i < $numConfigs; ++$i) {
                $storageConsolidation[] = $conf;
            }
        }

        $realTotal = $totalStorage + count($storageConsolidation) * $conf->useable_storage;
        $totalIops = $totalIops + count($storageConsolidation) * $conf->iops;

        if(intval($conf->iops) && $realTotalIops < $existingEnvironment->iops) {
            $remainingIops = $existingEnvironment->iops - $totalIops;
            $numConfigs = ceil($remainingIops / $conf->iops);
            $realTotalIops = $totalIops + $conf->iops * $numConfigs;
            for($i = 0; $i < $numConfigs; ++$i) {
                $iopsConsolidation[] = $conf;
            }
        }

        if (!count($iopsConsolidation) || !count($storageConsolidation)) {
            $nodeQty = 0;
            foreach ($consolidations as $consolidation) {
                foreach ($consolidation->targets as $target) {
                    foreach ($target->configs as $config) {
                        $nodeQty += $config->qty;
                    }
                }
            }
        }

        $realTotal = $realTotal + count($iopsConsolidation) * $conf->useable_storage;
        $realTotalIops = $totalIops + count($iopsConsolidation) * $conf->iops;

        return $this;
    }

    /**
     * @param $consolidations
     * @param $totals
     * @param $existingServerChunks
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function totalConsolidations(&$consolidations, &$totals, $existingServerChunks, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        foreach($consolidations as $consolidation) {
            $consolidation->ramTotal = 0;
            $consolidation->computedRamTotal = 0;
            $consolidation->rpmTotal = 0;
            $consolidation->comparisonRpmTotal = 0;
            $consolidation->computedRpmTotal = 0;
            $consolidation->comparisonComputedRpmTotal = 0;
            $consolidation->targetRamTotal = 0;
            $consolidation->targetComputedRamTotal = 0;
            $consolidation->targetUtilRamTotal = 0;
            $consolidation->targetRpmTotal = 0;
            $consolidation->targetComputedRpmTotal = 0;
            $consolidation->targetUtilRpmTotal = 0;
            $consolidation->comparisonRamTotal = 0;
            $consolidation->comparisonComputedRamTotal = 0;
            $consolidation->comparisonCores = 0;

            /** @var ServerConfiguration $server */
            foreach($consolidation->servers as $rawServer) {
                if (!($rawServer instanceof ServerConfiguration)) {
                    $server = new ServerConfiguration();
                    foreach((array)$rawServer as $k => $v) {
                        $server->{$k} = $v;
                    }
                } else {
                    $server = $rawServer;
                }
                $consolidation->ramTotal += $server->baseRam;
                $consolidation->computedRamTotal += $server->computedRam;
                $consolidation->rpmTotal += $server->baseRpm;
                $consolidation->comparisonRpmTotal += $server->getComparisonServer()->baseRpm;
                $consolidation->computedRpmTotal += $server->computedRpm;
                $consolidation->comparisonComputedRpmTotal += $server->getComparisonServer()->computedRpm;
                $consolidation->comparisonRamTotal += $server->getComparisonServer()->baseRam;
                $consolidation->comparisonComputedRamTotal += $server->getComparisonServer()->computedRam;

                $this->_totalExistingConsolidation($consolidation, $server);
                $this->_totalExistingServer($totals, $server);
            }
            foreach($consolidation->targets as $target) {
                $consolidation->targetComputedRamTotal += $target->computedRam;
                $consolidation->targetComputedRpmTotal += $target->computedRpm;


                $consolidation->targetRamTotal += $target->ram;
                $consolidation->targetRpmTotal += $target->processor->rpm;

                $consolidation->targetUtilRpmTotal += $target->utilRpm;
                $consolidation->targetUtilRamTotal += $target->utilRam;

                $totals->target->socket_qty += $this->consolidationHelper()->sumSockets($target->processor->socket_qty);
                $totals->target->total_cores += $target->processor->total_cores;
                $totals->target->licensed_cores += intval($target->licensed_cores);
                $totals->target->physical_cores += $target->processor->total_cores;
                if(!!$target->configs) {
                    foreach($target->configs as $config) {
                        $totals->target->servers += $config->qty;
                    }
                } else {
                    $totals->target->servers++;
                }

                $this->_totalTargetServer($totals, $server);
            }
            $totals->existing->ram += $consolidation->ramTotal;
            $totals->existing->computedRam += $consolidation->computedRamTotal;
            $totals->target->ram += $consolidation->targetRamTotal;
            $totals->target->computedRam += $consolidation->targetComputedRamTotal;
            $totals->target->utilRam += $consolidation->targetUtilRamTotal;

            $totals->existing->rpm += $consolidation->rpmTotal;
            $totals->existing->comparisonRpm += $consolidation->comparisonRpmTotal;
            $totals->existing->computedRpm += $consolidation->computedRpmTotal;
            $totals->existing->comparisonComputedRpm += $consolidation->comparisonComputedRpmTotal;
            $totals->existing->comparisonComputedRam += $consolidation->comparisonComputedRamTotal;
            $totals->existing->comparisonCores += $consolidation->comparisonCores;
            $totals->target->rpm += $consolidation->targetRpmTotal;
            $totals->target->computedRpm += $consolidation->targetComputedRpmTotal;
            $totals->target->utilRpm += $consolidation->targetUtilRpmTotal;
            $this->_totalConsolidation($totals, $consolidation);
        }


        $this->_totalExistingEnvironment($totals, $targetEnvironment, $existingEnvironment);

        return $this;
    }

    /**
     * @param $totals
     * @param $existingEnvironment
     */
    public function calculateIncrease(&$totals, $existingEnvironment)
    {
        $existing_cpm = $existingEnvironment->existing_environment_type === 'physical_servers_vm'
            ? $totals->existing->physical_rpm
            : $totals->existing->computedRpm;

        $target_cpm = $totals->target->utilRpm;

        $existing_ram = $totals->existing->computedRam;

        $target_ram = $totals->target->utilRam;

        if ($existing_cpm < $target_cpm) {
            $cpm_performance_increase = number_format(100 * (($target_cpm - $existing_cpm) / ($existing_cpm > 0 ? $existing_cpm : 1)));

            $totals->target->cpm_performance_increase = $cpm_performance_increase > 0 ? $cpm_performance_increase . '%' : 'N/A';
        }

        $totals->target->ram_capacity_increase = 'N/A';

        if ($existing_ram < $target_ram) {
            $totals->target->ram_capacity_increase = number_format(100 * (($target_ram - $existing_ram) / ($existing_ram > 0 ? $existing_ram : 1))) . '%';
        }
    }

    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @param $envCpuUtilization
     * @param $envRamUtilization
     * @param $remainingStorage
     * @param $totalStorage
     * @param $totalIops
     * @param $realTotal
     * @param $realTotalIops
     * @return object
     */
    public function getTotals(Environment $existingEnvironment, Environment $targetEnvironment, $envCpuUtilization, $envRamUtilization, $remainingStorage, $totalStorage, $totalIops, $realTotal, $realTotalIops)
    {
        return (object) [
            'existing' => (object) [
                'servers' => 0,
                'socket_qty' => 0,
                'total_cores' => 0,
                'physical_cores' => 0,
                'ram' => 0,
                'physical_ram' => 0,
                'computedRam' => 0,
                'physical_rpm' => 0,
                'rpm' => 0,
                'comparisonRpm' => 0,
                'computedRpm' => 0,
                'comparisonRam' => 0,
                'comparisonComputedRam' => 0,
                'comparisonComputedRpm' => 0,
                'comparisonCores' => 0,
                'cpuMatch' => $existingEnvironment->cpuUtilMatch,
                'ramMatch' => $existingEnvironment->ramUtilMatch,
                'cpuUtilization' => $envCpuUtilization,
                'ramUtilization' => $envRamUtilization
            ], 'target' => (object) [
                'servers' => 0,
                'socket_qty' => 0,
                'physical_cores' => 0,
                'total_cores' => 0,
                'licensed_cores' => 0,
                'ram' => 0,
                'computedRam' => 0,
                'utilRam' => 0,
                'rpm' => 0,
                'computedRpm' => 0,
                'utilRpm' => 0,
                'cpuUtilization' => $targetEnvironment->getCpuUtilization()
            ], 'storage' => (object) [
                'deficit' => $remainingStorage,
                'existing' => $existingEnvironment->useable_storage,
                'target' => $totalStorage,
                'targetTotal' => $realTotal
            ], 'iops' => (object) [
                'deficit' => $existingEnvironment->iops - $totalIops,
                'existing' => $existingEnvironment->iops,
                'target' => $totalIops,
                'targetTotal' => $realTotalIops
            ]
        ];
    }

    /**
     * @param $target
     * @return mixed
     */
    protected function _getTotalCores($target)
    {
        return $target->processor->total_cores;;
    }

    /**
     * @param $consolidation
     * @param $server
     * @return $this
     */
    protected function _totalExistingConsolidation($consolidation, ServerConfiguration $server)
    {
        return $this;
    }

    /**
     * @param $target
     * @return mixed
     */
    protected function _getPhysicalCores($target)
    {
        return $target->processor->total_cores;
    }

    /**
     * @param $storageConsolidation
     * @param $totals
     * @return $this
     */
    public function totalStorageConsolidations(&$storageConsolidation, &$totals)
    {
        foreach($storageConsolidation as $target) {
            $totals->target->computedRam += $target->computedRam;
            $totals->target->computedRpm += $target->computedRpm;

            $totals->target->ram += $target->ram;
            $totals->target->rpm += $target->processor->rpm;

            $totals->target->utilRpm += $target->utilRpm;
            $totals->target->utilRam += $target->utilRam;

            $totals->target->socket_qty += $this->consolidationHelper()->sumSockets($target->processor->socket_qty);
            $totals->target->physical_cores += $this->_getPhysicalCores($target);
            $totals->target->total_cores += $this->_getTotalCores($target);
            if(!!$target->configs) {
                foreach($target->configs as $config) {
                    $totals->target->servers += $config->qty;
                }
            } else {
                $totals->target->servers++;
            }
        }

        return $this;
    }

    /**
     * @param $iopsConsolidation
     * @param $totals
     * @return $this
     */
    public function totalIopsConsolidations(&$iopsConsolidation, &$totals)
    {
        foreach($iopsConsolidation as $target) {
            $totals->target->computedRam += $target->computedRam;
            $totals->target->computedRpm += $target->computedRpm;

            $totals->target->ram += $target->ram;
            $totals->target->rpm += $target->processor->rpm;

            $totals->target->utilRpm += $target->utilRpm;
            $totals->target->utilRam += $target->utilRam;

            $totals->target->socket_qty += $this->consolidationHelper()->sumSockets($target->processor->socket_qty);
            $totals->target->physical_cores += $this->_getPhysicalCores($target);
            $totals->target->total_cores += $this->_getTotalCores($target);
            if(!!$target->configs) {
                foreach($target->configs as $config) {
                    $totals->target->servers += $config->qty;
                }
            } else {
                $totals->target->servers++;
            }
        }

        return $this;
    }

    /**
     * @param $convReq
     * @param $totals
     * @return $this
     */
    public function totalConvReq(&$convReq, &$totals)
    {
        foreach($convReq as $target) {
            $totals->target->computedRam += $target->computedRam;
            $totals->target->computedRpm += $target->computedRpm;

            $totals->target->ram += $target->ram;
            $totals->target->rpm += $target->processor->rpm;

            $totals->target->utilRpm += $target->utilRpm;
            $totals->target->utilRam += $target->utilRam;

            $totals->target->socket_qty += $this->consolidationHelper()->sumSockets($target->processor->socket_qty);
            $totals->target->physical_cores += $this->_getPhysicalCores($target);
            $totals->target->total_cores += $this->_getTotalCores($target);
            if(!!$target->configs) {
                foreach($target->configs as $config) {
                    $totals->target->servers += $config->qty;
                }
            } else {
                $totals->target->servers++;
            }
        }

        return $this;
    }

    /**
     * @param ServerConfiguration $existingServer
     * @param ServerConfiguration $targetConfig
     * @param Environment $targetEnvironment
     * @return ServerConfiguration
     */
    protected function _getComparisonServer($existingServer, $targetConfig, Environment $targetEnvironment)
    {
        return $existingServer;
    }

    /**
     * @param Environment $existingEnvironment
     * @param $existingServerChunks
     */
    protected function _setNvnConvergedData(Environment $existingEnvironment, $existingServerChunks)
    {
        $useableStorage = 0.00;
        $iops = 0;
        foreach($existingServerChunks as $chunk) {
            /** @var \stdClass $node */
            foreach($chunk as $node) {
                if (!$node->useable_storage && $node->raw_storage) {
                    $node->useable_storage = $node->raw_storage / 2.00;
                }
                $useableStorage += $node->useable_storage;
                $iops += $node->iops ?: 0;
            }
        }

        if (intval($iops)) {
            $existingEnvironment->iops = $iops;
        }

        if (floatval($useableStorage)) {
            $existingEnvironment->useable_storage = round($useableStorage,2);
        }
    }
}