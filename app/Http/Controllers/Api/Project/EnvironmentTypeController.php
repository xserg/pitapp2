<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use App\Models\Project\EnvironmentType;

class EnvironmentTypeController extends Controller {

    protected $model = "App\Models\Project\EnvironmentType";

    protected $activity = 'Environment Type Management';
    protected $table = 'environment_types';

    // Private Method to set the data
    private function setData(&$environment) {
        // Set all the data here (fill in all the fields)
        $environment->name = Request::input('name');

        // Save the platform
        $environment->save();
    }

    protected function index() {
        $environments = EnvironmentType::all();

        return response()->json($environments);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $environment = EnvironmentType::find($id);

        return response()->json($environment->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $environment = new EnvironmentType;
        $this->setData($environment);

        return response()->json($environment->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $environment = EnvironmentType::find($id);
        $this->setData($environment);

        return response()->json($environment->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        EnvironmentType::destroy($id);

        // return a success
        return response()->json("Destroy Successful");
    }
}
