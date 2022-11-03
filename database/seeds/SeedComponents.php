<?php

/**
 * Description of SeedComponents
 *
 * @author mschenke
 */

use Illuminate\Database\Seeder;
use App\Models\StandardModule\Component;

class SeedComponents extends Seeder
{
    //put your code here

    public function run()
    {
        Component::firstOrCreate(array(
            "name" => "Admin",
            "description" => "Basic activities for user management",
            "language_key" => "Foundation.StandardModule_Admin"
        ));

        Component::firstOrCreate(array(
            "name" => "Projects",
            "description" => "The common component contains all activities that are "
                . "useful for both admin and public SmartStack apps.",
            "language_key"=>"Project"
        ));

        Component::firstOrCreate(array(
            "name" => "Software",
            "description" => "The common component contains all activities that are "
                . "useful for both admin and public SmartStack apps.",
            "language_key"=>"Software"
        ));

        Component::firstOrCreate(array(
            "name" => "Hardware",
            "description" => "The hardware component contains all activities that are "
                . "related to servers",
            "language_key" => "Hardware"
        ));

        Component::firstOrCreate(array(
            "name" => "Foundation",
            "description" => "The common component contains all activities that are "
                . "useful for both admin and public SmartStack apps.",
            "language_key" => "Foundation.StandardModule_Foundation"//"Common.ComponentLanguageKey_Common"
        ));

        Component::firstOrCreate(array(
            "name" => "Core",
            "description" => "The common component contains all activities that are "
                . "useful for both admin and public SmartStack apps.",
            "language_key"=>"Core.PublicComm_Core"//"Common.ComponentLanguageKey_Common"
        ));
    }
}