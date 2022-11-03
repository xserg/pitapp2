<?php

/**
 * Description of SeedActivities
 *
 * @author rsegura
 */

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\StandardModule\Component;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\Group;

class SeedLanguageCommon extends Seeder {
    //put your code here
    
    public function run() {
        
        $activity = Activity::firstOrCreate(array(
            "name" => "Language Management",
            "description" => "Add, edit, view Language keys",
            "component_id" => Component::where('name', '=', 'Foundation')->first()->id,
            "state" => "language.edit",
            "icon" => "fa-language",//"fa-language_key"
            "language_key" => "Foundation.Language_LanguageManagement"//"Common.ActivityLanguageKey_LanguageManagement"
        ));
        
        // Note swheeler 2015-09-14. Commented out until this can be moved to Admin stack only because it breaks Public stack.
        $adminGroup = Group::where('name', '=', 'Admin')->firstOrFail();
        $adminGroup->activities()->attach($activity->id);
    }
}
