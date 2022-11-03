<?php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Software\Feature;
use App\Models\Software\SoftwareFeature;


class FeatureController extends Controller {

    protected $model = "App\Models\Software\Feature";
    protected $activity = 'Feature Management';
    protected $table = 'features';

    // Private Method to set the data
    private function setData(&$feature) {
        // Set all the data here (fill in all the fields)
        $feature->name = Request::input('name');
        $feature->architecture = Request::input('architecture');
        $feature->license_cost = Request::input('license_cost');
        $feature->support_cost = Request::input('support_cost');
        $feature->support_cost_percent = Request::input('support_cost_percent');
        $feature->cost_per = Request::input('cost_per');
        $feature->annual_cost_per = Request::input('annual_cost_per');
        $feature->license_discount = Request::input('license_discount');
        $feature->annual_support_discount = Request::input('annual_support_discount');
        $feature->multiplier = Request::input('multiplier');
        $feature->support_multiplier = Request::input('support_multiplier');
        $feature->support_type = Request::input('support_type');
        $feature->user_id = Request::input('user_id');

        // Save the platform
        $feature->save();

        //If we pass a software ID, attach it to that software
        if(Request::has('software_id')) {
            $sf = new SoftwareFeature;
            $sf->software_id = Request::input('software_id');
            $sf->feature_id = $feature->id;
            $sf->is_default = false;
            $sf->save();
        }
    }

    private function validateData(){
        $validator = Validator::make(
            Request::all(),
            array(
                'name' => 'required | string | max:50',
                'license_cost' => 'required | numeric',
                'support_cost' => 'numeric',
                'support_cost_percent' => 'numeric | nullable',
                'license_discount' => 'required | numeric',
                'annual_support_discount' => 'numeric',
                'multiplier' => 'numeric',
                'support_multiplier' => 'numeric',
                'support_type' => 'required | numeric',
                'nup' => 'numeric | nullable'
        ));

        if ($validator->fails()) {
            abort(400, 'Invalid input ' . json_encode($validator->errors()));
        }
    }

    protected function index() {
        if (Request::has('query')){
            $q = Request::input('query');
            $q = json_decode($q);
            $name = isset($q->name) ? $q->name : '';
            $query = Feature::where('name', 'like', '%' . $name .'%' );

            $fullQuery = $query->get();
            return response()->json($fullQuery->toArray());
        }
        $features = Feature::whereNull('user_id')
                                ->orWhere('user_id', '=', Auth::user()->user->id)->get();
        return response()->json($features);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $feature = Feature::find($id);

        return response()->json($feature->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {

        $this->validateData();

        $feature = new Feature;
        $this->setData($feature);

        return response()->json($feature->toArray());
        //return response()->json("Create Successful");
    }



    /**
     * Update Method
     */
    protected function update($id) {
        $this->validateData();
        // Retrieve the item and set the data
        $feature = Feature::find($id);
        $this->setData($feature);

        return response()->json($feature->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Feature::destroy($id);

        // return a success
        return response()->json("Destory Successful");
    }

}
