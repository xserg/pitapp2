<?php namespace App\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Response;
use App\Models\UserManagement\Group;
use Illuminate\Support\Facades\Request;
use App\Models\StandardModule\Activity;
use App\Http\Controllers\Api\StandardModule\SmartController;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\AuthController as Auth;

class GroupController extends SmartController {

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
    
    private $messages;
    
    protected $activity = 'Group Management';
    
    protected $table = 'groups';
    
    private function setData($group) {
        
        //Validate the request server-side
        $request = Request::all();
        
        $validator = Validator::make(
                $request,
                array(
                    'description' => 'regex:/^[a-zA-Z0-9 ,.]*$/|max:255',
                )
        );
        
        if($validator->fails()) {
            $this->messages = $validator->messages();
            return -1;
        }
        
        if($group->name !== 'Admin') {
            if(Request::input('name'))          $group->name = Request::input('name');
        }

        if(!is_null(Request::input('description'))) {
            $group->description = Request::input('description');
        }
        
        $group->save();
        
        $activityAttach = (Request::input('activityAttach')) ? Request::input('activityAttach') : Array();
        
        if($group->name !== 'Admin') {
            $activityDetach = (Request::input('activityDetach')) ? Request::input('activityDetach') : Array();

            foreach($activityDetach as $detach) {
                $group->activities()->detach($detach);
            }
        }
        
        foreach($activityAttach as $attach) {
            $group->activities()->attach($attach);
        }
        
        $group->save();
    }
    
    public function index() {
        return Group::all()->toArray();
    }
    
    /**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
    protected function _store() {
        
        $group = new Group();
        
        //Group names must be unique
        $valid = Validator::make(
            Request::all(),
            array(
                'name' => 'required|unique:groups,name|regex:/^[a-zA-Z0-9 ,&.\'-]*$/|max:255'
        ));
        
        if($valid->fails()) {
            $this->messages = $valid->messages();
            return response()->json($this->messages, 500);
        }

        $this->setData($group);
        
        if($this->messages) {
            return response()->json($this->messages, 500);
        }

        response()->json($group->toArray());
    }
    
    /**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _show($id) {
        $group = Group::find($id);
        
        return response()->json($group->toArray());
    }
    
    /**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _update($id) {
        
        $group = Group::find($id);
        
        //Check that any new names are unique
        if(Request::input('name') !== $group->name) {
            $valid = Validator::make(
            Request::all(),
            array(
                'name' => 'required|unique:groups,name|regex:/^[a-zA-Z0-9 ,.\'-]*$/|max:255'
            ));
            
            if($valid->fails()) {
                $this->messages = $valid->messages();
                return response()->json($this->messages, 500);
            }
        }

        if($this->setData($group) < 0) {
            return response()->json($this->messages, 500);
        }

        response()->json($group->toArray());
    }
    
    /**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _destroy($id) {
        
        $group = Group::findOrFail($id);
        
        if($group->name == 'Admin') {
            return response()->json("You cannot delete the Admin group", 500);
        }
        
        foreach($group->users()->withTrashed()->get() as $user) {
            $group->users()->withTrashed()->detach($user->id);
        }
        
        foreach($group->activities()->get() as $activity) {
            $group->activities()->detach($activity->id);
        }

        $group->delete();

        return response()->json("User deleted", 200);
    }

}
