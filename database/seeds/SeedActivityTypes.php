<?php

/**
 * Description of SeedActivities
 *
 * @author rsegura
 */

use App\Models\StandardModule\Component;
use Illuminate\Database\Seeder;
use App\Models\StandardModule\ActivityLogType;

class SeedActivityTypes extends Seeder {

    public function run() {

        Eloquent::unguard();

        $component = Component::where('name', '=', 'Foundation')->first();

        ActivityLogType::create(array(
            'component_id'      =>      $component->id,
            'key'               =>      'record-saved',
            'name'              =>      'Record Saved',
            'color'             =>      '#89d656',
            'icon-key'          =>      'save'
        ));

        ActivityLogType::create(array(
            'component_id'      =>      $component->id,
            'key'               =>      'record-deleted',
            'name'              =>      'Record Deleted',
            'color'             =>      '#fc8675',
            'icon-key'          =>      'ban'
        ));

        ActivityLogType::create(array(
            'component_id'      =>      $component->id,
            'key'               =>      'record-undeleted',
            'name'              =>      'Record Undeleted',
            'color'             =>      '#ecb851',
            'icon-key'          =>      'circle-o'
        ));
    }
}
