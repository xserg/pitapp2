<?php namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Hardware\AmazonServer;

class AmazonServerController extends Controller {

    protected $server = "App\Models\Hardware\AmazonServer";
    protected $activity = 'Server Management';
    protected $table = 'amazon_servers';

    // Private Method to set the data
    private function setData(&$server) {
        // Set all the data here (fill in all the fields)
        $server->name = Request::input('name');
        $server->socket_qty = Request::input('socket_qty');
        $server->ram = Request::input('ram');
        $server->max_ram = Request::input('server_type');
        $server->min_cpu = Request::exists('environment_id') ? Request::input('environment_id') : null;

       // Request::exists('pending_review') ? Request::input('pending_review') : 0;
        // Save the platform
        $server->save();
        return $server->id;
    }

    /**
     * Get distint OS/Software type(software_type) for given provider
     * 
     * @param string $instanceType The instance_type to get OS/Software for
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    protected function softwareType() {
        $instanceType = Request::get('provider');
        $softwareTypes = DB::table('amazon_servers')
            ->select(['software_type', 'os_name'])
            ->where('instance_type', $instanceType)
            ->groupBy('software_type')
            ->get();

        return response()->json($softwareTypes->toArray());
    }

    protected function index() {
        $servers = AmazonServer::all();
        return response()->json($servers);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $server = AmazonServer::find($id);

        return response()->json($server->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $server = new AmazonServer;
        $id = $this->setData($server);

        return response()->json($server->toArray());
        //return response()->json(['id' => $id]);
    }

    /**
     * Update Method
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $server = AmazonServer::find($id);
        $this->setData($server);

        return response()->json($server->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        AmazonServer::destroy($id);

        // return a success
        return response()->json("Destroy Successful");
    }
}
