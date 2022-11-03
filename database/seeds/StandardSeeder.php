<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class StandardSeeder extends Seeder {

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		Model::unguard();
        
        $this->call(SeedComponents::class);
        $this->call(SeedActivityTypes::class);
        $this->call(SeedActivities::class);
        $this->call(SeedValueTypes::class);
        $this->call(SeedSettings::class);
        $this->call(SeedEnvironmentTypes::class);
	}

}
