<?php 

namespace App\Models\Configuration;

use App\Models\StandardModule\SmartModel;
use App\Models\StandardModule\Component;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\StandardModule\Activity;

class Setting extends SmartModel {

    use SoftDeletes;
    
    protected $fillable = ['key', 'value', 'component_id', 'activity_id', 'value_type_id'];
    // This is just for activity log, no need to compare to $this->component_id
    public function component() {
        return Component::where('name', '=', 'Admin')->first()->id;
    }
    
    public function activity() {
        return $this->belongsTo(Activity::class, 'id');
    }
    
    public function type() {
        return $this->belongsTo(ValueType::class, 'id');
    }
    
    public function reload() {
        return Setting::where('key', '=', $this->key)->where('component_id', '=', $this->component_id)->first();
    }
    
    public function logName() {
        return 'Setting ' . $this->key . ' ' . $this->component_id;
    }

}
