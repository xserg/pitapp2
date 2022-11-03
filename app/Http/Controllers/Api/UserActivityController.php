<?php namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Request;
use App\Http\Controllers\Controller as CoreController;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\User;
use App\Models\UserManagement\Profile;
use Illuminate\Support\Facades\Auth;

class UserActivityController extends CoreController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index($user) {
        $resource = User::withTrashed()->find($user)->activities;
        return response()->json($resource->toArray());
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store($user) {
        return response()->json("Method is not defined");
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($user, $id) {
		$activity = User::find($user)->activities()->find($id);
        
        return response()->json($activity->toArray());
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($user, $id) {
		return response()->json("Method is not defined");
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($user, $id) {
		return response()->json("Method is not defined");
	}
    
        public static function userActivities($id) {
            $activities = User::find($id)->allActivities();
            
            //Add the language key for the component to the activity for display purposes
            //and to speed up page load times
            foreach($activities as $activity) {
                $activity->component_name = $activity->activityComponent->language_key;
            }

            return $activities;
        }
        
        public static function checkActivity($id) {
            $activity = Request::input('activity');
            $activities = User::find($id)->allActivities();
            
            foreach($activities as $a) {
                if($a->name === $activity) {
                    return response()->json(true, 200);
                }
            }
            return response()->json(false, 200);
        }
        
    public static function allActivities($id) {
        $activities = Auth::user()->user->allActivities();
        
        //Add the language key for the component to the activity for display purposes
        //and to speed up page load times
        foreach($activities as $activity) {
            $activity->component_name = $activity->activityComponent->language_key;
            $activity->component_sort = $activity->activityComponent->sort;
        }
        
        return $activities;
    }

}
