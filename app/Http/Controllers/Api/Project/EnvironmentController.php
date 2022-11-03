<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Api\Hardware\InterconnectChassisController;
use App\Http\Controllers\Controller;
use App\Models\Hardware\InterconnectChassis;
use App\Models\Project\EnvironmentType;
use App\Models\Hardware\AmazonStorage;
use App\Models\Hardware\AzureStorage;
use App\Models\Hardware\GoogleStorage;
use App\Models\Hardware\IBMPVSStorage;
use App\Models\Hardware\AmazonServer;
use App\Services\Registry;
use Illuminate\Support\Arr;
use Symfony\Component\EventDispatcher\Tests\Service;
use App\Models\Project\Environment;
use App\Models\Project\Region;
use App\Models\Project\Currency;
use App\Models\Project\Provider;
use App\Models\Hardware\ServerConfiguration;
use App\Http\Controllers\Api\Hardware\ServerConfigurationController;
use App\Http\Controllers\Api\Software\SoftwareCostController;
use App\Models\Project\Cloud\AzureAdsServiceOption;
use App\Models\Project\Cloud\InstanceCategory;
use App\Models\Project\Cloud\OsSoftware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;

class EnvironmentController extends Controller {

    protected $model = "App\Models\Project\Environment";

    protected $activity = 'Environment Management';
    protected $table = 'environments';

    // Private Method to set the data

    /**
     * @param Environment $environment
     * @return bool
     */
    private function setData(&$environment) {
        $validator = Validator::make(
            Request::all(),
            array(
                'name' => 'required|string|max:50',
                'project_id' => 'required|exists:projects,id'
        ));

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return false;
        }
        // Set all the data here (fill in all the fields)
        $environment->name = Request::input('name');
        $environment->project_id = Request::input('project_id');
        $et = Request::input('environment_type');
        //environment_type may be an object or an ID
        if(is_array($et)) {
            $et = $et['id'];
        }
        $environment->environment_type = $et;
        $environment->server_qty = Request::input('server_qty');
        $environment->socket_qty = Request::input('socket_qty');
        $environment->core_qty = Request::input('core_qty');
        $environment->variance = Request::input('variance');
        $environment->max_utilization = Request::input('max_utilization');
        $environment->cpu_utilization = Request::input('cpu_utilization');
        $environment->processor_type_constraint = Request::input('processor_type_constraint');
        $environment->processor_models_constraint = Request::input('processor_models_constraint');
        $environment->ram_utilization = Request::input('ram_utilization');
        $environment->fte_qty = Request::input('fte_qty');
        $environment->fte_salary = Request::input('fte_salary');
        $environment->remaining_deprecation = Request::input('remaining_deprecation');
        $environment->migration_services = Request::input('migration_services');
        $environment->is_existing = Request::input('is_existing');
        $environment->currency_id = Request::input('currency_id');
        $environment->region_id = Request::input('region_id');
        $environment->provider_id = Request::input('provider_id');
        $environment->cost_per_kwh = Request::input('cost_per_kwh');
        $environment->storage_type = Request::input('storage_type');
        $environment->raw_storage = Request::input('raw_storage');
        $environment->useable_storage = Request::input('useable_storage');
        $environment->iops = Request::input('iops');
        $environment->storage_purchase = Request::input('storage_purchase');
        $environment->storage_maintenance = Request::input('storage_maintenance');
        $environment->vms = Request::input('vms');
        $environment->is_incomplete = Request::input('is_incomplete');
        $environment->is_optimal = Request::input('is_optimal');
        $environment->cloud_bandwidth = Request::input('cloud_bandwidth');
        $environment->cloud_storage_type = Request::input('cloud_storage_type');
        $environment->io_rate = Request::input('io_rate');
        $environment->provisioned_iops = Request::input('provisioned_iops');
        $environment->network_overhead = Request::input('network_overhead');
        $environment->copy_vm_os = Request::input('copy_vm_os');
        $environment->copy_vm_middleware = Request::input('copy_vm_middleware');
        $environment->copy_vm_hypervisor = Request::input('copy_vm_hypervisor');
        $environment->copy_vm_database = Request::input('copy_vm_database');
        $origEEType = $environment->existing_environment_type;
        $environment->existing_environment_type = Request::input('existing_environment_type');
        $environment->vm_hardware_annual_maintenance = Request::input('vm_hardware_annual_maintenance');
        $environment->payment_option_id = Request::input('payment_option_id');
        $environment->discount_rate = Request::input('discount_rate');
        $environment->converged_cloud_type = Request::input('converged_cloud_type');
        $environment->drive_qty = Request::input('drive_qty');

