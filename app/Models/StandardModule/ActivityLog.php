<?php namespace App\Models\StandardModule;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Facades\DB;
use App\Models\StandardModule\SmartModel;
use App\Models\StandardModule\Component;
use App\Models\StandardModule\ActivityLogType;
use App\Models\StandardModule\User;

class ActivityLog extends SmartModel {
    
    use SoftDeletes;
    
    protected $destroyOnWrite = false;
    
    protected $index = [
        ['activity_logs.model_id', '='],
        ['activity_logs.message', 'LIKE'],
        ['users.lastName', 'LIKE'],
        ['users.firstName', 'LIKE'],
        ['activity_logs.model_name', 'LIKE'],
        ['activity_logs.component_id', '='],
        ['activity_logs.activityLogType_id', '=']
    ];
    
    protected $joins = [
        [
            'users',
            'id',
            '=',
            'activity_logs',
            'user_id'
        ],
        [
            'components',
            'id',
            '=',
            'activity_logs',
            'component_id'
        ],
        [
            'activity_log_types',
            'id',
            '=',
            'activity_logs',
            'activityLogType_id'
        ]
    ];
    
    protected $viewColumns = [
        'activity_logs.id as "id"',
        'components.name as "name"',
        'users.email as "user_email"',
        'activity_logs.model_name as "entity"',
        'activity_logs.model_id as "entity_id"',
        'activity_log_types.name as "type"',
        'activity_log_types.id as "activityLogType_id"',
        'activity_logs.message as "message"',
        'activity_logs.created_at as "created_at"'
    ];
    
    protected $orderBy = [
        ['activity_logs.id', 'DESC']
    ];
    
    protected function prepare($query) {
        $query = (array) $query;
        
        if(isset($query['component_id'])) {
            $query['activity_logs.component_id'] = $query['component_id'];
            unset($query['component_id']);
        }
        if(isset($query['user'])) {
            $user = explode(" ", $query['user']);
            $userParams = array();
            if(count($user) > 1) {
                if(!strpos($user[0], ",")) {
                    $userParams['users.lastName'] = '"%' . $user[1] . '%"';
                    $userParams['users.firstName'] = '"%' . $user[0] . '%"';
                } else {
                    $userParams['users.lastName'] = '"%' . substr($user[0], 0, strlen($user[0]) - 1) . '%"';
                    $userParams['users.firstName'] = '"%' . $user[1] . '%"';
                }
            } else {
                $userParams['users.lastName'] = '"%' . $user[0] . '%"';
            }
            if ($query['user'] !== "")
                $query = collect($query)->merge(collect($userParams))->toArray();
            unset($query['user']);
        }
        if(isset($query['activityLogType_id'])) {
            $query['activity_logs.activityLogType_id'] = $query['activityLogType_id'];
            unset($query['activityLogType_id']);
        }
        if(isset($query['model_name'])) {
            $query['activity_logs.model_name'] = '"%' . $query['model_name'] . '%"';
            unset($query['model_name']);
        }
        if(isset($query['model_id'])) {
            $query['activity_logs.model_id'] = $query['model_id'];
            unset($query['model_id']);
        }
        if(isset($query['message'])) {
            $query['activity_logs.message'] = '"%' . $query['message'] . '%"';
            unset($query['message']);
        }
        return $query;
    }
    
    // Override the base toJson method to return the correct values for elasticsearch
    public function toJson($options = 0) {
        
        $entity = DB::table('activity_logs')
            ->leftJoin('components', 'components.id', '=', 'activity_logs.component_id')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->leftJoin('activity_log_types', 'activity_log_types.id', '=', 'activity_logs.activityLogType_id')
            ->select('activity_logs.id as id',
                    'components.name as name',
                    'users.email as user_email', 
                    'activity_logs.model_name as entity',
                    'activity_logs.model_id as entity_id',
                    'activity_log_types.name as type',
                    'activity_log_types.id as activityLogType_id',
                    'activity_logs.message as message',
                    'activity_logs.created_at as created_at')
            ->where('activity_logs.id', '=', $this->id)
            ->get();
        
        return json_encode($entity[0]);
    }
    
    public function component() {
        return $this->belongsTo(Component::class);
    }
    
    public function type() {
        return $this->belongsTo(ActivityLogType::class, 'activityLogType_id');
    }
    
    public function user() {
        return $this->belongsTo(User::class);
    }
    
    public function reload() {
        return ActivityLog::find($this->id);
    }
    
    public function logName() {
        return "Activity log " . $this->id;
    }
}
