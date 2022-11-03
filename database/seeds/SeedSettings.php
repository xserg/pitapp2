<?php

/**
 * Description of SeedSettings
 *
 * @author hnguyen
 */

use Illuminate\Database\Seeder;

use App\Models\Configuration\Setting;
use App\Models\Configuration\ValueType;
use App\Models\StandardModule\Activity;
use App\Models\StandardModule\Component;

class SeedSettings extends Seeder {
    
    public function run() {
        // Do not modify these contents for $component
        $component = Component::where('name', 'Admin')->first()->id;
        
        $cmActivity = Activity::where('component_id','=',$component)
                ->where('name','=','User Management')->first()->id;
        
        $booleanType = ValueType::where('label','=','boolean')->first()->id;
        
        $unsignedIntegerType = ValueType::where('label','=','unsigned integer')->first()->id;
        
        Setting::firstOrCreate(array(
            "key" => "Core.UserManagement_PasswordMinLength",
            "component_id" => $component,
            "activity_id" => $cmActivity,
            "value_type_id" => $unsignedIntegerType,
            "value" => 8
        ));
        
        Setting::firstOrCreate(array(
            "key" => "Core.UserManagement_PasswordContainsUppercase",
            "component_id" => $component,
            "activity_id" => $cmActivity,
            "value_type_id" => $booleanType,
            "value" => 1
        ));
        
        Setting::firstOrCreate(array(
            "key" => "Core.UserManagement_PasswordContainsLowercase",
            "component_id" => $component,
            "activity_id" => $cmActivity,
            "value_type_id" => $booleanType,
            "value" => 1
        ));
        
        Setting::firstOrCreate(array(
            "key" => "Core.UserManagement_PasswordContainsNumber",
            "component_id" => $component,
            "activity_id" => $cmActivity,
            "value_type_id" => $booleanType,
            "value" => 1
        ));
        
        Setting::firstOrCreate(array(
            "key" => "Core.UserManagement_PasswordContainsSpecialCharacter",
            "component_id" => $component,
            "activity_id" => $cmActivity,
            "value_type_id" => $booleanType,
            "value" => 1
        ));
        
        Setting::firstOrCreate(array(
            "key" => "Core.UserManagement_PasswordExpires",
            "component_id" => $component,
            "activity_id" => $cmActivity,
            "value_type_id" => $unsignedIntegerType,
            "value" => 0
        ));
        $activity = Activity::firstOrCreate(array(
            "name" => "ElasticSearch",
            "description" => "Choose to use/display warnings for ElasticSearch and set the server path",
            "component_id" => $component,
            "state" => "elastic.list",
            "icon" => "fa-search",
            "language_key" => "Foundation.StandardModule_ElasticSearch"
        ));
        $stringType = ValueType::where('label', '=', 'string')->first()->id;
        
        Setting::firstOrCreate(array(
            "key" => "Foundation.StandardModule_ElasticServerPath",
            "component_id" => $component,
            "activity_id" => $activity->id,
            "value_type_id" => $stringType,
            "value" => env('ELASTIC_HOST', "")
        ));
        
        Setting::firstOrCreate(array(
            "key" => "Foundation.StandardModule_ElasticPort",
            "component_id" => $component,
            "activity_id" => $activity->id,
            "value_type_id" => $unsignedIntegerType,
            "value" => env('ELASTIC_PORT', '9200')
        ));
        
        Setting::firstOrCreate(array(
            "key" => "Foundation.StandardModule_UseElastic",
            "component_id" => $component,
            "activity_id" => $activity->id,
            "value_type_id" => $booleanType,
            "value" => 1
        ));
        
    }
}