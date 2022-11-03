<?php

namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use App\Services\Registry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Environment;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Hardware\Server;
use App\Models\Hardware\Manufacturer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\Project\EmailController;

class ServerConfigurationController extends Controller {

    protected $model = ServerConfiguration::class;

    protected $activity = 'ServerConfig Management';
    protected $table = 'server_configurations';

    /**
     * @return bool
     */
    protected function _isAdmin()
    {
        return isset($_SERVER) && isset($_SERVER['HTTP_HOST']) && strstr($_SERVER['HTTP_HOST'], 'admin');
    }

    protected function index() {

      $isAdminRequest = false;
      if (Request::has('query')) {
        $isAdminRequest = Request::input('query') != "true";
        $query = ServerConfiguration::leftJoin('manufacturers','server_configurations.manufacturer_id' , '=', 'manufacturers.id')
                ->leftJoin('processors', 'server_configurations.processor_id', '=', 'processors.id')
                ->leftJoin('servers', 'server_configurations.model_id', '=', 'servers.id')
                ->leftJoin('environments', 'server_configurations.environment_id', '=', 'environments.id');
                if(!$isAdminRequest) {
                    //If this is an admin request, we only want to ignore environmental configs.
                    //If front end, ignore all but global and the ones we own
                    $query->where(function($query) {
                        $query->whereNull('server_configurations.user_id')
                        ->orWhere('server_configurations.user_id', '=', Auth::user()->user->id);
                    });
                }
                $query->whereNull('server_configurations.environment_id')
                ->whereNull('server_configurations.parent_configuration_id')
                ->select(
                'manufacturers.id as manufacturer_id',
                'manufacturers.name as manufacturer_name',
                'servers.id as server_id',
                'servers.name as server_name',
                'processors.id as processor_id',
                'processors.name as processor_name',
                'processors.architecture as processor_architecture',
                'processors.core_qty as processor_core_qty',
                'processors.socket_qty as processor_socket_qty',
                'processors.rpm as processor_rpm',
                'processors.ghz as processor_ghz',
                'server_configurations.*',
                'environments.id as environment_id',
                'environments.name as environment_name',
                'environments.cpu_utilization as environment_cpu_utilization',
                'environments.ram_utilization as environment_ram_utilization');

          //  if(Request::input('filter')){
          if(!$isAdminRequest) {
              $manufacturer = Request::input('manufacturer');
              $modelNumber = Request::input('modelNumber');
              $processor = Request::input('processor');
              $ram = Request::input('ram');
              $processor = str_ireplace("power 5", "power5", $processor);
              $processor = str_ireplace("power 6", "power6", $processor);
              $processor = str_ireplace("power 7", "power7", $processor);
              $processor = str_ireplace("power 8", "power8", $processor);
              if($manufacturer) {
                  $query->where('manufacturers.name', 'like', '%' . $manufacturer . '%');
              }
              if($modelNumber) {
                  $query->where('servers.name', 'like', '%' . $modelNumber . '%');
              }
              if(Request::input('converged') != 'true') {
                  if($processor) {
                      $query->where('processors.name', 'like', '%' . $processor . '%');
                  }
                  if($ram) {
                      $query->where('server_configurations.ram', '=', $ram);
                  }
              } else {
                  //Extra join in case
                  if($processor) {
                      $query->leftJoin('server_configurations as procNodes', 'server_configurations.id', '=', 'procNodes.parent_configuration_id');
                      $query->leftJoin('processors as nodeProcs', 'nodeProcs.id', '=', 'procNodes.processor_id');
                      $query->where('nodeProcs.name', 'like', '%' . $processor . '%');
                  }
                  if($ram) {
                      $query->leftJoin('server_configurations as ramNodes', 'server_configurations.id', '=', 'ramNodes.parent_configuration_id');
                      $query->havingRaw('SUM(ramNodes.ram * ramNodes.qty) = ' . $ram);
                  }
                  // $query->groupBy('server_configurations.id');
              }
          } else {
              $filter = Request::input('query');
              $filter = json_decode($filter);
              $serv = isset($filter->server_name) ? $filter->server_name : '';
              $proc = isset($filter->processor_name) ? $filter->processor_name : '';
              $man = isset($filter->manufacturer_name) ? $filter->manufacturer_name : '';

              $query->where(function($query) use($serv, $proc, $man){
                $query->where('processors.name', 'like', '%' . $proc .'%' )
                    ->orWhere('servers.name', 'like', '%' . $serv .'%' )
                    ->orWhere('manufacturers.name', 'like', '%' . $man .'%' );
              });
          }

          //  }

      }else{
        $query = ServerConfiguration::whereNull('environment_id')->whereNull('parent_configuration_id');
      }

      if (Request::has('converged')){
          if (Request::input('converged') == 'true') {
              $query->where('server_configurations.is_converged', '=', 1);
          } else {
              $query->where('server_configurations.is_converged', '=', 0);
          }
      }
      $query->orderBy('processors.name')->orderBy('processors.ghz')->orderBy('processors.socket_qty');
      $count = $query->get();
      $count = count($count);

      if (Request::has('limit')){
        if (Request::has('page')) {
            $query->offset((Request::input('page') - 1) * Request::input('limit'));
        }
        $query->limit(Request::input('limit'));
      }
      //Apply this filter. Only globally available or configs associated with your users should be
      //returned
      //$query->whereNull('user_id')
      //        ->orWhere('user_id', '=', Auth::user()->user->id);
      $query->with(['manufacturer', 'processor', 'server', 'environment', 'nodes',
                  'os', 'hypervisor', 'middleware', 'database', 'nodes.manufacturer',
                  'nodes.processor', 'nodes.server', 'nodes.os', 'nodes.hypervisor',
                  'nodes.middleware', 'nodes.database']);
      $scs = $query->get();
      //  $scs = ServerConfiguration::all();

      foreach ($scs as $key => &$sc)
      {
          $sc->owner = $sc->user ? $sc->user->firstName . ' ' . $sc->user->lastName : 'None (Global)';
          $sc->manufacturer_name = $sc->manufacturer ? $sc->manufacturer->name : '';
          $sc->processor_name = $sc->processor ? $sc->processor->name : '';
          $sc->server_name = $sc->server ? $sc->server->name : '';
          if ($sc->is_converged) {
              $this->convergedConfigTotals($sc);
          }
      }

      if (!$this->_isAdmin()) {
          $scs->each(function(ServerConfiguration $serverConfiguration) {
              if ($serverConfiguration->processor) {
                  $serverConfiguration->processor->rpm = null;
              }
              if ($serverConfiguration->nodes) {
                  $serverConfiguration->nodes->each(function(ServerConfiguration $node){
                      $node->processor->rpm = null;
                  });
              }
          });
      }

      if (Request::has('query') && !$isAdminRequest) {
        $response = (object)['data' => $scs, 'count' => $count];
        return response()->json([$response]);
      }
      return response()->json($scs);
    }

