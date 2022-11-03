<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Environment;

class ProjectEnvironmentController extends Controller {

    protected $activity = 'Environment Management';
    protected $table = 'environments';

    // Private Method to set the data
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
        $environment->environment_type = Request::input('environment_type');
        $environment->server_qty = Request::input('server_qty');
        $environment->socket_qty = Request::input('socket_qty');
        $environment->core_qty = Request::input('core_qty');
        $environment->variance = Request::input('variance');
        $environment->max_utilization = Request::input('max_utilization');
        $environment->cpu_utilization = Request::input('cpu_utilization');
        $environment->ram_utilization = Request::input('ram_utilization');
        $environment->fte_qty = Request::input('fte_qty');
        $environment->remaining_deprecation = Request::input('remaining_deprecation');
        $environment->migration_services = Request::input('migration_services');
        $environment->currency = Request::input('currency');
        $environment->region = Request::input('region');
        $environment->is_existing = Request::input('is_existing');
        // Save the platform
        $environment->savae();
        return true;
    }

    protected function index($projectId) {
        //Order by should return the existing environment first
        $environments = Environment::where("project_id", "=", $projectId)
                                  ->orderBy("is_existing", "desc")
                                  ->orderBy("id", "asc")
                                  ->with([/*'serverConfigurations',*/ 'environmentType', /*'serverConfigurations.manufacturer',
                                          'serverConfigurations.processor', 'serverConfigurations.server',*/ 'project'])
                                  ->get();
        /*foreach($environments as $environment) {
            $environment->project;
            foreach($environment->serverConfigurations as $config) {
                $config->manufacturer;
                $config->processor;
                $config->server;
            }
        }*/
        foreach($environments as &$e) {
            $e->target_analysis = $e->target_analysis ? true : null;
        }
        return response()->json($environments);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($projectId, $id) {
        $environment = Environment::where("id", "=", $id)
                                  ->where("project_id", "=", $projectId)
                                  ->with('serverConfigurations')
                                  ->with('environmentType')
                                  ->first();
        foreach($environments->serverConfigurations as $config) {
            $config->manufacturer;
            $config->chassis;
            $config->processor;
            $config->server;
        }
        return response()->json($environment->toArray());
    }

    /**
     * Store Method
     */
    protected function store($projectId) {
        // Create item and set the data
        $environment = new Environment;
        $environment->project_id = $projectId;
        if(!$this->setData($environment)) {
            return response()->json($this->messages, 500);
        }
        return response()->json($environment->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($projectId, $id) {
        // Retrieve the item and set the data
        $environment = Environment::where("id", "=", $id)
                                  ->where("project_id", "=", $projectId)
                                  ->first();
        if(!$this->setData($environment)) {
            return response()->json($this->messages, 500);
        }

        return response()->json($environment->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($projectId, $id) {
        // Make the deletion
        $environment = Environment::where("id", "=", $id)
                                  ->where("project_id", "=", $projectId)
                                  ->get();
        if($environment) {
            Environment::destroy($id);
            // return a success
            return response()->json("Destroy Successful");
        }
        return response()->json("Environment not found");
    }
}
