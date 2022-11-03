<?php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Software\SoftwareModifier;
use App\Models\Project\Project;


class SoftwareModifierController extends Controller {

    protected $model = "App\Models\Software\SoftwareModifier";
    protected $activity = 'Software Modifier Management';
    protected $table = 'software_modifiers';

    // Private Method to set the data
    private function setData(&$softwares) {
        // Set all the data here (fill in all the fields)
        $softwares->license_cost_discount = Request::input('license_cost_discount') ? Request::input('license_cost_discount') : 0;
        $softwares->support_cost_discount = Request::input('support_cost_discount') ? Request::input('support_cost_discount') : 0;

        $softwares->save();
    }

    /*private function validateData(){
        $validator = Validator::make(
            Request::all(),
            array(
                'license_cost_discount' => 'required | integer',
                'support_cost_discount' => 'required | integer'
        ));

        if ($validator->fails()) {
            abort(400, 'Invalid input ' . json_encode($validator->errors()));
        }
    }*/

    protected function index() {
        $softwares = SoftwareModifier::all();
        return response()->json($softwares);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $software = SoftwareModifier::find($id);

        return response()->json($software->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {

        //$this->validateData();

        $software = new SoftwareModifier;
        $this->setData($software);

        return response()->json($software->toArray());
        //return response()->json("Create Successful");
    }



    /**
     * Update Method
     */
    protected function update($id) {
        //$this->validateData();
        // Retrieve the item and set the data
        $software = SoftwareModifier::find($id);
        $this->setData($software);

        return response()->json($software->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        SoftwareModifier::destroy($id);

        // return a success
        return response()->json("Destory Successful");
    }

}
