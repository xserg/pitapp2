<?php
namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Hardware\ServerProcessor;

class ServerConfigurationProcessorController extends Controller {

    protected $model = "App\Models\Hardware\ServerConfigurationProcessor";

    protected $activity = 'ServerConfigurationProcessor Management';
    protected $table = 'server_configuration_processors';

    protected function index() {
          $sps = ServerConfigurationProcessor::all();
         return response()->json($sps);
    }

    protected function show($id){
        $sp = ServerConfigurationProcessor::find($id);

        return response()->json($sp->toArray());
    }

    protected function store(){
        $this->validateData();

        $sp = new ServerConfigurationProcessor;
        $this->setData($sp);

        return response()->json("Create Successful");
    }

    protected function update($id) {
        $this->validateData();

        $sp = ServerConfigurationProcessor::find($id);
        $this->setData($sp);

        return response()->json("Update Successful");
    }

    protected function destroy($id){
        ServerConfigurationProcessor::destroy($id);

        return response()->json("Destroy Successful");
    }

    private function setData($sp){
        $sp->processor_id = Request::input('processor_id');
        $sp->server_configuration_id = Request::input('server_configuration_id');

        $sp->save();
    }

     private function validateData(){
         $validator = Validator::make(
            Request::all(),
            array(
                'server_configuration_id' => 'required |  integer | exists:server_configurations,id',
            'processor_id' => 'required | integer | exists:processors,id'
        ));

        if ($validator->fails()) {
            abort(400, json_encode($validator->messages()));
        }
    }
}