    private function convergedConfigTotals(&$parent) {
        $totalRam = 0;
        $totalSockets = 0;
        $totalCores = 0;
        $ghz = [];
        $processors = [];
        $cores = [];
        $totalStorage = 0;
        $useableStorage = 0;
        $iops = 0;
        foreach($parent->nodes as $node) {
            $totalRam += $node->ram * $node->qty;
            if (isset($node->processor) && $node->processor) {
                $totalSockets += $node->processor->socket_qty * $node->qty;
                $totalCores += $node->processor->socket_qty * $node->processor->core_qty * $node->qty;
                $ghz[] = $node->processor->ghz;
                $cores[] = $node->processor->core_qty;
                $processors[] = $node->processor->name;
            }
            $totalStorage += $node->raw_storage * $node->qty;
            $iops += $node->iops;
            $useableStorage += $node->useable_storage * $node->qty;
        }
        $parent->raw_storage = $totalStorage;
        $parent->useable_storage = $useableStorage;
        $parent->iops = $iops;
        $parent->ram = $totalRam;
        $parent->processor_ghz = implode(', ', $ghz);
        $parent->processor_name = implode(', ', $processors);
        $parent->processor_core_qty = implode(', ', $cores);
        $parent->processor_socket_qty = $totalSockets;
        $parent->processor_total_cores = $totalCores;
    }

