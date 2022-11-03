<?php

/**
 * Description of SeedUsers
 * Optimized 2016-06-17 for #788
 *
 * @author rsmith, swheeler
 */

use App\Models\Project\Company;
use Illuminate\Database\Seeder;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;

class SeedUsers extends Seeder {

    public function run() {
        $now = new DateTime();
        $company = Company::firstOrCreate(array(
            'name' => 'Smart Software Inc',
            'address' => "123 Fake St\nFake, NE 68111",
            'created_at' => $now,
            'updated_at' => $now,
            'access_start' => $now
        ));

        $userIds = [];
        $userIds[] = User::firstOrCreate(array(
            'firstName' => 'Admin',
            'lastName'  => 'Admin',
            'email'     => 'admin@smartsoftwareinc.com',
            'company_id' => $company->id
        ))->reload()->id;

        Group::where('name', '=', 'Admin')->first()->users()->sync($userIds);
    }
}
