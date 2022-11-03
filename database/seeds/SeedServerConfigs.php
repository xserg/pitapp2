<?php

/**
 * Seeds the activities and states for Project related things
 *
 * @author jdobrowolski
 */

use Illuminate\Database\Seeder;

class SeedServerConfigs extends Seeder {

    public function run() {
        DB::unprepared(file_get_contents(__DIR__."/../data/pitapp-data.sql"));
        // Clear out old data
        DB::statement("delete from environment_analyses");
        DB::statement("delete from environments");
        DB::statement("delete from server_configurations");
        DB::statement("delete from projects");
    }
}