    protected function getUserConfigs($id) {

        if (Request::has('query')){
          $query = ServerConfiguration::leftJoin('manufacturers','server_configurations.manufacturer_id' , '=', 'manufacturers.id')
                  ->leftJoin('processors', 'server_configurations.processor_id', '=', 'processors.id')
                  ->leftJoin('servers', 'server_configurations.model_id', '=', 'servers.id')
                  ->leftJoin('environments', 'server_configurations.environment_id', '=', 'environments.id')
                  ->where(function($query) use ($id) {
                      $query->whereNull('server_configurations.user_id')
                      ->orWhere('server_configurations.user_id', '=', $id);
                  })
                  ->whereNull('server_configurations.parent_configuration_id')
                  ->whereNull('server_configurations.environment_id')
                  ->select(
                  'manufacturers.name as manufacturer_name',
                  'servers.name as server_name',
                  'processors.name as processor_name',
                  'server_configurations.*')
                  ->with('manufacturer', 'processor', 'server',
                  'os', 'hypervisor', 'middleware', 'database'/*,
                  'osMod', 'hypervisorMod', 'middlewareMod', 'databaseMod'*/);

            $words = explode(' ', Request::input('filter'));

            foreach($words as $word){
                $query->where(function($query) use($word){
                  $query->where('processors.name', 'like', '%' . $word .'%' )
                      ->orWhere('servers.name', 'like', '%' . $word .'%' )
                      ->orWhere('manufacturers.name', 'like', '%' . $word .'%' );
                });
            }
            $count = $query->get();
            $count = count($count);

            if(Request::has('limit')){
                $query->limit(Request::input('limit'));
                if(Request::has('page')){
                    $query->offset((Request::input('page') - 1) * Request::input('limit'));
                }
            }

            $configs = $query->get();
            foreach($configs as $config) {
                if($config->is_converged) {
                    $converged = ServerConfiguration::where('parent_configuration_id', '=', $config->id)
                                ->with('manufacturer', 'processor', 'server',
                                'os', 'hypervisor', 'middleware', 'database'/*,
                                'osMod', 'hypervisorMod', 'middlewareMod', 'databaseMod'*/)
                                ->get();
                    foreach($converged as $conv) {
                        $configs->add($conv);
                    }

                    $this->convergedConfigTotals($config);
                }
            }

            if (!$this->_isAdmin()) {
                $configs->each(function (ServerConfiguration $configuration) {
                    if ($configuration->nodes) {
                        $configuration->nodes->each(function (ServerConfiguration $node) {
                            if ($node->processor) {
                                $node->processor->rpm = null;
                            }
                        });
                    }
                    if ($configuration->processor) {
                        $configuration->processor->rpm = null;
                    }
                });
            }

            $response = (object)['data' => $configs, 'count' => $count];
            return response()->json([$response]);

        } else {
          $configs = ServerConfiguration::where(function($query) use ($id) {
                                              $query->where('user_id', '=', $id);
                                              $query->orWhereNull('user_id');
                                            })
                                          ->whereNull('environment_id')
                                          ->with('manufacturer', 'processor', 'server',
                                          'os', 'hypervisor', 'middleware', 'database'/*,
                                          'osMod', 'hypervisorMod', 'middlewareMod', 'databaseMod'*/)->get();
          if (!$this->_isAdmin()) {
              $configs->each(function (ServerConfiguration $configuration) {
                  if ($configuration->nodes) {
                      $configuration->nodes->each(function (ServerConfiguration $node) {
                          if ($node->processor) {
                              $node->processor->rpm = null;
                          }
                      });
                  }
                  if ($configuration->processor) {
                      $configuration->processor->rpm = null;
                  }
              });
          }
          return response()->json($configs);
        }
    }

    protected function show($id){
        $sc = ServerConfiguration::with('manufacturer', 'processor', 'server', 'environment', 'chassis')->find($id);
        $nodes = ServerConfiguration::with('manufacturer', 'processor', 'server', 'chassis')->where('parent_configuration_id', '=', $id)->get();
        $sc->nodes = $nodes;
        //$sc->manufacturer;
        //$sc->processor;
        //$sc->server;
        //$sc->environment;
        return response()->json($sc->toArray());
    }

