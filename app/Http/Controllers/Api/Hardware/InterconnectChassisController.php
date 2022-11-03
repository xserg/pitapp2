<?php

namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use App\Models\Hardware\InterconnectChassis;
use App\Models\Hardware\InterconnectChassisModel;
use App\Models\UserManagement\User;
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

class InterconnectChassisController extends Controller {

    protected $model = InterconnectChassis::class;

    protected $activity = 'Interconnect/Chassis Management';
    protected $table = 'hardware_intrconnect_chassis';

    public function index() {

      $isAdminRequest = false;
      if (Request::has('query')){
        $isAdminRequest = Request::input('query') != "true";
        $query = InterconnectChassis::leftJoin('hardware_interconnect_chassis_model', 'hardware_interconnect_chassis.model_id', '=', 'hardware_interconnect_chassis_model.id')
            ->leftJoin('manufacturers','hardware_interconnect_chassis.manufacturer_id' , '=', 'manufacturers.id')
            ->leftJoin('environments', 'hardware_interconnect_chassis.environment_id', '=', 'environments.id');
                if(!$isAdminRequest) {
                    //If this is an admin request, we only want to ignore environmental configs.
                    //If front end, ignore all but global and the ones we own
                    $query->where(function($query) {
                        $query->whereNull('hardware_interconnect_chassis.user_id')
                        ->orWhere('hardware_interconnect_chassis.user_id', '=', Auth::user()->user->id);
                    });
                }
                $query->whereNull('hardware_interconnect_chassis.environment_id')->with('model', 'manufacturer', 'user')
                ->select(
                'manufacturers.id as manufacturer_id',
                'manufacturers.name as manufacturer_name',

                'hardware_interconnect_chassis_model.id as model_id',
                'hardware_interconnect_chassis_model.name as model_name',
                'hardware_interconnect_chassis.*',

                'environments.id as environment_id',
                'environments.name as environment_name');

          //  if(Request::input('filter')){
          if(!$isAdminRequest) {
              $filter = trim(Request::input('filter') ?: '');
              if (strstr($filter, '$scope')) {
                  $filter = '';
              }
              /*$words = explode(' ',$filter);
              foreach($words as $word){
                  $query->where(function($query) use($word){
                    $query->where('processors.name', 'like', '%' . $word .'%' )
                        ->orWhere('servers.name', 'like', '%' . $word .'%' )
                        ->orWhere('manufacturers.name', 'like', '%' . $word .'%' );
                  });
              }*/
              if ($filter) {
                  $query->where('manufacturers.name', 'like', '%' . $filter . '%')
                      ->orWhere('hardware_interconnect_chassis_model.name', 'like', '%' . $filter . '%');
              }
          } else {
              $filter = Request::input('query');
              $filter = json_decode($filter);
              $model = isset($filter->server_name) ? $filter->server_name : '';
              $man = isset($filter->manufacturer_name) ? $filter->manufacturer_name : '';

              $query->where(function($query) use($model, $man){
                $query->where('hardware_interconnect_chassis_model.name', 'like', '%' . $model .'%' )->with('model', 'manufacturer', 'user')
                    ->orWhere('manufacturers.name', 'like', '%' . $man .'%' )
                    ->select(
                        'manufacturers.id as manufacturer_id',
                        'manufacturers.name as manufacturer_name',

                        'hardware_interconnect_chassis_model.id as model_id',
                        'hardware_interconnect_chassis_model.name as model_name',
                        'hardware_interconnect_chassis.*',
                        'environments.id as environment_id',
                        'environments.name as environment_name');
              });
          }

          //  }

      } else {
          $query = InterconnectChassis::whereNull('environment_id')->with('model', 'manufacturer', 'user')
              ->leftJoin('hardware_interconnect_chassis_model', 'hardware_interconnect_chassis.model_id', '=', 'hardware_interconnect_chassis_model.id')
              ->leftJoin('manufacturers', 'hardware_interconnect_chassis.manufacturer_id', '=', 'manufacturers.id')
              ->select(
                  'manufacturers.id as manufacturer_id',
                  'manufacturers.name as manufacturer_name',

                  'hardware_interconnect_chassis_model.id as model_id',
                  'hardware_interconnect_chassis_model.name as model_name',
                  'hardware_interconnect_chassis.*');
          if (!strstr($_SERVER['HTTP_HOST'], 'admin')) {
              $query->where(function ($query) {
                  $query->whereNull('hardware_interconnect_chassis.user_id')
                      ->orWhere('hardware_interconnect_chassis.user_id', '=', Auth::user()->user->id);
              });
          }
      }

      $query->orderBy('manufacturers.name')->orderBy('hardware_interconnect_chassis_model.name');
      $count = $query->get();
      $count = count($count);

      if(Request::has('limit')){
        if(Request::has('page')) {
            $query->offset((Request::input('page') - 1) * Request::input('limit'));
        }
        $query->limit(Request::input('limit'));
      }
      //Apply this filter. Only globally available or configs associated with your users should be
      //returned
      //$query->whereNull('user_id')
      //        ->orWhere('user_id', '=', Auth::user()->user->id);
      $query->with('model', 'manufacturer', 'user');
      $items = $query->get();
      //  $items = ServerConfiguration::all();

      foreach ($items as $key=>&$item)
      {
          if($item->user_id) {
              $owner = User::where('id', $item->user_id)->first();
              if ($owner) {
                  $item->owner = $owner->firstName . ' ' . $owner->lastName;
              } else {
                  $item->owner = 'None (Global)';
              }
          }
          else {
              $item->owner = 'None (Global)';
          }


          $item->manufacturer_name = $item->manufacturer ? $item->manufacturer->name : "";
          $item->model_name = $item->model ? $item->model->name : "";
          $item->type = $item->model->type ?: 'chassis';

      }
      if(Request::has('query') && !$isAdminRequest) {
        $response = (object)['data' => $items, 'count' => $count];
        return response()->json([$response]);
      }
      return response()->json($items);
    }

