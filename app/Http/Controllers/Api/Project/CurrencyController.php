<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Currency;

class CurrencyController extends Controller {

    protected $model = "App\Models\Project\Currency";

    protected $activity = 'Currency Management';
    protected $table = 'currencys';

    // Private Method to set the data
    private function setData(&$currency) {
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
        $currency->name = Request::input('name');
        

        $currency->save();

        return $currency;
    }

    protected function index() {
        $currencys = Currency::all();

        return response()->json($currencys);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $currency = Currency::find($id);
        
        return response()->json($currency->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $currency = new Currency;
        if(!$this->setData($currency)) {
            return response()->json($this->messages, 500);
        }

        
        
        return response()->json($currency->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $currency = Currency::find($id);
        if(!$this->setData($currency)) {
            return response()->json($this->messages, 500);
        }
        

        return response()->json($currency->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Currency::destroy($id);

        // return a success
        return response()->json("Delete Successful");
    }
}