    protected function showByEnvironment($serverConfigId,$id){
        $params = array();
        $params['environment_id'] = $id;
        $scs = ServerConfiguration::where($params)->with('manufacturer', 'processor', 'server', 'chassis',
                                    'environment', 'os.softwareType', 'middleware.softwareType',
                                    'hypervisor.softwareType', 'database.softwareType',
                                    'os.features', 'middleware.features', //'databaseLi.softwareType', 'databaseLi.features',
                                    'hypervisor.features', 'database.features', 'nodes',
                                    'nodes.os', 'nodes.hypervisor', 'nodes.middleware', 'nodes.database')
                                    ->orderBy('order', 'asc')->get();
        for($i=0; $i<count($scs) ; $i++)
        {
            foreach ($scs[$i]->nodes as $node) {
                $node->manufacturer;
                $node->processor;
                $node->server;
            }

        }
        return response()->json($scs->toArray());

    }

    protected function store(){
        $this->validateData();
        $sc = new ServerConfiguration;
        $this->setData($sc);
        $sc = ServerConfiguration::where('id', '=', $sc->id)
                              ->with(['manufacturer', 'processor', 'server', 'environment', 'nodes', 'chassis',
                                      'os', 'hypervisor', 'middleware', 'database', 'nodes.manufacturer',
                                      'nodes.processor', 'nodes.server', 'nodes.os', 'nodes.hypervisor',
                                      'nodes.middleware', 'nodes.database'])->first();
        /*$sc->manufacturer;
        $sc->processor;
        $sc->server;
        $sc->environment;
        if($sc->os)
            $sc->os->softwareType;
        if($sc->middleware)
            $sc->middleware->softwareType;
        if($sc->hypervisor)
            $sc->hypervisor->softwareType;
        if($sc->database)
            $sc->database->softwareType;*/
        /*if($sc->databaseLi)
            $sc->databaseLi->softwareType;*/
        /*$sc->osMod;
        $sc->middlewareMod;
        $sc->hypervisorMod;
        $sc->databaseMod;*/

        $emailer = new EmailController;
        if($sc->user_id && $sc->user_id == Auth::user()->user->id && $sc->parent_configuration_id == null) {
            $emailer->sendNewConfigEmail("Hardware", $sc->id);
        }
        $addToLibrary = Request::input('addToLibrary');
        if($addToLibrary) {
            $librarySc = $sc->replicate();
            $librarySc->environment_id = null;
            $librarySc->user_id = Auth::user()->user->id;
            //Library id is the parent id of the server config created for the library
            $libraryId = Request::input("library_id");
            if($libraryId) {
                $librarySc->parent_configuration_id = $libraryId;
                $librarySc->save();
            } else {
                $librarySc->save();
                $emailer->sendNewConfigEmail("Hardware", $librarySc->id);
                $sc->library_id = $librarySc->id;
            }

        }
        return response()->json($sc->toArray());
        //return response()->json("Create Successful");
    }

    protected function update($id) {
        $this->validateData();
        $sc = ServerConfiguration::find($id);
        $this->setData($sc);
        $sc = ServerConfiguration::where('id', '=', $sc->id)
                              ->with(['manufacturer', 'processor', 'server', 'environment', 'nodes',
                                      'os', 'hypervisor', 'middleware', 'database', 'nodes.manufacturer',
                                      'nodes.processor', 'nodes.server', 'nodes.os', 'nodes.hypervisor',
                                      'nodes.middleware', 'nodes.database'])->first();
        /*$sc->manufacturer;
        $sc->processor;
        $sc->server;
        $sc->environment;

        if($sc->os)
            $sc->os->softwareType;
        if($sc->middleware)
            $sc->middleware->softwareType;
        if($sc->hypervisor)
            $sc->hypervisor->softwareType;
        if($sc->database)
            $sc->database->softwareType;*/
        /*if($sc->databaseLi)
            $sc->databaseLi->softwareType;*/
        /*$sc->osMod;
        $sc->middlewareMod;
        $sc->hypervisorMod;
        $sc->databaseMod;*/

        return response()->json($sc->toArray());
        //return response()->json("Update Successful");
    }

