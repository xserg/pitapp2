<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Request;
use App\Models\Project\Provider;

class ProviderController extends Controller {

    protected $model = "App\Models\Project\Provider";

    protected $activity = 'Provider Management';
    protected $table = 'providers';

    // Private Method to set the data
    private function setData(&$provider) {
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
        $provider->name = Request::input('name');
        

        $provider->save();

        return $provider;
    }

    protected function index() {
        $providers = Provider::all();
        foreach($providers as $provider)
        {
            $regions = $provider->regions;
            foreach($regions as $region)
            {
                $region->currencies;
            }
        }

        return response()->json($providers);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $provider = Provider::find($id);
        $regions = $provider->regions;
        $amazonStorages = $provider->amazonStorages;
        foreach($regions as $region)
        {
            $region->currencies;
        }
        return response()->json($provider->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $provider = new Provider;
        if(!$this->setData($provider)) {
            return response()->json($this->messages, 500);
        }

        
        $provider->regions->currencies;
        return response()->json($provider->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $provider = Provider::find($id);
        if(!$this->setData($provider)) {
            return response()->json($this->messages, 500);
        }
        $provider->regions->currencies;

        return response()->json($provider->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Provider::destroy($id);

        // return a success
        return response()->json("Delete Successful");
    }
}
