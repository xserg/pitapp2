<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class UserSeeder extends Seeder {

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
        $this->call(SeedGroups::class);
        $this->call(SeedUsers::class);
        $this->call(SeedProfiles::class);
        $this->call(SeedConfigurationCommon::class);
    }

}
