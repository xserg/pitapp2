<?php

namespace App\Http\Controllers\Api\Project;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Models\Language\Language;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\User;
use App\Models\UserManagement\Profile;
use App\Http\Controllers\Api\StandardModule\SmartController;
use App\Writers\CDNWriter;
use App\Models\Configuration\Setting;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PrecisionProfileController extends ProfileController {

    private $messages;
    private $restricted;

    protected function setData($profile) {
        // Set the variables of the user

        //Validate the request server-side
        $request = Request::all();

        $rules = Profile::passwordComplexityRules();

        if(isset($rules) && isset($rules->regex)) {
            $regexString = 'regex:/^' . $rules->regex . '$/';
            $validator = Validator::make($request, array('password' => $regexString));
        } else {
            $validator = Validator::make($request, array('password' => 'min:6'));
        }

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return -1;
        }

        $profile->user_id = Request::input('user_id');
        $profile->username = Request::input('username');
        if(Request::input('password')) {
            $profile->password = Hash::make(Request::input('password'));
        }

        $profile->password_modified = date('Y-m-d H:i:s');

        //var_dump($profile->user_id);

        // get the user and use its email for the profiles email.
        $profile->email = User::withTrashed()->find($profile->user_id)->email;

        $profile->save();

        return 0;
    }

     protected function _store() {

         $user = new Profile();

         $valid = Validator::make(
             Request::all(),
             array(
                 'username' => 'required|unique:profiles,username|max:255',
                 'password' => 'required|min:6'
         ));

         if($valid->fails()) {
             $this->messages = $valid->messages();
             return response()->json($this->messages, 500);
         }

         $this->setData($user);

         if($this->messages) {
             return response()->json($this->messages, 500);
         }

         return response()->json($user->username . " saved");
     }

}
