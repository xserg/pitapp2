<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Region;

class RegionController extends Controller {

    protected $model = "App\Models\Project\Region";

    protected $activity = 'Region Management';
    protected $table = 'regions';

    // Private Method to set the data
    private function setData(&$region) {
        $validator = Validator::make(
            Request::all(),
            array(
                'name' => 'required|string|max:50'               
        ));

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return false;
        }

        // Set all the data here (fill in all the fields)
        $region->name = Request::input('name');
        

        $region->save();

        return $region;
    }

    protected function index() {
        $regions = Region::all();

        return response()->json($regions);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $region = Region::find($id);
        $region->currencies;
        return response()->json($region->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $region = new Region;
        if(!$this->setData($region)) {
            return response()->json($this->messages, 500);
        }

        
        $region->currencies;
        return response()->json($region->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $region = Region::find($id);
        if(!$this->setData($region)) {
            return response()->json($this->messages, 500);
        }
        $region->currencies;

        return response()->json($region->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Region::destroy($id);

        // return a success
        return response()->json("Delete Successful");
    }
}