    protected function getUserConfigs($id) {

        if (Request::has('query')){
          $query = InterconnectChassis::with('model', 'manufacturer')
              ->whereNull('environment_id')
              ->where(function($query) use ($id) {
                  $query->where('user_id', '=', $id);
                  $query->orWhereNull('user_id');
              });

            $words = explode(' ', Request::input('filter'));

            foreach($words as $word){
                $query->where(function($query) use($word){
                  $query->where('model.name', 'like', '%' . $word .'%' )
                      ->orWhere('model.manufacturer.name', 'like', '%' . $word .'%' )
                      ->orWhere('description', 'like', '%' . $word .'%' );
                });
            }
            $count = $query->count();

            if(Request::has('limit')){
                $query->limit(Request::input('limit'));
                if(Request::has('page')){
                    $query->offset((Request::input('page') - 1) * Request::input('limit'));
                }
            }

            $items = $query->get();

            $response = (object)['data' => $items, 'count' => $count];
            return response()->json([$response]);

        } else {
          $items = InterconnectChassis::with('model', 'manufacturer')
              ->where(function($query) use ($id) {
                  $query->where('user_id', '=', $id);
                  $query->orWhereNull('user_id');
                })
              ->whereNull('environment_id')->get();
          return response()->json($items);
        }
    }

    public function chassisRackList() {
        $query = InterconnectChassis::whereNull('environment_id')
            ->where('hardware_interconnect_chassis.type', 'chassis')
            ->leftJoin('hardware_interconnect_chassis_model', 'hardware_interconnect_chassis.model_id', '=', 'hardware_interconnect_chassis_model.id')
            ->leftJoin('manufacturers','hardware_interconnect_chassis.manufacturer_id' , '=', 'manufacturers.id')
            ->select(
                'manufacturers.id as manufacturer_id',
                'manufacturers.name as manufacturer_name',

                'hardware_interconnect_chassis_model.id as model_id',
                'hardware_interconnect_chassis_model.name as _model_name',
                'hardware_interconnect_chassis.*')
            ->selectRaw('CONCAT(IFNULL(manufacturers.name,\'\'), \':\', IFNULL(hardware_interconnect_chassis_model.name,\'\')) as model_name');

        $query->where(function ($query) {
            $query->whereNull('hardware_interconnect_chassis.user_id')
                ->orWhere('hardware_interconnect_chassis.user_id', '=', Auth::user()->user->id);
        });

        echo json_encode($query->get());
    }

    protected function show($id){
        $item = InterconnectChassis::with('model', 'manufacturer')->find($id);

        return response()->json($item->toArray());
    }

