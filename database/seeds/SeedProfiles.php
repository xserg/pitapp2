<?php

/**
 * Description of SeedUsers
 *
 * @author rsmith
 */

use App\Models\UserManagement\Profile;
use App\Models\UserManagement\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SeedProfiles extends Seeder {
    //put your code here

    public function run() {
        DB::table('profiles')->delete();

        // Admin user
        $user = User::where('email', '=', 'admin@smartsoftwareinc.com')->first();
        $password = Hash::make('1admin234');
        Profile::firstOrCreate(array(
            'username'  => 'admin',
            'password'  => $password,
            'user_id'   => $user->id,
            'email'     => 'admin@smartsoftwareinc.com'
        ));
    }
}