    protected function destroy($id){
        ServerConfiguration::destroy($id);

        return response()->json("Destroy Successful");
    }
/*
if(Request::has('manufacturer')) {
    $manufacturer = Request::input('manufacturer');
    if(gettype($manufacturer) != 'string' && isset($manufacturer["id"])) {
        $sc->manufacturer_id = $manufacturer["id"];
    } else {
*/
     private function createManufacturerIfNone($request){
       if(Request::has('manufacturer')) {
           $manufacturer = Request::input('manufacturer');
           if(gettype($manufacturer) == 'string') {
             $m = Manufacturer::where('name', '=', gettype($manufacturer) != 'string' ? $manufacturer['name'] : $manufacturer)->first();

             if(!$m) {
                 $m = new Manufacturer;
                 $m->name = gettype($manufacturer) != 'string' ? $manufacturer['name'] : $manufacturer;
                 $m->save();

             }
             $request['manufacturer_id'] = $m->id;
             $request['manufacturer'] = $m;
           }
         }
       return $request;
     }

     private function validateData(){
         $request = Request::all();
         $envId = Request::input("environment_id");
         $env = Environment::find($envId);
         if($env) {
             if($env->is_existing) {
                $manufacturer = 'integer | exists:manufacturers,id|nullable';
                $model = 'integer | exists:servers,id|nullable';
             } else {
                $manufacturer = 'integer | exists:manufacturers,id|nullable';
                $model = 'integer | exists:servers,id|nullable';
             }
         } else {
             $manufacturer = 'integer | exists:manufacturers,id|nullable';
             $model = 'integer | exists:servers,id|nullable';
         }
         // set ram to integer because frontend is sending string
         if (isset($request['ram'])) {
            $request['ram'] = intval($request['ram']);
         }
         // $request['manufacturer_id'] = 1;
         $request = $this->createManufacturerIfNone($request);
         $validator = Validator::make(
            $request,
            array(
                'environment_id' => 'integer | exists:environments,id|nullable',
                'manufacturer_id' => $manufacturer,
                'model_id' => $model,
                'processor_id' => 'integer | exists:processors,id|nullable',
                'chassis_id' => 'integer | exists:hardware_interconnect_chassis,id|nullable',
                'user_id' => 'integer | exists:users,id|nullable',
                'parent_configuration_id' => 'integer | exists:server_configurations,id|nullable',
                'ram' =>  'integer|nullable',
                'rack_units' => 'integer|nullable',
                'kilo_watts' => 'numeric|nullable',
                'acquisition_cost' => 'numeric|nullable',
                'os_annual_maintenance' => 'integer|nullable',
                'hdw_annual_maintenance' => 'integer|nullable',
                'discount_rate' => 'numeric|nullable' ,
                'system_software_list_price' => 'numeric|nullable',
                'system_software_discount_rate' => 'numeric|nullable',
                'hardware_warranty_period' => 'integer|nullable',
                'annual_system_software_maintenance_list_price' => 'numeric|nullable',
                'annual_system_software_maintenance_discount_rate' => 'numeric|nullable',
                'annual_usage_list_price' => 'numeric|nullable',
                'annual_usage_discount_rate' => 'numeric|nullable',
                'node_qty' => 'integer|nullable',
                'bandwidth_per_month' => 'integer|nullable',
                'workload_type' => 'string | max:255|nullable',
                'environment_detail' => 'string | max:255|nullable',
                'qty' => 'integer|nullable',
                'pending_review' => 'integer|nullable',
                'is_converged' => 'boolean|nullable'
        ));
         if ($validator->fails()) {
            abort(400,json_encode($validator->messages()));
        }
     }

