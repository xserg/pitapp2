<?php namespace App\Models\StandardModule;

use Illuminate\Database\Eloquent\Model;

use App\Models\StandardModule\SmartModel;
use App\Models\StandardModule\Component;

abstract class StandardModel extends SmartModel {
    
    public function component() {
        return Component::where('name', '=', 'Foundation')->first()->id;
    }
}