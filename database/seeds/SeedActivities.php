<?php

/**
 * Description of SeedActivities
 *
 * @author rsegura
 */

use Illuminate\Database\Seeder;
use App\Models\StandardModule\Activity;
use App\Models\StandardModule\Component;

class SeedActivities extends Seeder {

    public function run() {

        Activity::firstOrCreate(array(
            "name" => "Activity Logs",
            "description" => "Includes the ability to view all activity logs",
            "component_id" => Component::where('name', 'Foundation')->firstOrFail()->id,
            "state" => "activityLog.list",
            "icon" => "fa-book",
            "language_key" => "Foundation.StandardModule_ActivityLog"
        ));

        Activity::firstOrCreate(array(
            "name" => "Pricing",
            "description" => "Used to download pricing",
            "component_id" => Component::where('name', 'Admin')->firstOrFail()->id,
            "state" => "pricing.list",
            "icon" => "",
            "language_key" => "Pricing"
        ));

        Activity::firstOrCreate(array(
            "name" => "Revenue Report",
            "description" => "Used to download revenue report",
            "component_id" => Component::where('name', 'Admin')->firstOrFail()->id,
            "state" => "revenue.list",
            "icon" => "",
            "language_key" => "Revenue"
        ));

        Activity::firstOrCreate(array(
            "name" => "Settings",
            "description" => "Used for testing",
            "component_id" => Component::where('name', 'Foundation')->firstOrFail()->id,
            "state" => "settings.list",
            "icon" => "fa-cogs",
            "language_key" => "Settings"
        ));

        Activity::firstOrCreate(array(
            "name" => "Configuration Management",
            "description" => "Change configurations for an activity",
            "component_id" => Component::where('name', 'Admin')->firstOrFail()->id,
            "state" => "setting.list",
            "icon" => "fa-cogs",
            "language_key" => "Core.Configuration_ConfigurationManagement"
        ));

        Activity::firstOrCreate(array(
            "name" => "User Management",
            "description" => "Change user information, groups and activities",
            "component_id" => Component::where('name', 'Admin')->firstOrFail()->id,
            "state" => "user.list",
            "icon" => "fa-user",
            "language_key" => "Core.UserManagement_UserManagement"
        ));

        Activity::firstOrCreate(array(
            "name" => "Group Management",
            "description" => "Add, edit and remove groups. Change group permissions.",
            "component_id" => Component::where('name', 'Admin')->firstOrFail()->id,
            "state" => "group.list",
            "icon" => "fa-group",
            "language_key" => "Core.UserManagement_GroupManagement"
        ));
    }
}
