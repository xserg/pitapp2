<?php

/**
 * Seeds the activities and states for Hardware related things
 *
 * @author cgarnett
 */

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\StandardModule\Component;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\Group;

class SeedHardware extends Seeder {
    //put your code here

    public function run() {

        $component = Component::where("name", "Hardware")->firstOrFail();

        /**
         * Add Region Components
         */
        $manufacturerActivity = Activity::firstOrCreate(array(
            "name" => "Manufacturer Management",
            "description" => "Used for manufacturer management.",
            "component_id" => $component->id,
            "state" => "manufacturer.list",
            "icon" => "fa-globe",
            "sort" => 4,
            "language_key" => "Manufacturers"
        ));

        $serverActivity = Activity::firstOrCreate(array(
            "name" => "Server Management",
            "description" => "Used for server management.",
            "component_id" => $component->id,
            "state" => "server.list",
            "icon" => "fa-globe",
            "sort" => 3,
            "language_key" => "Servers"
        ));

        $processorActivity = Activity::firstOrCreate(array(
            "name" => "Processor Management",
            "description" => "Used for processor management.",
            "component_id" => $component->id,
            "state" => "processor.list",
            "icon" => "fa-globe",
            "sort" => 2,
            "language_key" => "Processors"
        ));

        $serverConfigActivity = Activity::firstOrCreate(array(
            "name" => "Hardware Configuration",
            "description" => "Used for hardware configuration management.",
            "component_id" => $component->id,
            "state" => "server-config.list",
            "icon" => "fa-globe",
            "sort" => 1,
            "language_key" => "Hardware Configuration"
        ));

        $adminGroup = Group::where('name', '=', 'Admin')->firstOrFail();
        $adminGroup->activities()->attach($manufacturerActivity->id);
        $adminGroup->activities()->attach($serverActivity->id);
        $adminGroup->activities()->attach($processorActivity->id);
        $adminGroup->activities()->attach($serverConfigActivity->id);
    }
}
