<?php namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as CoreController;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\Group;

class GroupActivityController extends CoreController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index($group) {
        $resource = Group::find($group)->activities;
        return response()->json($resource->toArray());
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store($group) {
        return response()->json("Method is not defined");
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($group, $id) {
		$activity = Group::find($group)->activities()->find($id);
        
        return response()->json($activity->toArray());
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($group, $id) {
		return response()->json("Method is not defined");
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($group, $id) {
		return response()->json("Method is not defined");
	}

}
