<?php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Software\Software;
use App\Models\Software\Feature;
use App\Models\Software\SoftwareFeature;
use App\Models\Project\Project;
use App\Http\Controllers\Api\Project\EmailController;


class SoftwareController extends Controller {

    protected $model = "App\Models\Software\Software";
    protected $activity = 'Software Management';
    protected $table = 'softwares';

    // Private Method to set the data
    private function setData(&$software) {
        // Set all the data here (fill in all the fields)
        $software->name = Request::input('name');
        $software->architecture = Request::input('architecture');
        $software->type = Request::input('type');
        $software->license_cost = Request::input('license_cost');
        $software->support_cost = Request::input('support_cost');
        $software->support_cost_percent = Request::input('support_cost_percent');
        $software->cost_per = Request::input('cost_per');
        $software->annual_cost_per = Request::input('annual_cost_per');
        $software->license_discount = Request::input('license_discount');
        $software->annual_support_discount = Request::input('annual_support_discount');
        $software->multiplier = Request::input('multiplier');
        $software->support_multiplier = Request::input('support_multiplier');
        $software->support_type = Request::input('support_type');
        $software->user_id = Request::input('user_id');
        //If we want to attach this to a 'project', we assign it to the user that owns that project
        if(Request::input('project_id')) {
            $proj = Project::find(Request::input('project_id'));
            $software->user_id = $proj->user_id;
        }

        //Validate name if creating software from the public site
        if($software->user_id == Auth::user()->user->id) {
            $match = Software::where('name', '=', $software->name)
                            ->where('architecture', '=', $software->architecture)
                            ->where('id', '!=', ($software->id ? $software->id : 0))
                            ->where(function($query) {
                                $query->whereNull('user_id')
                                ->orWhere('user_id', '=', Auth::user()->user->id);
                            })->first();
            if($match != null) {
                return false;
            }
        }

        // Save the platform
        $software->save();

        $features = Request::input('software_features');
        if(isset($features)) {
            $syncArray = [];
            foreach($features as $feature) {
                $syncArray[$feature["feature"]["id"]] = ['is_default'=>!!$feature["is_default"]];
            }
            $software->features()->sync($syncArray);
        }
        $software->softwareType;
        return $software;
    }

    private function validateData(){
        $validator = Validator::make(
            Request::all(),
            array(
                'name' => 'required | string | max:255',
                'type' => 'required | integer',
                'license_cost' => 'required | numeric',
                'support_cost' => 'numeric',
                'support_cost_percent' => 'bail | nullable | numeric',
                'license_discount' => 'required | numeric',
                'annual_support_discount' => 'numeric',
                'multiplier' => 'numeric | nullable',
                'support_multiplier' => 'numeric | nullable',
                'support_type' => 'required | numeric',
                'nup' => 'bail | nullable | numeric'
        ));

        if ($validator->fails()) {
            abort(400, 'Invalid input ' . json_encode($validator->errors()));
        }
    }

