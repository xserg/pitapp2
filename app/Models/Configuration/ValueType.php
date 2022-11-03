<?php

namespace App\Models\Configuration;

use App\Models\StandardModule\SmartModel;
use App\Models\StandardModule\Component;
use Illuminate\Database\Eloquent\SoftDeletes;

class ValueType extends SmartModel {

	use SoftDeletes;
    
    protected $fillable = ['label'];
    
    public function component() {
        return Component::where('name', '=', 'Admin')->first()->id;
    }
    
    public function reload() {
        return ValueType::where('label', '=', $this->label)->first();
    }
    
    public function logName() {
        return 'ValueType ' . $this->label;
    }
}
