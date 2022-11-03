<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use App\Models\UserManagement\User;
use App\Models\UserManagement\Profile;
use Illuminate\Support\Facades\DB;

class EulaController extends Controller {
  protected function hasAccepted($user) {
      //$user = $user . '@smartsoftwareinc.com'; //TEMPORARY until full email is used

      //$user = DB::table('users')->where('email', $user)->first();
      $profile = Profile::where('username', '=', $user)->first();
      if($profile == null){
        return response()->json('User not found', 404);
      }

      if(!$profile->user->eula){
        return response()->json('Eula not accepted', 400);
      }
      response()->json("Eula accepted", 200);
  }

  protected function acceptEula($user){
      //$user = $user . '@smartsoftwareinc.com'; //TEMPORARY until full email is used

      //$user = User::where('email', $user)->first();
      $profile = Profile::where('username', '=', $user)->first();
      if($profile == null){
        return response()->json($user . ' not found', 404);
      }
      if(!$profile->user->eula){
        $profile->user->eula = true;
        $profile->user->eula_timestamp = date('Y-m-d H:i:s');
        $profile->user->save();
      }
      response()->json("Eula set to accepted", 200);
  }

}
