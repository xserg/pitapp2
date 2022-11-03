<?php

/**
 * Seeds the activities and states for Project related things
 *
 * @author jdobrowolski
 */

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\StandardModule\Component;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\Group;

class SeedProjectCore extends Seeder {
    //put your code here

    public function run() {
        $component = Component::where("name", "Projects")->firstOrFail();

        /**
         * Add Projects Components
         */
        $activity = Activity::firstOrCreate(array(
            "name" => "Project Management",
            "description" => "Used for project management.",
            "component_id" => $component->id,
            "state" => "project.list",
            "icon" => "fa-globe",
            "language_key" => "Project Management"
        ));

        $environment = Activity::firstOrCreate(array(
            "name" => "Environment Management",
            "description" => "Used for environment management.",
            "component_id" => $component->id,
            "state" => "environment.list",
            "icon" => "fa-globe",
            "language_key" => "Environment"
        ));

        $environmentType = Activity::firstOrCreate(array(
            "name" => "Environment Type Management",
            "description" => "Used for environment type management.",
            "component_id" => $component->id,
            "state" => "environmentType.list",
            "icon" => "fa-globe",
            "language_key" => "Environment Type"
        ));

        $component = Component::where('name', '=', 'Admin')->first();

        $faq = Activity::firstOrCreate(array(
            "name" => "FAQ Management",
            "description" => "Used for FAQ management.",
            "component_id" => $component->id,
            "state" => "faq.list",
            "icon" => "fa-globe",
            "language_key" => "FAQs"
        ));

        $company = Activity::firstOrCreate(array(
            "name" => "Company Management",
            "description" => "Used for Company management.",
            "component_id" => $component->id,
            "state" => "company.list",
            "icon" => "fa-globe",
            "language_key" => "Companies"
        ));

        $adminGroup = Group::where('name', '=', 'Admin')->firstOrFail();
        $adminGroup->activities()->attach($activity->id);
        $adminGroup->activities()->attach($environment->id);
        $adminGroup->activities()->attach($environmentType->id);
        $adminGroup->activities()->attach($faq->id);
        $adminGroup->activities()->attach($company->id);
    }
}
