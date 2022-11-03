<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use App\Services\Revenue;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Project;
use App\Models\Project\Environment;
use App\Models\Project\Log;
use App\Models\Hardware\Processor;
use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\SoftwareCost;
use SVGGraph;
use JangoBrick\SVG\SVGImage;
use PhpOffice\PhpWord\PhpWord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OldTargetAnalysisController extends Controller {

    protected $activity = 'Analysis';

    private function serversExist($serverArray) {
        foreach($serverArray as $servers) {
            if(count($servers))
              return true;
        }
        return false;
    }
    private function combineConverged(&$configs) {
        $withCombinedConverged = [];
        $parentIds = [];
        foreach($configs as $config) {
            if(!$config->is_converged && $config->parent_configuration_id == null)
                $withCombinedConverged[] = $config;
            else if($config->is_converged && !$config->parent_configuration_id) {
                $parentIds[] = $config->id;
            }
        }
        foreach($parentIds as $id) {
            $rpm = 0;
            $ram = 0;
            $childConfigs = [];
            $socket_qty = 0;
            $core_qty = [];
            $iops = 0;
            $total_cores = 0;
            $useable_storage = 0;
            $raw_storage = 0;
            $ghz = [];
            $processors = [];
            $parent;
            foreach($configs as $config) {
                if(/*$config->id == $id || */$config->parent_configuration_id == $id) {
                    $ram += $config->ram * $config->qty;
                    $rpm += $config->processor->rpm * $config->qty;
                    $socket_qty += $config->processor->socket_qty * $config->qty;
                    $useable_storage += $config->useable_storage * $config->qty;
                    $iops += $config->iops * $config->qty;
                    $raw_storage += $config->raw_storage * $config->qty;
                    $core_qty[] = $config->processor->core_qty;
                    $ghz[] = $config->processor->ghz;
                    $processors[] = $config->processor->name;
                    $childConfigs[] = $config;
                    $total_cores += $config->processor->socket_qty * $config->processor->core_qty * $config->qty;
                }
                if($config->id == $id) {
                    $cc = json_encode($config);
                    $parent = json_decode($cc);
                    unset($cc);
                    $parent->processor = new Processor;
                }
            }
            $parent->processor->rpm = $rpm;
            $parent->processor->ghz = implode(', ', $ghz);
            $parent->processor->socket_qty = $socket_qty;
            $parent->processor->total_cores = $total_cores;
            $parent->processor->name = implode(', ', $processors);
            $parent->ram = $ram;
            $parent->useable_storage = $useable_storage;
            $parent->iops = $iops;
            $parent->raw_storage = $raw_storage;
            $parent->configs = $childConfigs;
            $withCombinedConverged[] = $parent;
        }
        return $withCombinedConverged;
    }


    private function consolidateAmazon(&$existing, $target, &$server, $currentTargetConfig, $instanceType, &$consolidations) {
        $cpuUtilization = $existing->cpu_utilization ? $existing->cpu_utilization : 50;
        $ramUtilization = $existing->ram_utilization ? $existing->ram_utilization : 100;

        if($server->cpu_utilization && $server->cpu_utilization != $cpuUtilization) {
            $existing->cpuUtilMatch = false;
        }

        if($server->ram_utilization && $server->ram_utilization != $ramUtilization)
            $existing->ramUtilMatch = false;

        $cpuUtilization = $server->cpu_utilization ? $server->cpu_utilization : $cpuUtilization;
        $ramUtilization = $server->ram_utilization ? $server->ram_utilization : $ramUtilization;

        $variance = $target->variance ? $target->variance : 5;
        $variance = (100 + $variance) / 100;

        //$serverType = $server->database_id ? 'R4' : 'M4';
        //Only wants to use R4 for non-RDS now.
        $serverType = 'R4';
        //Only use RDS if we have a database
        //Otherwise, switch to EC2
        if(!$server->database_id) {
            $instanceType = "EC2";
        }

        if($target->provider->name == "Azure") {
            $instanceType = "Azure";
        }

        if($currentTargetConfig->os_li_name) {
            $osName = $currentTargetConfig->os_li_name;
        } else {
            $osName = "Linux";
        }
        $cagrMult = 1;
        if($existing->project->cagr) {
            for($i = 0; $i < $existing->project->support_years; ++$i) {
                $cagrMult *= 1 + ($existing->project->cagr / 100.0);
            }
        }
        $server->baseRpm = round($server->processor->rpm * $cagrMult);
        $server->baseRam = round($server->ram * $cagrMult);
        $server->baseCores = round($server->processor->total_cores * $cagrMult);
        $server->computedRpm = round($server->baseRpm * ($cpuUtilization / 100.0));
        //$server->utilRam;
        $server->computedRam = round($server->baseRam * ($ramUtilization / 100.0));
        $server->computedCores = ceil($server->baseCores * ($cpuUtilization / 100.0));

        $cores = ceil($server->baseCores * ($cpuUtilization / 100.0) * (1.0 / $variance));
        $ram = ($server->baseRam * ($ramUtilization / 100) * (1.0 / $variance));
        //print_r(" Util Cores: " . $cores . " Util Ram: " . $ram);
        $consolidation = (object)['servers' => [$server], 'targets' => []];
        //See if there are any servers the same size or bigger than the one we have.
        //Also pull specific fields so the size of the saved analysis is smaller
        $amazonServerQuery = AmazonServer::select('id', 'name', 'vcpu_qty', 'ram', 'server_type',
                                                  'environment_id', 'instance_type', 'os_name',
                                                  'price_per_unit', 'price_unit', 'purchase_option', 'term_type',
                                                  'location', 'physical_processor', 'instance_family',
                                                  'clock_speed', 'storage', 'license_model', 'engine_code', 'pre_installed_sw',
                                                  'database_engine', 'database_edition', 'deployment_option', 'software_type')
                                    ->where('vcpu_qty', '>=', $cores)
        //$amazonServerQuery = AmazonServer::where('vcpu_qty', '>=', $cores)
                                    ->where('ram', '>=', $ram)
                                    ->where('instance_type', '=', $instanceType)
                                    //->whereRaw('? LIKE CONCAT("%", os_name, "%")', [$osName])
                                    ->orderBy('price_per_unit', 'asc')
                                    ->orderBy('vcpu_qty', 'asc')
                                    ->orderBy('ram', 'asc')
                                    ->orderBy('deployment_option', 'desc'); // Grabs the singles first
                                    //->first();

        if(!$currentTargetConfig->database_li_ec2) {
            $currentTargetConfig->database_li_ec2 = "NA";
        }
        if($currentTargetConfig->instance_category) {
            $amazonServerQuery = $amazonServerQuery->where('instance_family', '=', $currentTargetConfig->instance_category);
        }

        if($instanceType == "EC2") {
            $amazonServerQuery = $amazonServerQuery->whereRaw('? LIKE CONCAT("%", os_name, "%")', [$osName]);
                                                    //->where('server_type', '=', $serverType);
            if($currentTargetConfig->database_li_ec2) {
                $amazonServerQuery = $amazonServerQuery->where('pre_installed_sw', '=', $currentTargetConfig->database_li_ec2);
            }

        } else if($instanceType == "RDS") {
            if($currentTargetConfig->database_li_name == "Amazon Aurora") {
                $currentTargetConfig->database_li_name = "Aurora MySQL";
            }
            $amazonServerQuery = $amazonServerQuery->whereRaw('TRIM(CONCAT( database_engine, " ", database_edition)) LIKE CONCAT(?,"%")', [$currentTargetConfig->database_li_name])
                                                    ->where('license_model', '!=', 'Bring your own license');
            if($currentTargetConfig->database_li_name) {
                $do = $currentTargetConfig->deployment_option ? $currentTargetConfig->deployment_option : "Single-AZ";
                $amazonServerQuery = $amazonServerQuery->where('deployment_option', 'LIKE', $do . "%");
            }
        } else {
            if($currentTargetConfig->database_li_name) {
                $amazonServerQuery = $amazonServerQuery->where('software_type', '=', $currentTargetConfig->database_li_name);
            } else {
                $amazonServerQuery = $amazonServerQuery->where('software_type', '=', 'CentOS');
            }
        }
        //DB::enableQueryLog();
        $amazonServer = $amazonServerQuery->first();
        //If we do, cool, "consolidation" done.
        if($amazonServer != null) {
            $amazonServer->utilRam = $amazonServer->ram;
            $amazonServer->os_id = $currentTargetConfig->os_id;
            $amazonServer->os_li_name = $currentTargetConfig->os_li_name;
            $amazonServer->middleware_id = $currentTargetConfig->middleware_id;
            $amazonServer->database_id = $currentTargetConfig->database_id;
            $amazonServer->database_li_name = $currentTargetConfig->database_li_name;
            $consolidation->targets[] = $amazonServer;
        } else {
            //If it's bigger than the biggest server we have, grab the biggest one.
            $amazonServerQuery = AmazonServer::select('id', 'name', 'vcpu_qty', 'ram', 'server_type',
                                                      'environment_id', 'instance_type', 'os_name',
                                                      'price_per_unit', 'price_unit', 'purchase_option', 'term_type',
                                                      'location', 'physical_processor', 'instance_family',
                                                      'clock_speed', 'storage', 'license_model', 'engine_code', 'pre_installed_sw',
                                                      'database_engine', 'database_edition', 'deployment_option', 'software_type')
                                              ->orderBy('vcpu_qty', 'desc')
            //$amazonServerQuery = AmazonServer::orderBy('vcpu_qty', 'desc')
                                              ->orderBy('ram', 'desc')
                                              ->orderBy('price_per_unit', 'asc')
                                              ->where('instance_type', '=', $instanceType);

            if($currentTargetConfig->instance_category) {
                $amazonServerQuery = $amazonServerQuery->where('instance_family', '=', $currentTargetConfig->instance_category);
            }

            if($instanceType == "EC2") {
                $amazonServerQuery = $amazonServerQuery->whereRaw('? LIKE CONCAT("%", os_name, "%")', [$osName]);
                                                        //->where('server_type', '=', $serverType);
                if($currentTargetConfig->database_li_ec2)
                    $amazonServerQuery = $amazonServerQuery->where('pre_installed_sw', '=', $currentTargetConfig->database_li_ec2);
            } else if($instanceType == "RDS") {
                if($currentTargetConfig->database_li_name == "Amazon Aurora") {
                    $currentTargetConfig->database_li_name = "Aurora MySQL";
                }
                $amazonServerQuery = $amazonServerQuery->whereRaw('TRIM(CONCAT( database_engine, " ", database_edition)) LIKE CONCAT(?,"%")', [$currentTargetConfig->database_li_name])
                                                        ->where('license_model', '!=', 'Bring your own license');
                if($currentTargetConfig->database_li_name) {
                    $do = $currentTargetConfig->deployment_option ? $currentTargetConfig->deployment_option : "Single-AZ";
                    $amazonServerQuery = $amazonServerQuery->where('deployment_option', 'LIKE', $do . "%");
                }
            } else if($instanceType == "Azure") {
                if($currentTargetConfig->database_li_name) {
                    $amazonServerQuery = $amazonServerQuery->where('software_type', '=', $currentTargetConfig->database_li_name);
                } else {
                    $amazonServerQuery = $amazonServerQuery->where('software_type', '=', 'CentOS');
                }
            }
            $amazonServer = $amazonServerQuery->first();
            if($amazonServer == null) {
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
            for($i = 0; $i < $serversNeeded; ++$i) {
                $consolidation->targets[] = $amazonServer;
            }
        }
        $consolidations[] = $consolidation;

    }

    private function amazonAnalysis($existing, $target) {
        $cpuUtilization = $existing->cpu_utilization ? $existing->cpu_utilization : 50;
        $ramUtilization = $existing->ram_utilization ? $existing->ram_utilization : 100;
        $existingConfigs;
        if($existing->environmentType->name === 'Converged')
            $existingConfigs = $this->combineConverged($existing->serverConfigurations);
        else
            $existingConfigs = $existing->serverConfigurations;

        $existingServers = [];
        if($target->vms) {
            $totalCores = 0.0;
            $totalRam = 0.0;
            $dbServers = 0;
            $dbId = null;
            $serverArray = [];
            foreach($existingConfigs as &$config) {
                $dbServers += !!$config->database_id;
                $dbId = !$dbId ? $config->database_id : $dbId;
                $config->qty = $config->qty ? $config->qty : 1;
                if($target->environmentType->name == "Converged") {
                    //$totalCores += $config->processor->total_cores * $config->qty;
                } else {
                    $config->processor->total_cores = $config->processor->core_qty * $config->processor->socket_qty;
                    //$totalCores += $config->processor->total_cores * $config->qty;
                }
                //$totalRam += $config->ram * $config->qty;
            }
            //$ramPer = ceil((float)$totalRam / (float)$target->vms);
            //$coresPer = ceil((float)$totalCores / (float)$target->vms);

            $allConfig = [];
            foreach($existingConfigs as $config) {
                for($c = 0; $c < $config->qty; $c++) {
                    $cc = json_encode($config);
                    $clone = json_decode($cc);
                    unset($cc);
                    $allConfig[] = $clone;
                }
            }
            $vmsPerServer = floor((float)$target->vms / count($allConfig));
            $leftover = $target->vms % count($allConfig);
            $cpuUtilization = $existing->cpu_utilization ? $existing->cpu_utilization : 50;
            $ramUtilization = $existing->ram_utilization ? $existing->ram_utilization : 100;
            $tmp = [];
            foreach($allConfig as $c) {
                $cpuUtilization = $c->cpu_utilization ? $c->cpu_utilization : $cpuUtilization;
                $ramUtilization = $c->ram_utilization ? $c->ram_utilization : $ramUtilization;
                $cagrMult = 1;
                if($existing->project->cagr) {
                	for($i = 0; $i < $existing->project->support_years; ++$i) {
                		$cagrMult *= 1 + ($existing->project->cagr / 100.0);
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
                $clone->computedCores = round($clone->baseCores * ($cpuUtilization / 100.0));
                $tmp[] = $clone;
                $tmpConsolidations = [(object)['servers' => $tmp, 'targets' => []]];
            }

            foreach($allConfig as $index => &$config) {
                $vms = $vmsPerServer + ($index < $leftover);
                $config->processor->total_cores = ceil($config->processor->total_cores / (float)$vms);
                $config->processor->socket_qty = 1;
                $config->ram = ceil($config->ram / (float)$vms);
            }
            $totalConfigs = count($allConfig);

            for($i = 0; $i < $target->vms; $i++) {
                $index = floor(((float)$i / $target->vms) * $totalConfigs);
                $serverArray[] = $allConfig[$index];
            }
            $existingServers[] = $serverArray;
            //total for actual stats

        } else {
            $totalConfigs = 0;
            $tmpConsolidations = null;
            foreach($existingConfigs as &$server) {
                $serverArray = [];
                if(!$server->is_converged)
                    $server->processor->total_cores = $server->processor->core_qty * $server->processor->socket_qty;
                //sanity check
                $server->qty = $server->qty ? $server->qty : 1;
                for($i = 0; $i < $server->qty; ++$i) {
                    $serverArray[] = $server;
                    $totalConfigs++;
                }
                $existingServers[] = $serverArray;
            }
        }
        $ec2Consolidations = [];
        $rdsConsolidations = [];
        $existing->cpuUtilMatch = true;
        $existing->ramUtilMatch = true;
        foreach($existingServers as &$servers) {
            foreach($servers as &$server) {
                $currentTargetConfig = $this->matchTargetConstraints($server, $target->serverConfigurations);
                if($currentTargetConfig->database_li_name) {
                    $this->consolidateAmazon($existing, $target, $server, $currentTargetConfig, "RDS", $ec2Consolidations);
                } else {
                    $this->consolidateAmazon($existing, $target, $server, $currentTargetConfig, "EC2", $ec2Consolidations);
                }

            }
        }
        /*if($target->serverConfigurations[0]->database_id || $target->serverConfigurations[0]->database_li_name == null) {
            foreach($existingServers as &$servers) {
                foreach($servers as &$server) {
                    $currentTargetConfig = $this->matchTargetConstraints($server, $target->serverConfigurations);
                    $this->consolidateAmazon($existing, $target, $server, "EC2", $ec2Consolidations);
                }
            }
        }

        $rdsConsolidations = [];

        if($target->serverConfigurations[0]->database_li_name) {
            foreach($existingServers as &$servers) {
                foreach($servers as &$server) {
                    $currentTargetConfig = $this->matchTargetConstraints($server, $target->serverConfigurations);
                    $this->consolidateAmazon($existing, $target, $server, "RDS", $rdsConsolidations);
                }
            }
        }*/

        $ec2Totals = $this->consolidationTotals($ec2Consolidations, $cpuUtilization, $ramUtilization, $existing);
        //$rdsTotals = $this->consolidationTotals($rdsConsolidations, $cpuUtilization, $ramUtilization, $existing);

        if($tmpConsolidations) {
            $existingTotals = $this->consolidationTotals($tmpConsolidations, $cpuUtilization, $ramUtilization, $existing);
            //Replace the totals with values pulled from the actual hardware instead of those used in the VM calculations
            if($ec2Totals) {
                $ec2Totals->existing->computedRpm = $existingTotals->existing->computedRpm;
                $ec2Totals->existing->computedRam = $existingTotals->existing->computedRam;
                $ec2Totals->existing->ram = $existingTotals->existing->ram;
                $ec2Totals->existing->socket_qty = $existingTotals->existing->socket_qty;
                $ec2Totals->existing->servers = $existingTotals->existing->servers;
                $ec2Totals->existing->total_cores = $existingTotals->existing->total_cores;
                $ec2Totals->existing->cores = $existingTotals->existing->cores;
                $ec2Totals->existing->computedCores = $existingTotals->existing->computedCores;
            }
            /*if($rdsTotals) {
                $rdsTotals->existing->computedRpm = $existingTotals->existing->computedRpm;
                $rdsTotals->existing->computedRam = $existingTotals->existing->computedRam;
                $rdsTotals->existing->ram = $existingTotals->existing->ram;
                $rdsTotals->existing->socket_qty = $existingTotals->existing->socket_qty;
                $rdsTotals->existing->servers = $existingTotals->existing->servers;
                $rdsTotals->existing->total_cores = $existingTotals->existing->total_cores;
                $rdsTotals->existing->cores = $existingTotals->existing->cores;
                $rdsTotals->existing->computedCores = $existingTotals->existing->computedCores;
            }*/
        }

        /*if($ec2Totals && $rdsTotals) {
            $rdsAnalysis = (object) array('consolidations' => $rdsConsolidations, 'totals' => $rdsTotals, 'type' => 'AWS');
            $analysis = (object) array('consolidations' => $ec2Consolidations, 'totals' => $ec2Totals, 'extraAnalysis' => $rdsAnalysis, 'type' => 'AWS');
        } else if($rdsTotals) {
            $analysis = (object) array('consolidations' => $rdsConsolidations, 'totals' => $rdsTotals, 'type' => 'AWS');
        } else {*/
            $analysis = (object) array('consolidations' => $ec2Consolidations, 'totals' => $ec2Totals, 'type' => 'AWS');
        //}

        return $analysis;
    }

    private function consolidationTotals($consolidations, $cpuUtilization, $ramUtilization, $existingEnvironment) {
        if(count($consolidations) == 0)
            return null;
        $totals = (object) array(
            'existing' => (object) array(
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
                'ramUtilization' => $ramUtilization
            ), 'target' => (object) array(
                'servers' => 0,
                'total_cores' => 0,
                'ram' => 0
            )
        );
        foreach($consolidations as $consolidation) {
            $consolidation->ramTotal = 0;
            $consolidation->computedRamTotal = 0;
            $consolidation->rpmTotal = 0;
            $consolidation->computedRpmTotal = 0;
            $consolidation->targetRamTotal = 0;

            $consolidation->coreTotal = 0;
            $consolidation->computedCoreTotal = 0;

            foreach($consolidation->servers as $server) {
                $consolidation->ramTotal += $server->baseRam;
                $consolidation->computedRamTotal += $server->computedRam;
                $consolidation->rpmTotal += $server->baseRpm;
                $consolidation->computedRpmTotal += $server->computedRpm;

                $consolidation->coreTotal += $server->baseCores;
                $consolidation->computedCoreTotal += $server->computedCores;

                $totals->existing->socket_qty += $this->sumSockets($server->processor->socket_qty);
                $totals->existing->total_cores += $server->processor->total_cores;
                $totals->existing->servers++;
            }
            foreach($consolidation->targets as &$target) {
                $consolidation->targetRamTotal += $target->ram;
                $totals->target->ram += $target->ram;
                $totals->target->total_cores += $target->vcpu_qty;
                $target->processor = (object)['total_cores' => $target->vcpu_qty, 'name' => $target->name];
                $totals->target->servers++;
                //$totals->target->cores += $target->
            }
            $totals->existing->ram += $consolidation->ramTotal;
            $totals->existing->computedRam += $consolidation->computedRamTotal;

            $totals->existing->cores += $consolidation->coreTotal;
            $totals->existing->computedCores += $consolidation->computedCoreTotal;

            $totals->existing->rpm += $consolidation->rpmTotal;
            $totals->existing->computedRpm += $consolidation->computedRpmTotal;
        }
        //$totals->target->socket_qty = $totals->target->total_cores;
        $totals->target->utilRam = $totals->target->ram;
        return $totals;
    }

    /*private function serverToAmazon($ram, $cores) {
        $amazonServer = AmazonServer::where('core_qty', '>=', $cores)
                                    ->where('ram', '>=', $ram)
                                    ->orderBy('core_qty', 'asc')
                                    ->first();
        //If we do, cool, "consolidation" done.
        if($amazonServer != null) {
            return [$amazonServer];
        } else {
            $amazonServer = AmazonServer::orderBy('core_qty', 'desc')->first();
            $ram -= $amazonServer->ram;
            $cores -= $amazonServer->core_qty;
            $consolidation = $this->serverToAmazon($ram, $cores);
            $consolidation[] = $amazonServer;
            return $consolidation;
        }
    }*/

    private function emptyConstraint($obj) {
        return $obj === "" || $obj === null;
    }

    private function checkConstaint($existing, $target) {
        //Check if either constraint is a wild card. If it is, it is a success, but don't increment the matches
        if($this->emptyConstraint($target) || $this->emptyConstraint($existing)) {
            return 0;
        } else if($target == $existing) {
            //If they match, we want to increment the counter
            return 1;
        }
        //If the don't match, we can't consolidate to this target
        return false;
    }

    private function matchTargetConstraints($existing, $targetConfigs) {
        $bestMatch = null;
        $bestMatchScore = -1;
        foreach($targetConfigs as $target) {
            $currentScore = 0;
            $result = $this->checkConstaint($target->environment_detail, $existing->environment_detail);
            if($result === false)
                continue;
            $currentScore += $result;
            $result = $this->checkConstaint($target->location, $existing->location);
            if($result === false)
                continue;
            $currentScore += $result;
            $result = $this->checkConstaint($target->workload_type, $existing->workload_type);
            if($result === false)
                continue;
            $currentScore += $result;

            if($currentScore > $bestMatchScore) {
                $bestMatchScore = $currentScore;
                $bestMatch = $target;
            }
        }
        return $bestMatch;
    }

    public function analysis($existingId, $targetId) {
        ini_set('memory_limit', '8000M');
        set_time_limit(60);
        $existingEnvironment = Environment::with(array('serverConfigurations.processor', 'project',
                                                       "serverConfigurations.server",
                                                       "serverConfigurations.manufacturer",
            "serverConfigurations.chassis",
            "serverConfigurations.chassis.manufacturer",
            "serverConfigurations.chassis.model",
            "serverConfigurations.interconnect",
            "serverConfigurations.interconnect.manufacturer",
            "serverConfigurations.interconnect.model"))->find($existingId);
        $targetEnvironment = Environment::with(array('serverConfigurations.processor', 'project',
                                                       "serverConfigurations.server",
                                                       "serverConfigurations.manufacturer",
            "serverConfigurations.chassis",
            "serverConfigurations.chassis.manufacturer",
            "serverConfigurations.chassis.model",
            "serverConfigurations.interconnect",
            "serverConfigurations.interconnect.manufacturer",
            "serverConfigurations.interconnect.model"))->find($targetId);

        /* Authenticate */
        $profile = Auth::user();
        $deny = true;
        if ($existingEnvironment->project->user_id == $profile->user_id && $targetEnvironment->project->user_id == $profile->user_id) {
            $deny = false;
        }
        $user = \App\Models\UserManagement\User::with('groups')->find($profile->user_id);
        foreach($user->groups as $group) {
            if($group->name == "Admin") {
                $deny = false;
            }
        }

        if ($deny) {
            abort(401);
        }

        if($existingEnvironment->is_incomplete || $targetEnvironment->is_incomplete) {
            $err = (object)["message" => "An environment has insufficient data to run the analysis"];
            $err->environments = (object)['existing' => ($existingEnvironment->is_incomplete ? $existingEnvironment->id : null),
                                          'target' => ($targetEnvironment->is_incomplete ? $targetEnvironment->id : null),
                                          'existingName' => $existingEnvironment->name,
                                          'targetName' => $targetEnvironment->name,
                                          'existingIsExisting' => $existingEnvironment->is_existing];
            return response()->json($err, 400);
        }

        if(!$existingEnvironment->environmentType || !$targetEnvironment->environmentType) {
            $err = (object)["message" => "Insufficient data to run analysis"];
            $err->environments = (object)['existing' => (!$existingEnvironment->environmentType ? $existingEnvironment->id : null),
                                          'target' => (!$targetEnvironment->environmentType ? $targetEnvironment->id : null),
                                          'existingName' => $existingEnvironment->name,
                                          'targetName' => $targetEnvironment->name,
                                          'existingIsExisting' => $existingEnvironment->is_existing];
            return response()->json($err, 400);
        }
        if($targetEnvironment->environmentType->name == "Cloud") {
            //cloud stuff here
            $analysis = $this->amazonAnalysis($existingEnvironment, $targetEnvironment);
            $targetEnvironment->target_analysis = json_encode($analysis);
            $targetEnvironment->is_dirty = false;
            $targetEnvironment->save();
            if($analysis->totals == null) {
                $err = (object)["message" => "No matching server was found."];
                return response()->json($err, 400);
            }
            /** @var Revenue $revenueService */
            $revenueService = resolve(Revenue::class);
            if(!$revenueService->isReportMode() && Auth::user()->user->view_cpm == true) {
                Auth::user()->user->ytd_queries++;
                Auth::user()->user->save();

                $log = new Log();
                $log->user_id = Auth::user()->user->id;
                $log->log_type = "cpm_query";
                $log->save();
            }
            return $targetEnvironment->target_analysis;
        }
        //Calculate the existing environment variables
        $envCpuUtilization = $existingEnvironment->cpu_utilization ? $existingEnvironment->cpu_utilization : 50;
        $envRamUtilization = $existingEnvironment->ram_utilization ? $existingEnvironment->ram_utilization : 100;
        $cagrMult = 1;
        if($existingEnvironment->project->cagr) {
            for($i = 0; $i < $existingEnvironment->project->support_years; ++$i) {
                $cagrMult *= 1 + ($existingEnvironment->project->cagr / 100.0);
            }
        }

        if(!$existingEnvironment->is_existing) {
            $cpuUtilization = $existingEnvironment->cpu_utilization ? $existingEnvironment->cpu_utilization : $existingEnvironment->max_utilization;
            $ramUtilization = $existingEnvironment->ram_utilization ? $existingEnvironment->ram_utilization : $existingEnvironment->max_utilization;
        }

        $existingConfigs;
        if($existingEnvironment->environmentType->name == "Converged")
            $existingConfigs = $this->combineConverged($existingEnvironment->serverConfigurations);
        else
            $existingConfigs = $existingEnvironment->serverConfigurations;

        $existingServers = [];
        foreach($existingConfigs as &$server) {
            $serverArray = [];
            if(!$server->is_converged)
                $server->processor->total_cores = $server->processor->core_qty * $server->processor->socket_qty;
            //sanity check
            $server->qty = $server->qty ? $server->qty : 1;
            for($i = 0; $i < $server->qty; ++$i) {
                $serverArray[] = $server;
            }
            $existingServers[] = $serverArray;
        }

        $targetConfigs;
        if($targetEnvironment->environmentType->name == "Converged")
            $targetConfigs = $this->combineConverged($targetEnvironment->serverConfigurations);
        else
            $targetConfigs = $targetEnvironment->serverConfigurations;

        $targetObj = (object) array();
        $targetObj->cpuUtilization = $targetEnvironment->cpu_utilization ? $targetEnvironment->cpu_utilization : 100;
        $targetObj->ramUtilization = $targetEnvironment->ram_utilization ? $targetEnvironment->ram_utilization : 100;
        $targetObj->variance = $targetEnvironment->variance ? $targetEnvironment->variance : 5;
        //$targetObj->maxRelativePerformance = 0;
        //$targetObj->maxRpm = 0;
        //$targetObj->maxRam = 0;
        //Walk through each target server (possible for converged-> Should be one for compute)
        foreach($targetConfigs as &$config) {
            /*$config->computedRpm = round(min(($config->processor->rpm * ($targetObj->maxUtilization / 100.0)) * ((100 + $targetObj->variance) / 100.0),
                                                     $config->processor->rpm));
            $config->computedRam = round(min(($config->ram * ($targetObj->maxUtilization / 100.0)) * ((100 + $targetObj->variance) / 100.0),
                                                    $config->ram));*/
            $config->utilRpm = round($config->processor->rpm * ($targetObj->cpuUtilization / 100.0));
            $config->computedRpm = round(($config->processor->rpm * ($targetObj->cpuUtilization / 100.0)) * ((100 + $targetObj->variance) / 100.0));
            $config->utilRam = round($config->ram * ($targetObj->ramUtilization / 100.0));
            $config->computedRam = round(($config->ram * ($targetObj->ramUtilization / 100.0)) * ((100 + $targetObj->variance) / 100.0));
            $totalStorage = 0;
            $totalIops = 0;
            //$targetObj->maxRpm += $config->computedRpm;
            //$targetObj->maxRam += $config->computedRam;
            if(!$config->is_converged) {
                $config->processor->total_cores = $config->processor->core_qty * $config->processor->socket_qty;
            } else {
//                $totalIops = $config->iops;
            }

        }
        if(!count($targetConfigs) || !count($existingServers)) {
            $err = (object)["message" => "Insufficient data to run analysis."];
            $err->environments = (object)['existing' => (!count($existingServers) ? $existingEnvironment->id : null),
                                          'target' => (!count($targetConfigs) ? $targetEnvironment->id : null),
                                          'existingName' => $existingEnvironment->name,
                                          'targetName' => $targetEnvironment->name,
                                          'existingIsExisting' => $existingEnvironment->is_existing];
            return response()->json($err, 400);
        }
        $consolidations = [];
        $existingEnvironment->cpuUtilMatch = true;
        $existingEnvironment->ramUtilMatch = true;
        //While we still have existing servers to consolidate
        while($this->serversExist($existingServers)) {
            //Set the RAM and RPM to 0 for this consolidation
            $currentRPM = 0;
            $currentRAM = 0;
            $perConsolidation = [];
            $targets = [];
            $consolidated = false;
            $targetConfig = null;
            $targetObj = null;
            $count = 0;
            //Go through each of the seperate existing servers
            foreach($existingServers as &$existingServer) {
                //Skip over any empties
                if(!count($existingServer))
                    continue;
                $currentTargetConfig = $this->matchTargetConstraints($existingServer[0], $targetConfigs);
                if(!$targetConfig && !$targetObj) {
                    $targetConfig = $currentTargetConfig;
                    $targetObj = (object) array();
                    $targetObj->cpuUtilization = $targetEnvironment->cpu_utilization ? $targetEnvironment->cpu_utilization : 100;
                    $targetObj->ramUtilization = $targetEnvironment->ram_utilization ? $targetEnvironment->ram_utilization : 100;
                    $targetObj->variance = $targetEnvironment->variance ? $targetEnvironment->variance : 5;
                    $targetObj->maxRpm = $targetConfig->computedRpm;
                    $targetObj->maxRam = $targetConfig->computedRam;
                } else if($currentTargetConfig->id != $targetConfig->id) {
                    continue;
                }
                if(!$targetConfig->useable_storage)
                    $targetConfig->useable_storage = $targetConfig->raw_storage / 2.0;
                $cpuUtilization = $existingServer[0]->cpu_utilization ? $existingServer[0]->cpu_utilization : $envCpuUtilization;
                if($existingServer[0]->cpu_utilization && $existingServer[0]->cpu_utilization != $envCpuUtilization) {
                    $existingEnvironment->cpuUtilMatch = false;
                }
                $ramUtilization = $existingServer[0]->ram_utilization ? $existingServer[0]->ram_utilization : $envRamUtilization;
                if($existingServer[0]->ram_utilization &&
                    $existingServer[0]->ram_utilization != $envRamUtilization) {
                    $existingEnvironment->ramUtilMatch = false;
                }
                //Calculated the RPM and RAM used for the server
                $existingServer[0]->baseRpm = round($existingServer[0]->processor->rpm * $cagrMult);
                $existingServer[0]->baseRam = round($existingServer[0]->ram * $cagrMult);
                $existingServer[0]->computedRpm = round($existingServer[0]->baseRpm * ($cpuUtilization / 100.0));
                $existingServer[0]->computedRam = round($existingServer[0]->baseRam * ($ramUtilization / 100.0));
                //Check if the existing server is larger than the target-> This may happen in the case of
                //converged to compute-> We also don't want to start trying to consolidate the targets into
                //existing if we already started the other way around
                $reverseConsolidation = false;
                if($consolidated == false &&
                    ($existingServer[0]->computedRpm > $targetObj->maxRpm ||
                    $existingServer[0]->computedRam > $targetObj->maxRam)) {
                        $reverseConsolidation = true;
                        if(!!$existingServer[0]->computedRam == false || !!$targetObj->maxRam == false) {
                            $err = (object)["message" => "Insufficient data to run analysis."];
                            $err->environments = (object)['existing' => ((!!$existingServer[0]->computedRam == false) ? $existingEnvironment->id : null),
                                                          'target' => ((!!$targetObj->maxRam == false) ? $targetEnvironment->id : null),
                                                          'existingName' => $existingEnvironment->name,
                                                          'targetName' => $targetEnvironment->name,
                                                          'existingIsExisting' => $existingEnvironment->is_existing];
                            return response()->json($err, 400);
                        }
                    }
                if($reverseConsolidation) {
                      while(($currentRPM <= $existingServer[0]->computedRpm) ||
                            ($currentRAM <= $existingServer[0]->computedRam)) {
                          //Increase the RAM and RPM
                          $currentRPM += $targetObj->maxRpm;
                          $currentRAM += $targetObj->maxRam;
                          $totalIops += $targetConfig->iops;
                          $totalStorage += $targetConfig->useable_storage;
                          //Add this server to the current consolidation
                          $targets[] = $targetConfig;
                          $consolidated = true;
                      }

                      //Add this server to the current consolidation
                      $perConsolidation[] = $existingServer[0];
                      //Remove this server from the list of servers that need to be consolidated
                      array_shift($existingServer);
                } else {
                    //While the we can fit another one of these servers into the target
                    $existingServer[0]->environment_name = $existingEnvironment->name;
                    //Check if we're in the right environment (Production, Dev, etc)

                    $env = $existingServer[0]->environment_detail;
                    $workload = $existingServer[0]->workload_type;
                    $location = $existingServer[0]->location;
                    //print_r(count($existingServer) . ' ' . $existingServer[0]->id . ' ');
                    while(count($existingServer) &&
                          (($currentRPM + $existingServer[0]->computedRpm) <= $targetObj->maxRpm) &&
                          (($currentRAM + $existingServer[0]->computedRam) <= $targetObj->maxRam) &&
                          ((!count($perConsolidation)) || (count($perConsolidation)
                          && $perConsolidation[0]->environment_detail == $env
                          && $perConsolidation[0]->workload_type == $workload
                          && $perConsolidation[0]->location == $location))) {
                        //Increase the RAM and RPM
                        $currentRPM += $existingServer[0]->computedRpm;
                        $currentRAM += $existingServer[0]->computedRam;

                        //Add this server to the current consolidation
                        $perConsolidation[] = $existingServer[0];
                        //Remove this server from the list of servers that need to be consolidated
                        array_shift($existingServer);
                        $consolidated = true;
                    }

                    if (count($targets) === 0) {
                        $targets[] = $targetConfig;
                        $totalIops += $targetConfig->iops;
                        $totalStorage += $targetConfig->useable_storage;
                    }
                }
            }
            //targets->push($targetEnvironment);
            //$target = $targetConfigs;
            //target->push(JSON->parse(JSON->stringify(targetConfigs)));
            //We couldn't find a way to consolidate the servers-> Probably because at least one of the existing
            //environment servers couldn't fit in to the target servers-> This will need to be updated for the case of
            //converged to non-converged, but for now this is here to prevent infinite loops
            if (!$consolidated) {
                break;
            }
            //$targetEnvironment->processor
            $consolidation = (object) array(
                'servers' => $perConsolidation,
                'targets' => $targets,
                'collapsed' => false
            );
            //Once we've tried to fit all the servers in, add the servers found to a single consolidation
            $consolidations[] = $consolidation;
        }
        $remainingStorage = 0;
        $storageConsolidations = array();
        $iopsConsolidation = [];
        $convReq = array();
        $realTotalIops = $totalIops;
        $realTotal = $totalStorage;
        if ($targetEnvironment->environmentType->name === 'Converged') {
            $conf = $targetConfigs[0];
            if(!$conf->useable_storage) {
                $conf->useable_storage = ($conf->raw_storage / 2.0);
            }
            if($totalStorage < $existingEnvironment->useable_storage) {
                $remainingStorage = $existingEnvironment->useable_storage - $totalStorage;
                $numConfigs = ceil($remainingStorage / $conf->useable_storage);
                $realTotal = $totalStorage + $conf->useable_storage * $numConfigs;
                for($i = 0; $i < $numConfigs; ++$i) {
                    $storageConsolidations[] = $conf;
                }
            }

            $realTotal = $totalStorage + count($storageConsolidations) * $conf->useable_storage;
            $totalIops = $totalIops + count($storageConsolidations) * $conf->iops;

            if(intval($conf->iops) && $realTotalIops < $existingEnvironment->iops) {
                $remainingIops = $existingEnvironment->iops - $totalIops;
                $numConfigs = ceil($remainingIops / $conf->iops);
                $realTotalIops = $totalIops + $conf->iops * $numConfigs;
                for($i = 0; $i < $numConfigs; ++$i) {
                    $iopsConsolidation[] = $conf;
                }
            }

            if (intval($conf->iops) && $totalIops >= $existingEnvironment->iops && $totalStorage >= $existingEnvironment->useable_storage) {
                $nodeQty = 0;
                foreach($consolidations as $consolidation) {
                    foreach($consolidation->targets as $target) {
                        foreach($target->configs as $config) {
                            $nodeQty += $config->qty;
                        }
                    }
                }
                //If the consolidation only has 1 appliance with 1 node, add another appliance
                if ($nodeQty === 1) {
                    $convReq[] = $conf;
                }
            }

            $realTotal = $realTotal + count($iopsConsolidation) * $conf->useable_storage;
            $realTotalIops = $totalIops + count($iopsConsolidation) * $conf->iops;

        }

        $totals = (object) array(
            'existing' => (object) array(
                'servers' => 0,
                'socket_qty' => 0,
                'total_cores' => 0,
                'ram' => 0,
                'computedRam' => 0,
                'rpm' => 0,
                'computedRpm' => 0,
                'cpuMatch' => $existingEnvironment->cpuUtilMatch,
                'ramMatch' => $existingEnvironment->ramUtilMatch,
                'cpuUtilization' => $envCpuUtilization,
                'ramUtilization' => $envRamUtilization
            ), 'target' => (object) array(
                'servers' => 0,
                'socket_qty' => 0,
                'total_cores' => 0,
                'ram' => 0,
                'computedRam' => 0,
                'utilRam' => 0,
                'rpm' => 0,
                'computedRpm' => 0,
                'utilRpm' => 0
            ), 'storage' => (object) array(
                'deficit' => $remainingStorage,
                'existing' => $existingEnvironment->useable_storage,
                'target' => $totalStorage,
                'targetTotal' => $realTotal
            ), 'iops' => (object) array(
                'deficit' => $existingEnvironment->iops - $totalIops,
                'existing' => $existingEnvironment->iops,
                'target' => $totalIops,
                'targetTotal' => $realTotalIops
            )
        );
        foreach($consolidations as $consolidation) {
            $consolidation->ramTotal = 0;
            $consolidation->computedRamTotal = 0;
            $consolidation->rpmTotal = 0;
            $consolidation->computedRpmTotal = 0;
            $consolidation->targetRamTotal = 0;
            $consolidation->targetComputedRamTotal = 0;
            $consolidation->targetUtilRamTotal = 0;
            $consolidation->targetRpmTotal = 0;
            $consolidation->targetComputedRpmTotal = 0;
            $consolidation->targetUtilRpmTotal = 0;
            foreach($consolidation->servers as $server) {
                $consolidation->ramTotal += $server->baseRam;
                $consolidation->computedRamTotal += $server->computedRam;
                $consolidation->rpmTotal += $server->baseRpm;
                $consolidation->computedRpmTotal += $server->computedRpm;

                $totals->existing->socket_qty += $this->sumSockets($server->processor->socket_qty);
                $totals->existing->total_cores += $server->processor->total_cores;
                $totals->existing->servers++;
            }
            foreach($consolidation->targets as $target) {
                $consolidation->targetComputedRamTotal += $target->computedRam;
                $consolidation->targetComputedRpmTotal += $target->computedRpm;


                $consolidation->targetRamTotal += $target->ram;
                $consolidation->targetRpmTotal += $target->processor->rpm;

                $consolidation->targetUtilRpmTotal += $target->utilRpm;
                $consolidation->targetUtilRamTotal += $target->utilRam;

                $totals->target->socket_qty += $this->sumSockets($target->processor->socket_qty);
                $totals->target->total_cores += $target->processor->total_cores;
                if(!!$target->configs) {
                    foreach($target->configs as $config) {
                        $totals->target->servers += $config->qty;
                    }
                } else {
                    $totals->target->servers++;
                }
            }
            $totals->existing->ram += $consolidation->ramTotal;
            $totals->existing->computedRam += $consolidation->computedRamTotal;
            $totals->target->ram += $consolidation->targetRamTotal;
            $totals->target->computedRam += $consolidation->targetComputedRamTotal;
            $totals->target->utilRam += $consolidation->targetUtilRamTotal;

            $totals->existing->rpm += $consolidation->rpmTotal;
            $totals->existing->computedRpm += $consolidation->computedRpmTotal;
            $totals->target->rpm += $consolidation->targetRpmTotal;
            $totals->target->computedRpm += $consolidation->targetComputedRpmTotal;
            $totals->target->utilRpm += $consolidation->targetUtilRpmTotal;
        }

        foreach($storageConsolidations as $target) {
            $totals->target->computedRam += $target->computedRam;
            $totals->target->computedRpm += $target->computedRpm;

            $totals->target->ram += $target->ram;
            $totals->target->rpm += $target->processor->rpm;

            $totals->target->utilRpm += $target->utilRpm;
            $totals->target->utilRam += $target->utilRam;

            $totals->target->socket_qty += $this->sumSockets($target->processor->socket_qty);
            $totals->target->total_cores += $target->processor->total_cores;
            if(!!$target->configs) {
                foreach($target->configs as $config) {
                    $totals->target->servers += $config->qty;
                }
            } else {
                $totals->target->servers++;
            }
        }

        foreach($iopsConsolidation as $target) {
            $totals->target->computedRam += $target->computedRam;
            $totals->target->computedRpm += $target->computedRpm;

            $totals->target->ram += $target->ram;
            $totals->target->rpm += $target->processor->rpm;

            $totals->target->utilRpm += $target->utilRpm;
            $totals->target->utilRam += $target->utilRam;

            $totals->target->socket_qty += $this->sumSockets($target->processor->socket_qty);
            $totals->target->total_cores += $target->processor->total_cores;
            if(!!$target->configs) {
                foreach($target->configs as $config) {
                    $totals->target->servers += $config->qty;
                }
            } else {
                $totals->target->servers++;
            }
        }

        foreach($convReq as $target) {
            $totals->target->computedRam += $target->computedRam;
            $totals->target->computedRpm += $target->computedRpm;

            $totals->target->ram += $target->ram;
            $totals->target->rpm += $target->processor->rpm;

            $totals->target->utilRpm += $target->utilRpm;
            $totals->target->utilRam += $target->utilRam;

            $totals->target->socket_qty += $this->sumSockets($target->processor->socket_qty);
            $totals->target->total_cores += $target->processor->total_cores;
            if(!!$target->configs) {
                foreach($target->configs as $config) {
                    $totals->target->servers += $config->qty;
                }
            } else {
                $totals->target->servers++;
            }
        }

        $analysis = (object) array('consolidations' => $consolidations,
                                  'storage' => $storageConsolidations,
                                  'iops' => $iopsConsolidation,
                                  'converged' => $convReq,
                                  'totals' => $totals,
                                  'type' => 'Physical'
                                );
        foreach($analysis->consolidations as &$con) {
            foreach($con->servers as &$server) {
                $this->unsetServerData($server);
            }
            foreach($con->targets as &$target) {
                $this->unsetServerData($target);
            }
        }
        $targetEnvironment->target_analysis = json_encode($analysis);
        $targetEnvironment->is_dirty = false;
        $targetEnvironment->save();
        /** @var Revenue $revenueService */
        $revenueService = resolve(Revenue::class);
        if(!$revenueService->isReportMode() && Auth::user()->user->view_cpm == true) {
            Auth::user()->user->ytd_queries++;
            Auth::user()->user->save();

            $log = new Log();
            $log->user_id = Auth::user()->user->id;
            $log->log_type = "cpm_query";
            $log->save();
        }
        return $targetEnvironment->target_analysis;
        //$targetEnvironment->$cacheTargetAnalysis();
    }

    private function unsetServerData(&$server) {
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
    }

    private function sumSockets($sockets) {
        if(gettype($sockets) == "string") {
            $socketArray = explode(',', $sockets);
            $totalSockets = 0;
            foreach($socketArray as $socket) {
                $totalSockets += (int)$socket;
            }
            return $totalSockets;
        } else {
            return (int)$sockets;
        }
    }
}