    protected function store(){
        $this->validateData();
        $item = new InterconnectChassis();
        $this->setData($item);
        $item = InterconnectChassis::where('id', '=', $item->id)
                              ->with(['manufacturer', 'model'])->first();
        
        $emailer = new EmailController;
        if($item->user_id && $item->user_id == Auth::user()->user->id && $item->parent_configuration_id == null) {
            $emailer->sendNewConfigEmail("Interconnect/Chassis", $item->id);
        }
        $addToLibrary = Request::input('addToLibrary');
        if($addToLibrary) {
            $libraryItem = $item->replicate();
            $libraryItem->environment_id = null;
            $libraryItem->user_id = Auth::user()->user->id;
            //Library id is the parent id of the server config created for the library
            $libraryId = Request::input("library_id");
            if($libraryId) {
                $libraryItem->save();
            } else {
                $libraryItem->save();
                $emailer->sendNewConfigEmail("Hardware", $libraryItem->id);
                $item->library_id = $libraryItem->id;
            }

        }
        return response()->json($item->toArray());
        //return response()->json("Create Successful");
    }

    protected function update($id) {
        $this->validateData();
        $item = InterconnectChassis::find($id);
        $this->setData($item);
        $item = InterconnectChassis::where('id', '=', $item->id)
                              ->with(['manufacturer', 'model'])->first();

        return response()->json($item->toArray());
        //return response()->json("Update Successful");
    }

