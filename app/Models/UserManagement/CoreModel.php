<?php namespace App\Models\UserManagement;

use Illuminate\Database\Eloquent\Model;
use App\Models\StandardModule\SmartModel;
use App\Models\StandardModule\Component;

abstract class CoreModel extends SmartModel {
    
    public function component() {
        return Component::where('name', '=', 'Core')->first()->id;
    }

}