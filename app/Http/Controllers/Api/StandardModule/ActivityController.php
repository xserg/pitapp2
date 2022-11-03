<?php namespace App\Http\Controllers\Api\StandardModule;

use App\Http\Controllers\Controller as CoreController;
use App\Models\StandardModule\Activity;
use Illuminate\Support\Facades\Request;

class ActivityController extends CoreController {


    /**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index() {
        $resource = Activity::all();
                
        return response()->json($resource->toArray());
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store() {
        $activity = new Activity();
        
        if (Request::input('name')) {
            $activity->name = Request::input('name');
        }
        if (Request::input('description')) {
            $activity->description = Request::input('description');
        }
        
        $activity->save();
        
        return Response::json($activity->name . ' saved', 200);
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id) {
		$activity = Activity::find($id);
        
        return response()->json($activity->toArray());
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id) {
		$activity = Activity::find($id);
        
        if (Request::input('name')) {
            $activity->name = Request::input('name');
        }
        if (Request::input('description')) {
            $activity->description = Request::input('description');
        }
        
        $activity->save();
        
        return Response::json($activity->name . ' saved', 200);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id) {
		$activity = Activity::findOrFail($id);

        $activity->delete();

        return Response::json("Activity deleted", 200);
	}

}