        if ($environment->isExisting() && !$environment->isVm()) {
            // Auto set to `compute` type
            $environment->setDefaultExistingEnvironmentType();
            $environment->vm_hardware_annual_maintenance = null;
        }

        if (!$environment->isAws() || ($environment->cloud_storage_type != 3 && $environment->cloud_storage_type != 7 && intval($environment->provisioned_iops))) {
            $environment->provisioned_iops = null;
        }

        if ($environment->isCloud()) {
            $environment->custom_cloud_support_cost = Request::input('custom_cloud_support_cost');
            $environment->cloud_support_costs = Request::input('cloud_support_costs');
        }

        if(Request::exists('treat_as_existing')) {
            $tae = Request::input('treat_as_existing');
            //if($tae && !$environment->is_existing) {
                //$environment->cpu_utilization = $environment->max_utilization;
                //$environment->ram_utilization = $environment->max_utilization;
            //}
            //If we updated the existing environment, null all the target environment analyses for the project.
            if($tae) {
                $siblingEnvironments = Environment::where('project_id', '=', $environment->project_id)
                                                  ->where('id', '!=', $environment->id)->get();
                /** @var Environment $sibling */
                foreach($siblingEnvironments as $sibling) {
                    $sibling->is_dirty = true;
                    if (!floatval($environment->useable_storage) && $sibling && $sibling->environmentType && $sibling->environmentType->name != 'Cloud') {
                        $sibling->cloud_storage_type = null;
                        $sibling->provisioned_iops = null;
                    } else {
                        /*
                         * Otherwise ensure the type is set
                         */
                        if ($sibling->environmentType && $sibling->environmentType->name == 'Cloud' && !$sibling->cloud_storage_type && $sibling->provider) {
                            $sibling->cloud_storage_type = $sibling->provider->name == 'AWS' ? 2 : 1;
                        }
                    }
                    $sibling->save();
                }
            }
        }

        if ($environment->isExisting() || $environment->isTreatAsExisting() || $environment->isCloud()) {
            $environment->resetCopyVmSoftware();
        }

        //Since we updated the environment, the analysis is no longer valid.
        $environment->is_dirty = true;
        // Save the platform
        $environment->save();

        if ($environment->isExisting() && !$environment->isPhysicalVm()) {
            $siblingEnvironments = Environment::where('project_id', '=', $environment->project_id)
                ->where('id', '!=', $environment->id)->get();

            /** @var Environment $siblingEnvironment */
            foreach($siblingEnvironments as $siblingEnvironment) {
                $siblingEnvironment->resetCopyVmSoftware()
                    ->save();
            }
        }

        $environment->project->updated_at = time();
        $environment->project->save();

        if ($origEEType && $origEEType !== $environment->existing_environment_type) {
            $environment->serverConfigurations->each(function(ServerConfiguration $config){
                $config->delete();
            });
        }

        if (Request::input('payment_option')) {
            $environment->payment_option = Request::input('payment_option');
        }

