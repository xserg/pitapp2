<?php

/**
 * Seeds the activities and states for Project related things
 *
 * @author jdobrowolski
 */

use App\Models\Project\Region;
use Illuminate\Database\Seeder;
use App\Models\Project\Provider;
use Illuminate\Support\Facades\DB;

class SeedProviders extends Seeder {
    //put your code here

    public function run() {

        $aws_provider = Provider::firstOrCreate(array(
            "name" => "AWS"
        ));
        $azure_provider = Provider::firstOrCreate(array(
            "name" => "Azure"
        ));
        $google_provider = Provider::firstOrCreate(array(
            "name" => "Google"
        ));

        DB::beginTransaction();

        $regions = [
            ['name' => 'US East (N. Virginia)', 'provider_owner_id' => $aws_provider->id],
            ['name' => 'US East (Ohio)', 'provider_owner_id' => $aws_provider->id],
            ['name' => 'US West (N. California)', 'provider_owner_id' => $aws_provider->id],
            ['name' => 'US West (Oregon)', 'provider_owner_id' => $aws_provider->id],
            ['name' => 'West US 2', 'provider_owner_id' => $azure_provider->id],
            ['name' => 'West US', 'provider_owner_id' => $azure_provider->id],
            ['name' => 'West Central US', 'provider_owner_id' => $azure_provider->id],
            ['name' => 'South Central US', 'provider_owner_id' => $azure_provider->id],
            ['name' => 'North Central US', 'provider_owner_id' => $azure_provider->id],
            ['name' => 'East US 2', 'provider_owner_id' => $azure_provider->id],
            ['name' => 'East US', 'provider_owner_id' => $azure_provider->id],
            ['name' => 'Central US', 'provider_owner_id' => $azure_provider->id],
            ['name' => 'us-west2', 'provider_owner_id' => $google_provider->id],
            ['name' => 'us-central1', 'provider_owner_id' => $google_provider->id],
            ['name' => 'us-west3', 'provider_owner_id' => $google_provider->id],
            ['name' => 'us-west1', 'provider_owner_id' => $google_provider->id],
            ['name' => 'us-east4', 'provider_owner_id' => $google_provider->id],
            ['name' => 'us-east1', 'provider_owner_id' => $google_provider->id]
        ];

        foreach($regions as $region) {
            Region::firstOrCreate($region);
        }

        DB::commit();
    }
}
