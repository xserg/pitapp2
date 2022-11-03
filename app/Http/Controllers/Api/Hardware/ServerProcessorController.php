<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Hardware\ServerProcessor;

/**
 * Description of ServerProcessorController
 *
 * @author rvalenziano
 */
class ServerProcessorController extends Controller{
    protected $model = ServerProcessor::class;

    protected $activity = 'ServerProcessor Management';
    protected $table = 'server_processors';

    protected function index() {
          $mps = ServerProcessor::all();
         return response()->json($mps);
    }

    protected function show($id){
        $mp = ServerProcessor::find($id);

        return response()->json($mp->toArray());
    }

    protected function store(){
       // $this->validateData();

        $mp = new ServerProcessor;
        $this->setData($mp);

        return response()->json($mp->toArray());
        //return response()->json("Create Successful");
    }

    protected function update($id) {
       // $this->validateData();

        $mp = ServerProcessor::find($id);
        $this->setData($mp);

        return response()->json($mp->toArray());
        //return response()->json("Update Successful");
    }

    protected function destroy($id){
        ServerProcessor::destroy($id);

        return response()->json("Destroy Successful");
    }

    private function setData(&$mp){
        $mp->processor_id = Request::input('processor_id');
        $mp->server_id = Request::input('server_id');

        $mp->save();
    }

}
