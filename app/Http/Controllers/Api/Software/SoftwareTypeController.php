<?php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Software\SoftwareType;


class SoftwareTypeController extends Controller {

    protected $model = "App\Models\Software\SoftwareType";
    protected $activity = 'SoftwareType Type Management';
    protected $table = 'software_types';

    // Private Method to set the data
    private function setData(&$softwares) {
        // Set all the data here (fill in all the fields)
        $softwares->name = Request::input('name');
        // Save the platform
        $softwares->save();
    }

    private function validateData(){
        $validator = Validator::make(
            Request::all(),
            array(
                'name' => 'required | string | max:50'
        ));

        if ($validator->fails()) {
            abort(400, 'Invalid input ' . json_encode($validator->errors()));
        }
    }

    protected function index() {
        $softwares = SoftwareType::all();
        return response()->json($softwares);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $software = SoftwareType::find($id);

        return response()->json($software->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {

        $this->validateData();

        $software = new SoftwareType;
        $this->setData($software);

        return response()->json($software->toArray());
        //return response()->json("Create Successful");
    }



    /**
     * Update Method
     */
    protected function update($id) {
        $this->validateData();
        // Retrieve the item and set the data
        $software = SoftwareType::find($id);
        $this->setData($software);

        return response()->json($software->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        SoftwareType::destroy($id);

        // return a success
        return response()->json("Destory Successful");
    }

}