        if (Request::input('cloud_storage_type')) {
            $environment->cloud_storage_type_model = AmazonStorage::getAmazonStorageTypeById(Request::input('cloud_storage_type'));
        }
        return true;
    }

    protected function index() {
        $environments = Environment::all();

        return response()->json($environments);
    }

    private function insertUnique(&$resultArray, $result) {
        foreach($resultArray as $r) {
            if($r == $result)
                return;
        }
        if($result !== null)
            $resultArray[] = $result;
    }

    protected function getEnvironmentConstraints($id) {
        $env = Environment::find($id);
        $existing = Environment::where("project_id", "=", $env->project_id)
                                ->orderBy("id", "asc")->first();
        $results = (object)['locations' => [], 'environment_details' => [], 'workload_types' => [], 'combinations' => []];
        if($existing->id == $id)
            return null;
        foreach($existing->serverConfigurations as $config) {
            $this->insertUnique($results->locations, $config->location);
            $this->insertUnique($results->environment_details, $config->environment_detail);
            $this->insertUnique($results->workload_types, $config->workload_type);
            $found = false;
            foreach($results->combinations as $combination) {
                if($combination->location == $config->location &&
                   $combination->environment_detail == $config->environment_detail &&
                   $combination->workload_type == $config->workload_type) {
                    $found = true;
                }
            }
            if(!$found) {
                $results->combinations[] = (object)[
                    'location' => $config->location,
                    'environment_detail' => $config->environment_detail,
                    'workload_type' => $config->workload_type
                ];
            }
        }
        //return response()->json($results);
        return $results;
    }

    /**
     * Get an environment resource for a given id(w/ optional `project` appended)
     * 
     * Resources returned with the Environment:
     * - ServerConfiguration
     * - ...
     *
     * @param int $id
     * @param int $projectId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function show($id, $projectId = null)
    {
        $serverIncludes = array(
            'serverConfigurations.processor',
            'serverConfigurations.manufacturer',
            'serverConfigurations.server',
            'serverConfigurations.os.softwareType',
            'serverConfigurations.middleware.softwareType',
            'serverConfigurations.hypervisor.softwareType',
            'serverConfigurations.database.softwareType',
            'serverConfigurations.os.features',
            'serverConfigurations.middleware.features',
            //'databaseLi.softwareType',
            // 'databaseLi.features',
            'serverConfigurations.hypervisor.features',
            'serverConfigurations.database.features',
            'serverConfigurations.nodes',
            'serverConfigurations.interconnect',
            'serverConfigurations.chassis',
            'serverConfigurations.chassis.model',
            'serverConfigurations.nodes.os.features',
            'serverConfigurations.nodes.hypervisor.features',
            'serverConfigurations.nodes.middleware.features',
            'serverConfigurations.nodes.database.features',
            'serverConfigurations.nodes.processor'
        );
        $environment = Environment::with(
            array('softwareCosts.software.softwareFeatures' => function ($query) {
                $query->with('feature')
                    ->join('features', 'software_features.feature_id', '=', 'features.id')
                    ->whereNull('features.user_id')
                    ->orWhere('features.user_id', '=', Auth::user()->user->id);
                },
                'softwareCosts.software.softwareType',
                'softwareCosts.featureCosts' => function ($query) {
                    $query->with('feature')
                        ->join('features', 'feature_costs.feature_id', '=', 'features.id')
                        ->whereNull('features.user_id')
                        ->orWhere('features.user_id', '=', Auth::user()->user->id);
                },
                'environmentType',
                'project',
                'provider',
                'region'
            ))
            ->with(['interconnects', 'interconnects.model', 'interconnects.manufacturer'])
            ->with($serverIncludes)
            ->find($id);

        /*foreach($environment->softwareCosts as $sc) {
            $sc->software;
        }*/

        //We don't need this client side and it just adds overhead to the request
        $environment->setAppends([]);
        //This is to check if we have a target environment that should be treated as an existing one
        $environment->constraints = $this->getEnvironmentConstraints($id);
        /** @var false|Environment $existing */
        $existing = false;

        if($projectId != null) {
            $existing = Environment::where('project_id', '=', $projectId)->orderBy('id', 'asc')->first();
            $environment->treat_as_existing = $existing->id == $id;
        }

        if($projectId != null) {
            if($environment->project_id != $projectId) {
                return response()->json("Invalid environment");
            }
        }

        $environment->serverConfigurations->each(function(ServerConfiguration $serverConfiguration) {
            //* append the InstanceCategory object to the serverConfig's selected "instance_category"
            if ($serverConfiguration->instance_category) {
                $serverConfiguration->instance_category_object =
                    InstanceCategory::where(
                        'name',
                        $serverConfiguration->instance_category
                    )
                    ->first();
            }

            //* append the OsSoftware object to the serverConfig's selected software
            // if ($serverConfiguration->database_li_name) {
            //     $serverConfiguration->os_software = OsSoftware::where(
            //             'name',
            //             $serverConfiguration->database_li_name
            //         )
            //         ->first();
            // }

            if ($serverConfiguration->nodes) {
                $serverConfiguration->nodes->each(function(ServerConfiguration $node) {
                    if ($node->processor) {
                        $node->processor->rpm = null;
                    }
                });
            }

            if ($serverConfiguration->processor) {
                $serverConfiguration->processor->rpm = null;
            }
        });

        //$environment->environmentType;
        //$environment->project;
        $provider = $environment->provider;

        if($provider) {
            $regions = $provider->regions;
            $instanceCategories = $provider->instanceCategories;
            $osSoftwares = $provider->osSoftwares;

            if ($provider->name === Provider::AZURE) {
                $provider['ads_service_options'] = AzureAdsServiceOption::all();
            }
        }

        $region =  $environment->region;
        $currencies = null;

        if($region) $currencies = $region->currencies;

        // Set payment option and cloud storage type model
        if ($environment->environment_type && $environment->isAws()) {
            $environment->cloud_storage_type_model = AmazonStorage::getAmazonStorageTypeById($environment->cloud_storage_type);
            $environment->payment_option = AmazonServer::getAmazonPaymentOptionById($environment->payment_option_id);
        }
        
        if ($environment->environment_type && $environment->isAzure()) {
            $environment->cloud_storage_type_model = AzureStorage::getAzureStorageTypeById($environment->cloud_storage_type);
            
            #* making sure environment has the correct PaymentOption reference ID if
            #* a PaymentOption ID changes after import
            $paymentOption = AmazonServer::getAzurePaymentOptionById($environment->payment_option_id);

            // if ($paymentOption['id'] !== (int)$environment->payment_option_id) {
            //     $environment->payment_option_id = $paymentOption['id'];
            //     $environment->save();
            // }

            $environment->payment_option = $paymentOption;
        }
        
        if ($environment->environment_type && $environment->isGoogle()) {
            $environment->cloud_storage_type_model = GoogleStorage::getGoogleStorageTypeById($environment->cloud_storage_type);
            $environment->payment_option = AmazonServer::getGooglePaymentOptionById($environment->payment_option_id);
        }
        
        if ($environment->environment_type && $environment->isIBMPVS()) {
            $environment->cloud_storage_type_model = IBMPVSStorage::getIBMPVSStorageTypeById($environment->cloud_storage_type);
            $environment->payment_option = AmazonServer::getIBMPVSPaymentOptionById($environment->payment_option_id);
        }

        if($currencies)
        {
            $region->currencies;
        }
        $environment->currency;
        $responseData = $environment->toArray();

        $project = $environment->project;

        if (!$environment->isExisting() && $existing) {
            $responseData['existing_softwares'] = $existing->getDistinctSoftware()->all();
        }

        return response()->json($responseData);
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $environment = new Environment;

        if(!$this->setData($environment)) {
            return response()->json($this->messages, 500);
        }

        $environment->environmentType;
        $provider = $environment->provider;

        if($provider) {
            $regions = $provider->regions;
            $instanceCategories = $provider->instanceCategories;
            $osSoftwares = $provider->osSoftwares;
        }

        $region =  $environment->region;
        $currencies = null;

        if($region) $currencies = $region->currencies;

        if($currencies) $region->currencies;

        $environment->currency;

        return response()->json($environment->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update a project's environment attributes
     * 
     * @param int $id The environment ID
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $environment = Environment::find($id);
        if(!$this->setData($environment)) {
            return response()->json($this->messages, 500);
        }
        $emailer = new EmailController;
        //Converged specifically drops extra configs, then saves appliances
        if (!$environment->isExisting() && $environment->environmentType->name == "Converged") {

            $interconnectData = Request::input('interconnects');
            $newInterconnects = [];
            $interconnects = [];

            if ($interconnectData) {
                foreach ($interconnectData as $interconnectDatum) {
                    if (!$interconnectDatum['id']) {
                        continue;
                    }

                    $originalId = false;

                    if (!isset($interconnectDatum['environment_id']) || !$interconnectDatum['environment_id']) {
                        $originalId = $interconnectDatum['id'];
                        unset($interconnectDatum['id']);
                    }

                    $interconnectDatum["environment_id"] = $environment->id;
                    $interconnect = InterconnectChassisController::updateFromObject($interconnectDatum);

                    if ($originalId) {
                        $newInterconnects[$interconnect->id] = $originalId;
                    }

                    $interconnects[] = $interconnect->id;
                }

                $collection = collect($interconnects);
                $environment->interconnects()->whereNotIn('id', $collection->values()->all())->delete();

            } else {
                $environment->interconnects()->delete();
            }

            $configs = Request::input('serverConfigs');
            $retConfs = [];
            if($configs) {
                foreach($configs as $config) {
                    $conf = null;
                    if (Arr::exists($config, 'delete') && $config['delete']) {
                        $conf = ServerConfigurationController::updateFromObject($config);
                    }
                    if ($conf) {
                        $conf->processor;
                        $conf->chassis;
                        $conf->model;
                        $conf->manufacturer;
                        $conf->os;
                        $conf->middleware;
                        $conf->hypervisor;
                        $conf->database;
                        if (is_object($conf->chassis)) {
                            $conf->chassis->model;
                        }
                        $retConfs[] = $conf;
                    }
                }
                $environment->serverConfigs = $retConfs;
            }

            $appliances = Request::input('appliances');
            $retApps = [];
            if($appliances) {
                foreach($appliances as $appliance) {
                    if ($newInterconnects && $appliance['interconnect_id'] && in_array($appliance['interconnect_id'], $newInterconnects)) {
                        foreach($newInterconnects as $correctId => $uuid) {
                            if ($uuid == $appliance['interconnect_id']) {
                                $appliance['interconnect_id'] = $correctId;
                            }
                        }
                    }
                    if (!isset($appliance['interconnect_id'])) {
                        $appliance['interconnect_id'] = null;
                    }
                    if ($appliance['interconnect_id'] && !in_array($appliance['interconnect_id'], $interconnects)) {
                        $appliance['interconnect_id'] = null;
                    }
                    $app = ServerConfigurationController::updateFromObject($appliance);
                    $nodes = $appliance['nodes'];
                    foreach($nodes as $node) {
                        //Make sure they're d
                        if($app)
                            $node['parent_configuration_id'] = $app->id;
                        $node['environment_id'] = $id;
                        $n = ServerConfigurationController::updateFromObject($node);
                    }
                    if($app) {
                        $app->nodes;
                        $app->interconnect;
                        foreach($app->nodes as $n) {
                            $n->processor;
                            $n->server;
                            $n->manufacturer;
                            $n->os;
                            $n->middleware;
                            $n->hypervisor;
                            $n->database;
                        }
                        $app->processor;
                        $app->server;
                        $app->manufacturer;
                        $retApps[] = $app;
                        if (Arr::exists($appliance, 'addToLibrary') && $appliance['addToLibrary']) {
                            $libraryApp = $app->replicate();
                            $libraryApp->environment_id = null;
                            $libraryApp->user_id = Auth::user()->user->id;
                            $libraryApp->save();
                            foreach($app->nodes as $node) {
                                $libraryNode = $node->replicate();
                                $libraryNode->parent_configuration_id = $libraryApp->id;
                                $libraryNode->environment_id = null;
                                $libraryNode->user_id = Auth::user()->user->id;
                                $libraryNode->save();
                            }
                            $emailer->sendNewConfigEmail("Hardware", $libraryApp->id);
                        }
                    }
                }
                $environment->appliances = $retApps;
            }
        } else {
            if ($environment->interconnects) {
                $environment->interconnects()->delete();
            }
            //Otherwise, save as normal
            $configs = Request::input('serverConfigs');
            $retConfs = [];

            if($configs) {
                // Set the current environment
                Registry::register('current_environment', $environment);

                // Parent Manufacturer
                $manufacturer = isset($configs[0]['manufacturer']) ?  $configs[0]['manufacturer']['name'] : '';

                foreach($configs as $config) {
                    if (is_null($config)) continue;

                    unset($config['interconnect_id']);

                    // Set manufacturer same as parent
                    if (isset($configs[0]['manufacturer']) && $configs[0]['manufacturer']['name'] == 'IBM') {
                        $config['is_ibm'] = true;
                    }

                    $conf = ServerConfigurationController::updateFromObject($config);

                    if($conf) {
                        //* append the InstanceCategory object to the serverConfig's selected "instance_category"
                        if ($conf->instance_category) {
                            $conf->instance_category_object = InstanceCategory::where(
                                'name',
                                $conf->instance_category
                            )
                            ->first();
                        }

                        //* append the OsSoftware object to the serverConfig's selected software
                        // if ($conf->database_li_name) {
                        //     $conf->os_software = OsSoftware::where(
                        //             'name',
                        //             $conf->database_li_name
                        //         )
                        //         ->first();
                        // }

                        $conf->processor;
                        $conf->chassis;

                        if (is_object($conf->chassis)) {
                            $conf->chassis->model;
                        }

                        $conf->server;
                        $conf->manufacturer;
                        $conf->os;
                        $conf->middleware;
                        $conf->hypervisor;
                        $conf->database;
                        $retConfs[] = $conf;
                    }

                    if (Arr::exists($config, 'addToLibrary') && $config['addToLibrary'] && $conf) {
                        $libraryConf = $conf->replicate();
                        $libraryConf->environment_id = null;
                        $libraryConf->user_id = Auth::user()->user->id;
                        $libraryConf->save();
                        $emailer->sendNewConfigEmail("Hardware", $libraryConf->id);
                    }

                    if ($conf && $environment->isExisting() && $environment->isPhysicalVm() && $conf->isPhysical()) {
                        // Set the last physical server that was updated
                        Registry::register('last_physical_server_configuration', $conf);
                    }

                }

                $environment->serverConfigs = $retConfs;
            }
        }

        $softwareCosts = Request::input('softwareCosts');
        $retCosts = [];
        if($softwareCosts) {
            foreach($softwareCosts as $softwareCost) {
                if (!$environment->isVm()) {
                    $softwareCost['physical_processors'] = null;
                    $softwareCost['physical_cores'] = null;
                }
                $cost = SoftwareCostController::updateFromObject($softwareCost);
                if($cost) {
                    $retCosts[] = $cost;
                }
            }
            $environment->usedSoftwareCosts = $retCosts;
        }


        $environment->environmentType;
        $provider = $environment->provider;

        if($provider) {
            $regions = $provider->regions;
            $instanceCategories = $provider->instanceCategories;
            $osSoftwares = $provider->osSoftwares;
            
            if ($provider->name === Provider::AZURE) {
                $provider['ads_service_options'] = AzureAdsServiceOption::all();
            }
        }

        $region =  $environment->region;
        $currencies = null;

        if($region) $currencies = $region->currencies;

        if($currencies) $region->currencies;

        $environment->currency;
        $environment->interconnects;

        if ($environment->interconnects) {
            foreach($environment->interconnects as $interconnect) {
                if (isset($newInterconnects[$interconnect->id])) {
                    $interconnect->setUuid($newInterconnects[$interconnect->id]);
                }
                $interconnect->model;
                $interconnect->manufacturer;
            }
        }
        $environment->setAppends([]);

        if ($environment->isExisting()) {
           // Check if environment is physical
           // See the Environment model for functions to get the type
           // (I'm adding methods to it since old dev apparently didn't know about class methods -__-
           if ($environment->isPhysical()) {
               $environment->serverConfigurations()->where('type', ServerConfiguration::TYPE_VM)->delete();
           }

           // Do something similar for if environment is VM, but then remove physicals, and update all vms to not have a parent:
           if ($environment->isVm()) {
               $environment->serverConfigurations()->where('type', ServerConfiguration::TYPE_PHYSICAL)->delete();
               for ($i = 0; $i < count($environment->serverConfigs); $i++) {
                 $config = $environment->serverConfigs[$i];

                 if ($config->isPhysical()) {
                     // Remove from response
                     unset($environment->serverConfigs[$i]);

                     // Remove from database
                     $config->delete();

                } else {
                     // Do something similar for if environment is VM, but then remove physicals, and update all vms to not have a parent:
                    $config->physical_configuration_id = null;
                    $config->save();
                }
               }
           }

           if ($environment->isPhysicalVm()) {
               $environment->serverConfigurations()->where('type', ServerConfiguration::TYPE_VM)->whereNull('physical_configuration_id')->delete();
           } else if ($environment->isVm()) {
               // Deleting non cloud targets
               $deleteCount = 0;
               $environment->project->environments->filter(function(Environment $environment){
                   return !$environment->isExisting() && $environment->environmentType && !$environment->isCloud();
               })->each(function(Environment $environment) use (&$deleteCount) {
                   $deleteCount++;
                   $environment->delete();
               });

               if ($deleteCount) {

                   $cloudCount = $environment->project->environments->filter(function (Environment $environment) {
                       return !$environment->isExisting() && (!$environment->environmentType || $environment->isCloud());
                   })->count();

                   for ($x = 1; $x <= $deleteCount; $x++) {
                       $counter = $x + $cloudCount;
                       $target_environment = new Environment([
                           'name' => "Target $counter",
                           'is_existing' => 0
                       ]);
                       $target_environment->setEnvironmentType(EnvironmentType::ID_CLOUD);
                       $environment->project->environments()->save($target_environment);
                   }
                   unset($environment->project->environments);
                   $environment->project->environments;
               }
           }
        }

        return response()->json($environment->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Environment::destroy($id);

        // return a success
        return response()->json("Destroy Successful");
    }

    protected function cacheTargetAnalysis($id) {
        $environment = Environment::where("id", "=", $id)
            ->with('serverConfigurations')
            ->with('environmentType')
            ->first();
        if(!$environment)
            return response()->json("Environment not found", 404);
        if(Request::has('target_analysis')) {
            $result = json_decode(Request::input('target_analysis'));
            if(json_last_error() !== JSON_ERROR_NONE)
                return response()->json("Please provide a valid, json encoded analysis", 400);

            $environment->target_analysis = Request::input('target_analysis');
            $environment->save();
            foreach($environment->serverConfigurations as $config) {
                $config->manufacturer;
                $config->processor;
                $config->server;
            };
            return response()->json($environment->toArray());
        } else {
            return response()->json("Please provide an analysis for an existing Environment", 400);
        }
    }
}
