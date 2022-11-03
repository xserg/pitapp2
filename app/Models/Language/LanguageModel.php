<?php namespace App\Models\Language;

use Illuminate\Database\Eloquent\Model;
use App\Models\StandardModule\SmartModel;
use App\Models\StandardModule\Component;

abstract class LanguageModel extends SmartModel {
    
    public function component() {
        return Component::where('name', '=', 'Foundation')->first()->id;
    }
}