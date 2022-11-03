<?php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Software\FeatureCost;
use App\Models\Software\Software;


class FeatureCostController extends Controller {

    protected $model = "App\Models\Software\FeatureCost";

    protected $activity = 'FeatureCost Management';
    protected $table = 'feature_costs';

    // Private Method to set the data
    private function setData(&$featureCost) {
        // Set all the data here (fill in all the fields)
        $featureCost->feature_id = Request::input('feature_id');
        $featureCost->software_cost_id = Request::input('software_cost_id');
        $featureCost->license_cost_discount = Request::input('license_cost_discount');
        $featureCost->support_cost_discount = Request::input('support_cost_discount');

        // Save the platform
        $featureCost->save();
    }

    private function validateData(){
        $validator = Validator::make(
            Request::all(),
            array(
                'feature_id' => 'required | integer | exists:features,id',
            'software_cost_id' => 'required | integer | exists:software_costs,id',
            'license_cost_discount' => 'required | numeric',
            'support_cost_discount' => 'required | numeric'
        ));

        if ($validator->fails()) {
            abort(400, 'Invalid input ' . json_encode($validator->messages()));
        }
    }

    protected function index() {
        $featureCosts = FeatureCost::all();

        return response()->json($featureCosts);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {

        $featureCost = FeatureCost::find($id);

        return response()->json($featureCost->toArray());
    }

    /**
     * Store Method
     */
    protected function store($id=null) {
        // Create item and set the data
        $this->validateData();

        $featureCost = new FeatureCost;
        $this->setData($featureCost);

        return response()->json($featureCost->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        $this->validateData();
        // Retrieve the item and set the data
        $featureCost = FeatureCost::find($id);
        $this->setData($featureCost);

        return response()->json($featureCost->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        FeatureCost::destroy($id);

        // return a success
        return response()->json("Destory Successful");
    }
}
