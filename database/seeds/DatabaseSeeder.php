<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(StandardSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(SeedEmailSettings::class);
        $this->call(LanguageSeeder::class);
        $this->call(SeedProjectCore::class);
        $this->call(SeedSoftwareCore::class);
        $this->call(SeedHardware::class);
        $this->call(SeedProviders::class);

        // Google
        $this->call(SeedGoogleServers::class);
        $this->call(SeedGoogleStorages::class);

        // Azure
        $this->call(SeedAzureAds::class);
        $this->call(SeedAzureServers::class);
        $this->call(SeedAzureStorages::class);

        // AWS
        $this->call(SeedAmazonServers::class);
        $this->call(SeedAmazonServersRDS::class);
        $this->call(SeedAmazonStorages::class);

        // IBM
        $this->call(SeedIBMPVS_Storage::class);
        $this->call(SeedIBMPVS_Compute::class);

        // CPM/Software/Hardware
        $this->call(SeedServerConfigs::class);

        // OptimalTarget
        $this->call(SeedOptimalTargets::class);
    }
}
