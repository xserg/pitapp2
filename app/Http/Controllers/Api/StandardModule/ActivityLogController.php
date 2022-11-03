<?php namespace App\Http\Controllers\Api\StandardModule;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Request;
use App\Models\StandardModule\ActivityLog;

class ActivityLogController extends SmartController {

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
    
    protected $activity = 'Activity Logs';
    
    protected $table = 'activity_logs';
    
    protected $cached = true;
    
    protected $model = "App\Models\StandardModule\ActivityLog";
    
    private function setData($group) {
        //Current read only on the activity logs
    }
    
    /**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
    protected function _store() {
        //Currently read only access
    }
    
    /**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _show($id) {
        $log = ActivityLog::find($id);
        
        if($log->user_id) {
            $user = $log->user;
            $log->username = $user->firstName . " " . $user->lastName . " " . $user->email;
        } else {
            $log->username = "SYSTEM";
        }
        
        if($log->activityLogType_id) {
            $log->type = $log->type()->first()->name;
        }
        
        if($log->component_id) {
            $log->component = $log->component()->first()->name;
        }
        
        if($log->model_name !== null && $log->model_id !== null) {
//            $log->entity = $log->model_name::find($log->model_id)->logName();
            $entity = call_user_func($log->model_name . '::find', $log->model_id);
            if($entity !== null) {
                $log->entity = $entity->logName();
            }
        }
        
        return response()->json($log, 200);
    }
    
    /**
     * Returns a list of activity_logs after filtering
     * on multiple parameters
     */
    public function get() {
        
        if(Request::input('model_name') !== null) {
            $where = $this->buildLike('model_name', json_decode(Request::input('model_name')), Request::input('entities') !== null ? json_decode(Request::input('entities')) : null);
        }

        if(Request::input('user_id') !== null)
        {
            $where .= " and detailed LIKE '%\"id\":". Request::input('user_id') . "%'";
        }

        $builder = ActivityLog::whereRaw($where)->orderBy('id', 'desc');

        if(Request::input('limit') !== null) {
            $builder = $builder->limit(Request::input('limit'));
        }

        $logs = $builder->get();
        
//        print_r(ActivityLog::whereRaw($where)->toSql());
//        die();
        
        //Add in columns for type
        foreach($logs as &$log) {
            $log->icon  = $log->type['icon-key'];
            $log->color = $log->type['color'];
        }
//        print_r($logs);
        return response()->json($logs, 200);
        
    }
    
    private function buildLike($column, $matches, $entities) {
        
//        var_dump($matches);
        
        $where = "(";
        
        foreach($matches as $match) {
            $entity = strtolower($match);
            if(isset($entities->$entity)) {
                $where .= $column . " like '%" . $match . "' and model_id = '" . $entities->$entity . "' or ";
            } else {
                $where .= $column . " like '%" . $match . "' or ";
            }
        }
        
        //Strip the last or
        $where = substr($where, 0, strrpos($where, ' or'));
        $where .= ")";
        return $where;
    }
    
    /**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _update($id) {
        //Currently read only access
    }
    
    /**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _destroy($id) {
        //Currently read only access
    }

}
