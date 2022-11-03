<?php

/**
 * Seeds the activities and states for Project related things
 *
 * @author jdobrowolski
 */

use Illuminate\Database\Seeder;
use App\Models\StandardModule\Component;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\Group;
use App\Models\Software\SoftwareType;

class SeedSoftwareCore extends Seeder {
    //put your code here

    public function run() {

        $component = Component::where("name", "Software")->firstOrFail();

        /**
         * Add Region Components
         */
        $activity = Activity::firstOrCreate(array(
            "name" => "Software Management",
            "description" => "Used for software management.",
            "component_id" => $component->id,
            "state" => "software.list",
            "icon" => "fa-globe",
            "language_key" => "Software Management"
        ));

        $activityCost = Activity::firstOrCreate(array(
            "name" => "SoftwareCost Management",
            "description" => "Used for software management.",
            "component_id" => $component->id,
            "state" => "software-cost.list",
            "icon" => "fa-globe",
            "language_key" => "SoftwareCost Management"
        ));

        $activityType = Activity::firstOrCreate(array(
            "name" => "Software Type Management",
            "description" => "Used for software type management.",
            "component_id" => $component->id,
            "state" => "software-type.list",
            "icon" => "fa-globe",
            "language_key" => "SoftwareType Management"
        ));

        $activityFeature = Activity::firstOrCreate(array(
            "name" => "Feature Management",
            "description" => "Used for feature management.",
            "component_id" => $component->id,
            "state" => "feature.list",
            "icon" => "fa-globe",
            "language_key" => "Feature Management"
        ));

        //populate Software Types

        SoftwareType::updateOrCreate(array(
                        "name" => 'Operating System',
                        'id' => 1
                    ));
        SoftwareType::updateOrCreate(array(
                        "name" => 'Hypervisor',
                        'id' => 2
                    ));
        SoftwareType::updateOrCreate(array(
                        "name" => 'Middleware',
                        'id' => 3
                    ));
        SoftwareType::updateOrCreate(array(
                        "name" => 'Database',
                        'id' => 4
                    ));

        $adminGroup = Group::where('name', '=', 'Admin')->firstOrFail();

        $adminGroup->activities()->attach($activity->id);
        $adminGroup->activities()->attach($activityCost->id);
        $adminGroup->activities()->attach($activityType->id);
        $adminGroup->activities()->attach($activityFeature->id);
    }
}
