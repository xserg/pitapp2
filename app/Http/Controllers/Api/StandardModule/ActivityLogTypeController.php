<?php namespace App\Http\Controllers\Api\StandardModule;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as CoreController;
use App\Models\StandardModule\ActivityLogType;

class ActivityLogTypeController extends CoreController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index() {
        $resource = ActivityLogType::all();
                
        return response()->json($resource->toArray());
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id) {
		$activity = ActivityLogType::find($id);
        
        return response()->json($activity->toArray());
	}

}