    private function setData(&$sc){
        $sc->manufacturer_id = Request::input('manufacturer_id');
        //jdobrowolski. These fields moved to the processor table
        //$sc->processor_qty = Request::input('processor_qty');
        //$sc->core_qty = Request::input('core_qty');

        $sc->model_id = Request::input('model_id');
        $sc->ram = Request::input('ram');
        $sc->environment_id = Request::input('environment_id');
        $sc->rack_units = Request::input('rack_units');
        $sc->kilo_watts = Request::input('kilo_watts');
        $sc->acquisition_cost = Request::input('acquisition_cost');
        $sc->os_annual_maintenance = Request::input('os_annual_maintenance');
        $sc->hdw_annual_maintenance = Request::input('hdw_annual_maintenance');
        $sc->discount_rate = Request::input('discount_rate') ? Request::input('discount_rate') : 0;
        $sc->system_software_list_price = Request::input('system_software_list_price');
        $sc->system_software_discount_rate = Request::input('system_software_discount_rate') ? Request::input('system_software_discount_rate') : 0;
        $sc->hardware_warranty_period = Request::input('hardware_warranty_period') ? Request::input('hardware_warranty_period') : 0;
        $sc->useable_storage = Request::input('useable_storage');
        $sc->raw_storage = Request::input('raw_storage');
        $sc->storage_type = Request::input('storage_type');
        $sc->raw_storage = Request::input('raw_storage');
        $sc->iops = Request::input('iops');
        $sc->node_qty = Request::input('node_qty');
        $sc->bandwidth_per_month = Request::input('bandwidth_per_month');
        $sc->purpose = Request::input('purpose');
        $sc->location = Request::input('location');
        $sc->workload_type = Request::input('workload_type');
        $sc->environment_detail = Request::input('environment_detail');
        $sc->qty = Request::input('qty');
        $sc->pending_review = Request::input('pending_review');
        $sc->processor_id = Request::input('processor_id');
        $sc->user_id = Request::input('user_id');
        $sc->parent_configuration_id = Request::input('parent_configuration_id');
        $sc->is_converged = Request::input('is_converged') ? Request::input('is_converged') : false;
        $sc->annual_maintenance_list_price = Request::input('annual_maintenance_list_price');
        $sc->annual_maintenance_discount_rate = Request::input('annual_maintenance_discount_rate') ? Request::input('annual_maintenance_discount_rate') : 0;
        $sc->annual_system_software_maintenance_list_price = Request::input('annual_system_software_maintenance_list_price');
        $sc->annual_system_software_maintenance_discount_rate = Request::input('annual_system_software_maintenance_discount_rate') ? Request::input('annual_system_software_maintenance_discount_rate') : 0;
        $sc->raw_storage_unit = Request::input('raw_storage_unit');
        $sc->useable_storage_unit = Request::input('useable_storage_unit');
        $sc->bandwidth_per_month_unit = Request::input('bandwidth_per_month_unit');
        $sc->serial_number = Request::input('serial_number');
        $sc->order = Request::input('order');
        $sc->cpu_utilization = Request::input('cpu_utilization');
        $sc->ram_utilization = Request::input('ram_utilization');
        $sc->drive_size = Request::input('drive_size');
        $sc->drive_qty = Request::input('drive_qty');
        $sc->deployment_option = Request::input('deployment_option');
        $sc->instance_category = Request::input('instance_category');
        $sc->description = Request::input('description');
        $sc->annual_usage_list_price = Request::input('annual_usage_list_price');
        $sc->annual_usage_discount_rate = Request::input('annual_usage_discount_rate');

        if (Request::exists('server_id') )
        {
            $sc->model_id = Request::input('server_id');
        }
        if (Request::exists('manufacturer_id') )
        {
            $sc->manufacturer_id = Request::input('manufacturer_id');
        }
        if(Request::exists('manufacturer')) {
            $manufacturer = Request::input('manufacturer');
            if($manufacturer == null || $manufacturer == "") {
                $sc->manufacturer_id = null;
            } else {
                if(gettype($manufacturer) != 'string' && isset($manufacturer["id"])) {
                    $sc->manufacturer_id = $manufacturer["id"];
                } else {
                    $m = Manufacturer::where('name', '=', gettype($manufacturer) != 'string' ? $manufacturer['name'] : $manufacturer)->first();
                    if(!$m) {
                        $m = new Manufacturer;
                        $m->name = gettype($manufacturer) != 'string' ? $manufacturer['name'] : $manufacturer;
                        $m->save();
                    }
                    $sc->manufacturer_id = $m->id;
                }
            }
        }
        if(Request::exists('server')) {
            $server = Request::input('server');
            if($server == null || $server == "") {
                $sc->model_id = null;
            } else {
                if(gettype($server) != 'string' && isset($server["id"])) {
                    $sc->model_id = $server["id"];
                } else {
                    $s = new Server;
                    $s->name = gettype($server) != 'string' ? $server['name'] : $server;
                    $s->manufacturer_id = $sc->manufacturer_id;
                    $s->user_id = Auth::user()->user->id;
                    $s->save();
                    $sc->model_id = $s->id;
                }
            }
        }
        if (Request::exists('processor_id') )
        {
            $sc->processor_id = Request::input('processor_id');
        }
        if (Request::exists('chassis_id') )
        {
            $sc->chassis_id = Request::input('chassis_id');
        }
        if (Request::exists('os_id') )
        {
            $sc->os_id = Request::input('os_id');
        }
        if (Request::exists('middleware_id') )
        {
            $sc->middleware_id = Request::input('middleware_id');
        }
        if (Request::exists('hypervisor_id') )
        {
            $sc->hypervisor_id = Request::input('hypervisor_id');
        }
        if (Request::exists('database_id') )
        {
            $sc->database_id = Request::input('database_id');
        }
        /*if (Request::exists('database_li_id') )
        {
            $sc->database_li_id = Request::input('database_li_id');
        }*/
        if (Request::exists('database_li_name') )
        {
            $sc->database_li_name = Request::input('database_li_name');
        }
        if (Request::exists('database_li_ec2') )
        {
            $sc->database_li_ec2 = Request::input('database_li_ec2');
        }
        if (Request::exists('os_li_name') )
        {
            $sc->os_li_name = Request::input('os_li_name');
        }

        if (Request::exists('ads_database_type')) {
            $sc->ads_database_type = Request::input('ads_database_type');
        }

        if (Request::exists('ads_service_type')) {
            $sc->ads_database_type = Request::input('ads_service_type');
        }

        $sc->os_mod_id = Request::input('os_mod_id') ? Request::input('os_mod_id') : null;
        $sc->hypervisor_mod_id = Request::input('hypervisor_mod_id') ? Request::input('hypervisor_mod_id') : null;
        $sc->middleware_mod_id = Request::input('middleware_mod_id') ? Request::input('middleware_mod_id') : null;
        $sc->database_mod_id = Request::input('database_mod_id') ? Request::input('database_mod_id') : null;

        $sc->save();
    }

