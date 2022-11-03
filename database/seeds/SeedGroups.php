<?php

/**
 * Description of SeedGroups
 *
 * @author rsegura
 */

use Illuminate\Database\Seeder;
use App\Models\UserManagement\Group;
use App\Models\StandardModule\Activity;

class SeedGroups extends Seeder {

    public function run() {

        $admin = Group::firstOrCreate(array(
            'name' => 'Admin',
            'description' => 'For those cool enough to have admin permissions'
        ));

        Group::firstOrCreate(array(
            'name' => 'Moderator',
            'description' => 'For those cool enough to have moderator permissions'
        ));

        $admin->activities()->sync(Activity::select('id')->get()->pluck('id'));

        // Look up admin activity and assign to group
        $activity = Activity::where("name", "=", "Configuration Management")->firstOrFail();
        $admin->activities()->attach($activity->id);
    }
}
