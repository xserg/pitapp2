<?php namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use App\Models\Hardware\Manufacturer;

class ManufacturerController extends Controller {

    protected $model = "App\Models\Hardware\Manufacturer";
    protected $activity = 'Manufacturer Management';
    protected $table = 'manufacturers';

    // Private Method to set the data
    private function setData(&$manufacturer) {
        // Set all the data here (fill in all the fields)
        $manufacturer->name = Request::input('name');

        // Save the platform
        $manufacturer->save();
        return $manufacturer->id;

    }

    /*
    if(Request::exists('distinct')){
        $types = DB::table('processors')->select('name', 'id')->distinct()->groupBy('name')->get();
        return response()->json($types);
    }*/

    public function index() {
        $requestPage = Request::header('referer');
        $requestPage = strtolower($requestPage);
        $isAdminRequest = strpos($requestPage, 'admin') !== false;
        if(!$isAdminRequest) {
            $upload_path = '/core/hardware_cache/manufacturers.json';
            if(!file_exists(public_path() . $upload_path)) {
                $this->cacheManufacturers();
            } else {
                $mtime = filemtime(public_path() . $upload_path);
                $mDate = date('Y-m-d H:i:s', $mtime);
                $recache = Manufacturer::where('updated_at', '>', $mDate)->first();
                if($recache) {
                    $this->cacheManufacturers();
                }
            }
            $fp = fopen(public_path() . $upload_path, 'r');
            if(!headers_sent($filename, $linenum))
                header("Content-Type: text/json");
            fpassthru($fp);
            return;
        } else {
            $manufacturers = Manufacturer::all();
        }
        return response()->json($manufacturers);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $manufacturer = Manufacturer::find($id);

        return response()->json($manufacturer->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $manufacturer = new Manufacturer;
        $id =  $this->setData($manufacturer);

        return response()->json($manufacturer->toArray());
        //return response()->json(["id" => $id]);
    }

    /**
     * Update Method
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $manufacturer = Manufacturer::find($id);
        $this->setData($manufacturer);

        return response()->json($manufacturer->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Manufacturer::destroy($id);
        $this->cacheManufacturers();
        // return a success
        return response()->json("Destroy Successful");
    }

    private function cacheManufacturers() {
        $manufacturers = Manufacturer::select('id', 'name', 'pending_review', 'processor_models')->get();
        $manufacturers = $manufacturers->toArray();
        $manufacturers = json_encode($manufacturers);
        $upload_path = '/core/hardware_cache/';
        if(!is_dir(public_path() . $upload_path)) {
            mkdir(public_path() . $upload_path, 0755);
        }
        $fp = fopen(public_path() . $upload_path . 'manufacturers.json', 'w');
        fwrite($fp, $manufacturers);
        fclose($fp);
    }
}