    public function index() {
        $requestPage = Request::header('referer');
        $requestPage = strtolower($requestPage);
        $isAdminRequest = strpos($requestPage, 'admin') !== false;
        if (Request::has('query')){
            //$isAdminRequest = Request::input('query') != "true";
            if(!$isAdminRequest) {
                $query = Software::where(function($query) {
                      $query->whereNull('user_id');
                      $query->orWhere('user_id', '=', Auth::user()->user->id);})
                  ->with(array('softwareFeatures' => function($query) {
                        $query->with('feature')->join('features', 'software_features.feature_id', '=', 'features.id')
                        ->whereNull('features.user_id')
                        ->orWhere('features.user_id', '=', Auth::user()->user->id);
                    }, 'softwareType'));
            } else {
                $query = Software::where(function($query) {
                    $query->with(array('softwareFeatures' => function($query) {
                          $query->with('feature')->join('features', 'software_features.feature_id', '=', 'features.id');
                      }, 'softwareType'));
                });
            }

            if(!$isAdminRequest) {
                $words = explode(' ', Request::input('filter'));
                foreach($words as $word){
                    $query->where(function($query) use($word){
                      $query->where('name', 'like', '%' . $word .'%' );
                    });
                }
            } else {
                $q = Request::input('query');
                $q = json_decode($q);
                $name = isset($q->name) ? $q->name : '';
                $query->where('name', 'like', '%' . $name .'%' );
                $type = isset($q->type) ? $q->type : null;
                if($type)
                    $query->where('type', '=', $type);
            }

            $fullQuery = $query->get();
            $count = count($fullQuery);



            if(!$isAdminRequest) {
                $softwares = $fullQuery;
                $query = Feature::where(function($query) {
                      $query->whereNull('user_id');
                      $query->orWhere('user_id', '=', Auth::user()->user->id);
                    });
                foreach($words as $word){
                    $query->where(function($query) use($word){
                      $query->where('name', 'like', '%' . $word .'%' );
                    });
                }
                $features = $query->get();
                $count += count($features);
                /*foreach($softwares as &$software) {
                    $software->full_name = $software->architecture ? $software->name . " (" . $software->architecture . ")" : $software->name;
                }*/
                foreach($features as &$feature) {
                    $feature->full_name = $feature->architecture ? $feature->name . " (" . $feature->architecture . ")" : $feature->name;
                    $feature->software_type = (object)['name' => "Feature"];
                }
                $array = collect($softwares->toArray())->merge(collect($features->toArray()))->toArray();
                $count = count($array);
                $limit = Request::has('limit') ? Request::input('limit') : 20;
                if(Request::has('page')){
                    $page = Request::input('page') - 1;
                } else {
                    $page = 0;
                }
                $collection = collect($array);
                $ret = $collection->slice($page * $limit, $limit);
                
                $response = (object)['data' => $ret, 'count' => $count];
                return response()->json($response);
            } else {
                $limit = Request::has('limit') ? Request::has('limit') : 20;
                if(Request::has('limit')){
                    $query->limit(Request::input('limit'));
                    if(Request::has('page')){
                        $query->offset((Request::input('page') - 1) * Request::input('limit'));
                    }
                }

                $softwares = $query->get();
                foreach($fullQuery as &$software) {
                    $software->software_type_name = $software->softwareType->name;
                    $software->full_name = $software->architecture ? $software->name . " (" . $software->architecture . ")" : $software->name;
                    if($software->support_type == 1)
                        $software->support_cost_calc = $software->support_cost;
                    else
                        $software->support_cost_calc = round($software->license_cost * ($software->support_cost_percent / 100));

                    if($software->user) {
                        $software->owner = $software->user->firstName . ' ' . $software->user->lastName;
                    } else {
                        $software->owner = 'None (Global)';
                    }
                }
                return response()->json($fullQuery->toArray());
            }
        } else {
            if(!$isAdminRequest) {
                $userId = Auth::user()->user->id;
                $softwares = Software::orWhereNull('user_id')
                                        ->orWhere('user_id', '=', $userId)
                                        ->with(array('softwareFeatures' => function($query) use($userId) {
                                              $query->with('feature')->join('features', 'software_features.feature_id', '=', 'features.id')
                                              ->whereNull('features.user_id')
                                              ->orWhere('features.user_id', '=', $userId);
                                          }, 'softwareType'))->get();
            } else {
                $softwares = Software::with(array('softwareFeatures' => function($query) {
                                              $query->with('feature')->join('features', 'software_features.feature_id', '=', 'features.id');
                                          }, 'softwareType'))->get();
            }
            //These attributes are useful for the admin table. We don't need to add them for the front end
            if($isAdminRequest) {
                foreach($softwares as $software) {
                    $software->software_type_name = $software->softwareType->name;
                    $software->full_name = $software->architecture ? $software->name . " (" . $software->architecture . ")" : $software->name;
                    if($software->support_type == 1)
                        $software->support_cost_calc = $software->support_cost;
                    else
                        $software->support_cost_calc = round($software->license_cost * ($software->support_cost_percent / 100.0));

                    if($software->user)
                        $software->owner = $software->user->firstName . ' ' . $software->user->lastName;
                    else
                        $software->owner = 'None (Global)';
                }
            }
            return response()->json($softwares);
        }
    }

    protected function getUserSoftware($id) {
        $softwares = Software::where('user_id', '=', $id)->with('softwareType')->orderBy('type')->get();
        return response()->json($softwares);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $software = Software::with('softwareType', 'softwareFeatures.feature')->find($id);
        foreach($software->softwareFeatures as $sf) {
            $sf->feature;
        }
        return response()->json($software->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {

        $this->validateData();

        $software = new Software;
        $return = $this->setData($software);
        if(!$return) {
            return response()->json("Software name and architecture must be unique", 400);
        }
        $software->softwareType;
        foreach($software->softwareFeatures as $sf) {
            $sf->feature;
        }
        if($software->user_id && $software->user_id == Auth::user()->user->id) {
            $emailer = new EmailController;
            $emailer->sendNewConfigEmail("Software", $software->id);
        }
        return response()->json($software->toArray());
        //return response()->json("Create Successful");
    }



    /**
     * Update Method
     */
    protected function update($id) {
        $this->validateData();
        // Retrieve the item and set the data
        $software = Software::with('softwareType', 'softwareFeatures.feature')->find($id);
        $return = $this->setData($software);
        if(!$return) {
            return response()->json("Software name and architecture must be unique", 400);
        }

        return response()->json($software->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        $software = Software::find($id);
        //Remove any features
        $software->features()->detach();
        // Make the deletion
        Software::destroy($id);

        // return a success
        return response()->json("Destory Successful");
    }

}
