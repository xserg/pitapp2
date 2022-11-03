<?php namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Hardware\Server;
use App\Models\Hardware\Manufacturer;

class ServerController extends Controller {

    protected $server = "App\Models\Hardware\Server";
    protected $activity = 'Server Management';
    protected $table = 'servers';

    // Private Method to set the data
    private function setData(&$server) {
        // Set all the data here (fill in all the fields)
        $server->name = Request::input('name');
        $server->manufacturer_id = Request::input('manufacturer_id');
        $server->min_ram = Request::exists('min_ram') ? Request::input('min_ram') : 0;
        $server->max_ram = Request::exists('max_ram') ? Request::input('max_ram') : 0;
        $server->min_cpu = Request::exists('min_cpu') ? Request::input('min_cpu') : 0;
        $server->max_cpu = Request::exists('max_cpu') ? Request::input('max_cpu') : 0;

       // Request::exists('pending_review') ? Request::input('pending_review') : 0;
        // Save the platform
        $server->save();
        return $server->id;
    }

    public function index() {
        if(Request::exists('manufacturer')){
            $query = DB::table('servers')
                       ->select('servers.name', 'servers.id');
            if(Request::input('manufacturer') !== '*'){
              $query->where('servers.manufacturer_id', '=', Request::input('manufacturer'));
            }
              return response()->json($query->get());
        }
        $requestPage = Request::header('referer');
        $requestPage = strtolower($requestPage);
        $isAdminRequest = strpos($requestPage, 'admin') !== false;
        if(!$isAdminRequest) {
            $upload_path = '/core/hardware_cache/servers.json';
            if(!file_exists(public_path() . $upload_path)) {
                $this->cacheServers();
            } else {
                $mtime = filemtime(public_path() . $upload_path);
                $mDate = date('Y-m-d H:i:s', $mtime);
                $recache = Server::where('updated_at', '>', $mDate)->first();
                if($recache) {
                    $this->cacheServers();
                }
            }
            $fp = fopen(public_path() . $upload_path, 'r');
            if(!headers_sent($filename, $linenum))
                header("Content-Type: text/json");
            fpassthru($fp);
            return;
        } else {
            $servers = Server::all();
            for($i=0; $i<count($servers) ; $i++)
            {
                $servers[$i]->manufacturer;
            }
        }

        /*
        //Decorate with manufacturer_name
        $idNameMap = []; //Don't query more than necessary
        foreach($servers as $server){
          if (!Arr::exists($idNameMap, $server['manufacturer_id'])){
            $idNameMap[$server['manufacturer_id']] = Manufacturer::find($server['manufacturer_id'])['name'];
          }
          $server['manufacturer_name']  =  $idNameMap[$server['manufacturer_id']];
        } */

        return response()->json($servers);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $server = Server::find($id);
        $server->manufacturer;

        return response()->json($server->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $server = new Server;
        $id = $this->setData($server);

        return response()->json($server->toArray());
        //return response()->json(['id' => $id]);
    }

    /**
     * Update Method
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $server = Server::find($id);
        $this->setData($server);

        return response()->json($server->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Server::destroy($id);
        $this->cacheServers();
        // return a success
        return response()->json("Destroy Successful");
    }

    private function cacheServers() {
        $servers = Server::select('id', 'manufacturer_id', 'name', 'user_id')->with('manufacturer')->get();
        $servers = $servers->toArray();
        $servers = json_encode($servers);
        $upload_path = '/core/hardware_cache/';
        if(!is_dir(public_path() . $upload_path)) {
            mkdir(public_path() . $upload_path, 0755, true);
        }
        $fp = fopen(public_path() . $upload_path . 'servers.json', 'w');
        fwrite($fp, $servers);
        fclose($fp);
    }
}
