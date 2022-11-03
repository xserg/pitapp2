<?php namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use App\Models\Hardware\InterconnectChassisModel;
use Illuminate\Support\Facades\Request;
use App\Models\Hardware\Manufacturer;

class InterconnectChassisModelController extends Controller {

    protected $model = "App\Models\Hardware\InterconnectChassisModel";
    protected $activity = 'Interconnect/Chassis Model Management';
    protected $table = 'hardware_interconnect_chassis_model';


    public function index($type) {

       // $models = InterconnectChassisModel::where('type', $type)->get();
        $models = InterconnectChassisModel::all();
        return response()->json($models);
    }


}
