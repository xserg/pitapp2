<?php namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as CoreController;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\User;

class ActivityUserController extends CoreController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index($activity) {
        $resource = Activity::find($activity)->users;
                
        return response()->json($resource->toArray());
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store() {
        
        return response()->json("Method is not defined");
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($activity, $id) {
		$user = Activity::find($activity)->users()->find($id);
        
        return response()->json($user->toArray());
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($activity, $id) {
		return response()->json("Method is not defined");
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($activity, $id) {
		return response()->json("Method is not defined");
	}

}
