<?php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Software\SoftwareCost;
use App\Models\Software\Software;


class SoftwareCostController extends Controller {

    protected $model = "App\Models\Software\SoftwareCost";

    protected $activity = 'SoftwareCost Management';
    protected $table = 'software_costs';

    // Private Method to set the data
    private function setData(&$softwareCost) {
        // Set all the data here (fill in all the fields)
        $softwareCost->environment_id = Request::input('environment_id');
        $softwareCost->software_type_id = Request::input('software_type_id');
        $softwareCost->license_cost = Request::input('license_cost') | 0;
        $softwareCost->support_cost = Request::input('support_cost') | 0;
        $softwareCost->license_cost_modifier = Request::input('license_cost_modifier');
        $softwareCost->support_cost_modifier = Request::input('support_cost_modifier');
        $softwareCost->annual_license_cost = Request::input('annual_license_cost') | 0;
        $softwareCost->annual_maintenance_cost = Request::input('annual_maintenance_cost') | 0;

        // Save the platform
        $softwareCost->save();

        $fcs = Request::input('feature_costs');
        if(isset($fcs)) {
            $syncArray = [];
            foreach($fcs as $fc) {
                $syncArray[$fc["feature"]["id"]] = ['license_cost_discount'=>$fc["license_cost_discount"],
                                                    'support_cost_discount'=>$fc["support_cost_discount"]];
            }
            $softwareCost->features()->sync($syncArray);
        }
        foreach($softwareCost->featureCosts as $fc) {
            $fc->feature;
        }
        foreach($softwareCost->software->softwareFeatures as $sf) {
            $sf->feature;
        }
    }

    private function validateData(){
        $validator = Validator::make(
            Request::all(),
            array(
                'environment_id' => 'required | integer | exists:environments,id',
            'software_type_id' => 'required | integer | exists:softwares,id',
            'license_cost' => 'integer',
            'support_cost' => 'integer',
            'license_cost_modifier' => 'required | numeric',
            'support_cost_modifier' => 'required | numeric',
            'annual_license_cost' => 'integer',
            'annual_maintenance_cost' => 'integer'
        ));

        if ($validator->fails()) {
            abort(400, 'Invalid input ' . json_encode($validator->messages()));
        }
    }

    protected function index() {
        $softwareCosts = SoftwareCost::all();

        return response()->json($softwareCosts);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {

        $softwareCost = SoftwareCost::find($id);

        return response()->json($softwareCost->toArray());
    }

    /**
     * Store Method
     */
    protected function store($id=null) {
        // Create item and set the data
        $this->validateData();

        $softwareCost = new SoftwareCost;
        $this->setData($softwareCost);
        $softwareCost->software->softwareType;
        return response()->json($softwareCost->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        $this->validateData();
        // Retrieve the item and set the data
        $softwareCost = SoftwareCost::with('software.softwareType')->find($id);
        $this->setData($softwareCost);

        return response()->json($softwareCost->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        SoftwareCost::destroy($id);

        // return a success
        return response()->json((object)["id" => "Destroy Successful"]);
    }
    protected function showByEnvironment($id,$environmentID)
    {
        $params = array();
        $params['environment_id'] = $environmentID;
        $softwareCost = SoftwareCost::where($params)->get();
        for($i=0;  $i < count($softwareCost); $i++)
        {
            $softwareType = Software::find($softwareCost[$i]['software_type_id']);
            $softwareCost[$i]['softwareType'] = $softwareType;
        }
        return response()->json($softwareCost->toArray());
    }

    public static function updateFromObject($cost) {

        if(self::returnIfExists($cost, 'deleted')) {
            if(self::returnIfExists($cost, 'id'))
                SoftwareCost::destroy($cost['id']);
            return;
        }
        $sc = null;
        if(self::returnIfExists($cost, 'id')) {
            $sc = SoftwareCost::find($cost['id']);
        } else {
            $sc = new SoftwareCost();
        }

        $sc->environment_id = self::returnIfExists($cost, 'environment_id');
        $sc->software_type_id = self::returnIfExists($cost, 'software_type_id');
        $sc->license_cost = self::returnIfExists($cost, 'license_cost') | 0;
        $sc->support_cost = self::returnIfExists($cost, 'support_cost') | 0;
        $sc->license_cost_modifier = self::returnIfExists($cost, 'license_cost_modifier');
        $sc->support_cost_modifier = self::returnIfExists($cost, 'support_cost_modifier');
        $sc->annual_license_cost = self::returnIfExists($cost, 'annual_license_cost') | 0;
        $sc->annual_maintenance_cost = self::returnIfExists($cost, 'annual_maintenance_cost') | 0;
        $sc->physical_processors = self::returnIfExists($cost, 'physical_processors');
        $sc->physical_cores = self::returnIfExists($cost, 'physical_cores');

        $sc->save();

        $fcs = self::returnIfExists($cost, 'feature_costs');
        if(isset($fcs)) {
            $syncArray = [];
            foreach($fcs as $fc) {
                $syncArray[$fc["feature"]["id"]] = ['license_cost_discount'=>$fc["license_cost_discount"],
                                                    'support_cost_discount'=>$fc["support_cost_discount"]];
            }
            $sc->features()->sync($syncArray);
        }
        foreach($sc->featureCosts as $fc) {
            $fc->feature;
        }
        foreach($sc->software->softwareFeatures as $sf) {
            $sf->feature;
        }
        $sc->software->softwareType;
        return $sc;
    }

    private static function returnIfExists($cost, $index) {
        return Arr::exists($cost, $index) ? $cost[$index] : null;
    }
}