    protected function destroy($id){
        InterconnectChassis::destroy($id);

        return response()->json("Destroy Successful");
    }
/*
if(Request::has('manufacturer')) {
    $manufacturer = Request::input('manufacturer');
    if(gettype($manufacturer) != 'string' && isset($manufacturer["id"])) {
        $item->manufacturer_id = $manufacturer["id"];
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
               Request::merge(['manufacturer_id' => $m->id]);
               Request::merge(['manufacturer' => $m]);
           } elseif (gettype($manufacturer) == 'array') {
               $m = Manufacturer::where('id', '=', $manufacturer['id'])->first();

               if(!$m) {
                   $m = new Manufacturer();
                   $m->name = $manufacturer['name'];
                   $m->save();

               }
               Request::merge(['manufacturer_id' => $m->id]);
               Request::merge(['manufacturer' => $m]);
           }
         }
       return $request;
     }

    private function CreateModelIfNone($request){
        if(Request::has('model')) {
            $model = Request::input('model');
            $type = Request::input('type');
            if(gettype($model) == 'string') {
                $m = InterconnectChassisModel::where('name', '=', gettype($model) != 'string' ? $model['name'] : $model)
                    ->where('type', '=', $type)
                    ->first();

                if(!$m) {
                    $m = new InterconnectChassisModel();
                    $m->name = gettype($model) != 'string' ? $model['name'] : $model;
                    $m->type = $type;
                    $m->save();

                }
                Request::merge(['model_id' => $m->id]);
                Request::merge(['model' => $m]);
            } elseif (gettype($model) == 'array') {
                $m = InterconnectChassisModel::where('id', '=', $model['id'])
                    ->where('type', '=', $type)
                    ->first();

                if(!$m) {
                    $m = new InterconnectChassisModel();
                    $m->name = $model['name'];
                    $m->type = $type;
                    $m->save();

                }
                Request::merge(['model_id' => $m->id]);
                Request::merge(['model' => $m]);
            }
        }
        return $request;
    }

     private function validateData(){
         $request = Request::all();
         $envId = Request::input("environment_id");
         $env = Environment::find($envId);

         // $request['manufacturer_id'] = 1;
         $request = $this->createManufacturerIfNone($request);
         $request = $this->createModelIfNone($request);
         $validator = Validator::make(
            $request,
            array(
                'environment_id' => 'integer | exists:environments,id|nullable',
                'manufacturer_id' => 'integer | exists:manufacturers,id|nullable',
                'model_id' => 'integer | exists:hardware_interconnect_chassis_model,id|nullable',
                'user_id' => 'integer | exists:users,id|nullable',
                'rack_units' => 'integer|nullable'
        ));
         if ($validator->fails()) {
            abort(400,json_encode($validator->messages()));
        }

        return $request;
     }

    private function setData(&$item){


        $item->model_id = Request::input('model_id');
        $item->environment_id = Request::input('environment_id');
        $item->manufacturer_id = Request::input('manufacturer_id');
        $item->rack_units = Request::input('rack_units');
        $item->num_ports = Request::input('num_ports');
        $item->num_nodes = Request::input('num_nodes');
        $item->nodes_per_unit = Request::input('nodes_per_unit');
        $item->hardware_list_price = Request::input('hardware_list_price');
        $item->discount = Request::input('discount');
        $item->annual_maintenance_list_price = Request::input('annual_maintenance_list_price');
        $item->annual_maintenance_discount = Request::input('annual_maintenance_discount');
        $item->user_id = Request::input('user_id');
        $item->type = Request::input('type');
        $item->description = Request::input('description');


//        if(Request::exists('model')) {
//            $model = Request::input('model');
//            if($model == null || $model == "") {
//                $item->model_id = null;
//            } else {
//                if(gettype($model) != 'string' && isset($model["id"])) {
//                    $item->model_id = $model["id"];
//                } else {
//                    $m = InterconnectChassisModeler::where('name', '=', gettype($model) != 'string' ? $model['name'] : $model)->first();
//                    if(!$m) {
//                        $m = new Manufacturer;
//                        $m->name = gettype($model) != 'string' ? $model['name'] : $model;
//                        $m->save();
//                    }
//                    $item->model_id = $m->id;
//                }
//            }
//        }
//
//        if(Request::exists('manufacturer')) {
//            $manufact = Request::input('manufacturer');
//            if($manufact == null || $manufact == "") {
//                $item->manufacturer_id = null;
//            } else {
//                if(gettype($manufact) != 'string' && isset($manufact["id"])) {
//                    $item->manufacturer_id = $manufact["id"];
//                } else {
//                    $m = InterconnectChassisModeler::where('name', '=', gettype($manufact) != 'string' ? $manufact['name'] : $manufact)->first();
//                    if(!$m) {
//                        $m = new Manufacturer;
//                        $m->name = gettype($manufact) != 'string' ? $manufact['name'] : $manufact;
//                        $m->save();
//                    }
//                    $item->manufacturer_id = $m->id;
//                }
//            }
//        }

        $item->save();
    }

    public static function updateFromObject($item) {
        if(self::returnIfExists($item, 'delete')) {
            if(self::returnIfExists($item, 'id'))
                InterconnectChassis::destroy($item['id']);
            return;
        }
        $itemModel= null;
        if(self::returnIfExists($item, 'id')) {
            $itemModel = InterconnectChassis::find($item['id']);
        } else {
            $itemModel = new InterconnectChassis();
        }

        $itemModel->manufacturer_id = self::returnIfExists($item, 'manufacturer_id');
        $itemModel->model_id = self::returnIfExists($item, 'model_id');
        $itemModel->environment_id = self::returnIfExists($item, 'environment_id');
        $itemModel->rack_units = self::returnIfExists($item, 'rack_units');
        $itemModel->user_id = self::returnIfExists($item, 'user_id');
        $itemModel->description = self::returnIfExists($item, 'description');
        $itemModel->num_ports = self::returnIfExists($item, 'num_ports');
        $itemModel->nodes_per_unit = self::returnIfExists($item, 'nodes_per_unit');
        $itemModel->num_nodes = self::returnIfExists($item, 'num_nodes');
        $itemModel->hardware_list_price = self::returnIfExists($item, 'hardware_list_price');
        $itemModel->discount = self::returnIfExists($item, 'discount');
        $itemModel->annual_maintenance_list_price = self::returnIfExists($item, 'annual_maintenance_list_price');
        $itemModel->name = self::returnIfExists($item, 'name');
        $itemModel->annual_maintenance_discount = self::returnIfExists($item, 'annual_maintenance_discount');

        if(self::returnIfExists($item, 'manufacturer')) {
            $manufacturer = $item['manufacturer'];
            if($manufacturer == null || $manufacturer == "") {
                $itemModel->manufacturer_id = null;
            } else {
                if(gettype($manufacturer) != 'string' && isset($manufacturer["id"])) {
                    $itemModel->manufacturer_id = $manufacturer["id"];
                } else {
                    $m = Manufacturer::where('name', '=', gettype($manufacturer) != 'string' ? $manufacturer['name'] : $manufacturer)->first();
                    if(!$m) {
                        $m = new Manufacturer;
                        $m->name = gettype($manufacturer) != 'string' ? $manufacturer['name'] : $manufacturer;
                        $m->save();
                    }
                    $itemModel->manufacturer_id = $m->id;
                }
            }
        }
        if(self::returnIfExists($item, 'model')) {
            $model = $item['model'];
            if($model == null || $model == "") {
                $itemModel->model_id = null;
            } else {
                if(gettype($model) != 'string' && isset($model["id"])) {
                    $itemModel->model_id = $model["id"];
                } else {
                    $m = InterconnectChassisModel::where('name', '=', gettype($model) != 'string' ? $manufacturer['name'] : $model)->first();
                    if(!$m) {
                        $m = new InterconnectChassisModel();
                        $m->name = gettype($model) != 'string' ? $model['name'] : $model;
                        $m->save();
                    }
                    $itemModel->model_id = $m->id;
                }
            }
        }

        $itemModel->save();
        return $itemModel;
    }
    private static function returnIfExists($config, $index) {
        return Arr::exists($config, $index) ? $config[$index] : null;
    }
}