    public static function updateFromObject($config) {
        if(self::returnIfExists($config, 'delete')) {
            if(self::returnIfExists($config, 'id')) {
                ServerConfiguration::destroy($config['id']);
            }
            return;
        }
        $sc = null;
        if(self::returnIfExists($config, 'id')) {
            $sc = ServerConfiguration::find($config['id']);
        }

        if (!$sc) {
            $sc = new ServerConfiguration();
        }

        $updateMap = [
            'manufacturer_id' => null,
            'model_id' => null,
            'ram' => null,
            'environment_id' => null,
            'rack_units' => null,
            'kilo_watts' => null,
            'acquisition_cost' => null,
            'os_annual_maintenance' => null,
            'hdw_annual_maintenance' => null,
            'discount_rate' => 0,
            'system_software_list_price' => null,
            'system_software_discount_rate' => null,
            'hardware_warranty_period' => null,
            'annual_system_software_maintenance_list_price' => null,
            'annual_system_software_maintenance_discount_rate' => null,
            'annual_usage_list_price' => null,
            'annual_usage_discount_rate' => null,
            'useable_storage' => null,
            'raw_storage' => null,
            'iops' => null,
            'node_qty' => null,
            'bandwidth_per_month' => null,
            'purpose' => null,
            'location' => null,
            'workload_type' => null,
            'environment_detail' => null,
            'qty' => null,
            'pending_review' => null,
            'processor_id' => null,
            'chassis_id' => null,
            'user_id' => null,
            'parent_configuration_id' => null,
            'interconnect_id' => null,
            'is_converged' => false,
            'annual_maintenance_list_price' => null,
            'annual_maintenance_discount_rate' => 0,
            'raw_storage_unit' => null,
            'useable_storage_unit' => null,
            'storage_type' => null,
            'bandwidth_per_month_unit' => null,
            'serial_number' => null,
            'order' => null,
            'cpu_utilization' => null,
            'ram_utilization' => null,
            'drive_size' => null,
            'drive_qty' => null,
            'deployment_option' => null,
            'instance_category' => null,
            'description' => null,
            'os_id' => null,
            'middleware_id' => null,
            'hypervisor_id' => null,
            'database_id' => null,
            'database_li_name' => null,
            'database_li_ec2' => null,
            'ads_database_type' => null,
            'ads_service_type' => null,
            'os_li_name' => null,
            'type' => 'physical',
            'vm_id' => null,
            'vm_cores' => null,
            'physical_configuration_id' => null,
            'licensed_cores' => null,
            'ads_compute_tier' => null,
            'is_include_burastable' => 1,
        ];

        foreach($updateMap as $key => $default) {
            $sc->{$key} = isset($config[$key]) && !$config[$key] !== '' && $config[$key] !== 'NaN' ? $config[$key] : $default;
        }

        /** @var Environment $environment */
        $environment = Registry::registry('current_environment');

        if ($environment && $environment->isPhysicalVm() && $sc->isVm()) {
            /** @var ServerConfiguration $lastPhysical */
            $lastPhysical = Registry::registry('last_physical_server_configuration');
            if (!$sc->hasPhysicalConfigurationId()) {
                // If we are adding a new VM and it doesn't have the physical parent id
                // we need to pull that out of the registry because it's not available in
                // the request JSON
                if ($lastPhysical && $lastPhysical->id) {
                    $sc->physical_configuration_id = $lastPhysical->id;
                } else {
                    throw new \Exception("Cannot find a physical server for VM ID: " . $sc->vm_id);
                }
            }
            if (floatval($sc->vm_cores) && !$lastPhysical->isPartialCoresSupported()) {
                // Ensure the vm cores are a whole number in the event the processor
                // on the physical server does not support fractional cores
                $sc->vm_cores = max(1, intval($sc->vm_cores));
            } else if (floatval($sc->vm_cores)) {
                // If IBM LPARs set increment to .5
                $sc->vm_cores = round($sc->vm_cores, 2);
            }
        } else if ($environment && $environment->isVm()) {
            // Ensure this never gets set to an integer
            $sc->physical_configuration_id = null;
        }

        if(self::returnIfExists($config, 'manufacturer')) {
            $manufacturer = $config['manufacturer'];
            if($manufacturer == null || $manufacturer == "") {
                $sc->manufacturer_id = null;
            } else {
                if(gettype($manufacturer) != 'string' && isset($manufacturer["id"])) {
                    $sc->manufacturer_id = $manufacturer["id"];
                } else {
                    $m = Manufacturer::where('name', '=', gettype($manufacturer) != 'string' ? $manufacturer['name'] : $manufacturer)->first();
                    if(!$m) {
                        $m = new Manufacturer;
                        $m->name = gettype($manufacturer) != 'string' ? $manufacturer['name'] : $manufacturer;
                        $m->save();
                    }
                    $sc->manufacturer_id = $m->id;
                }
            }
        }
        if(self::returnIfExists($config, 'server')) {
            $server = $config['server'];
            if($server == null || $server == "") {
                $sc->model_id = null;
            } else {
                if(gettype($server) != 'string' && isset($server["id"])) {
                    $sc->model_id = $server["id"];
                } else {
                    $s = new Server;
                    $s->name = gettype($server) != 'string' ? $server['name'] : $server;
                    $s->manufacturer_id = $sc->manufacturer_id;
                    $s->user_id = Auth::user()->user->id;
                    $s->save();
                    $sc->model_id = $s->id;
                }
            }
        }
        $sc->save();
        return $sc;
    }
    private static function returnIfExists($config, $index) {
        return Arr::exists($config, $index) ? $config[$index] : null;
    }
}
