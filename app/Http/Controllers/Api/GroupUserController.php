<?php namespace App\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Controller;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;

class GroupUserController extends Controller {

	/*
	|--------------------------------------------------------------------------
	| Welcome Controller
	|--------------------------------------------------------------------------
	|
	| This controller renders the "marketing page" for the application and
	| is configured to only allow guests. Like most of the other sample
	| controllers, you are free to modify or remove it as you desire.
	|
	*/

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index($group) {
        $user = Group::find($group)->users;
        
		return response()->json($user->toArray());
	}
    
    /**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
    public function store($group) {
        return response()->json("This method is undefined");
    }
    
    /**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    public function show($group, $id) {
        $user = Group::find($group)->users()->find($id);
        
        return response()->json($user->toArray());
    }
    
    /**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    public function update($id) {
        return response()->json("This method is undefined");
    }
    
    /**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    public function destory($id) {
        return response()->json("This method is undefined");
    }

}
