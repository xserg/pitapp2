<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\UserManagement\User;
use App\Models\UserManagement\Profile;
use App\Models\Language\Language;
use App\Models\Project\UserSavedEmail;

class UserProfileController extends Controller {

    protected $activity = 'User Management';

    protected function createUserProfile() {
        $request = Request::all();

        //Validate everything for both user and profile before creating anything
        //Validate the user entry
        $messages = [
          'required' => 'The :attribute field is required',
          'email.email' => 'Incorrect email format',
          'max' => 'The max allowed size of :attribute is :max',
          'min' => 'The minimum allowed length of :attribute is :min',
          'unique' => 'The :attribute is already taken',
          'alpha_num' => 'The :attribute may only use letters and numbers'
        ];
        $validator = Validator::make(
            $request, array(
                'firstName' => 'required|regex:/^[a-zA-Z ]+$/|max:255',
                'lastName' => 'required|regex:/^[a-zA-Z ]+$/|max:255',
                'email' => 'required|email|unique:users,email|max:255',
                'phone' => 'regex:/^[0-9]*$/|min:7|max:20',
                'preferredLanguage_id' => 'exists:languages,id'
            ),
            $messages
        );

        if ($validator->fails()) {
            $this->messages = $validator->messages();
            return response()->json($this->messages, 500);
        }
        //Validate uniqueness of username and password length
        $validator = Validator::make(
            Request::all(),
            array(
                'username' => 'required|unique:profiles,username|alpha_num|max:255',
                'password' => 'required|min:6'
            ),
            $messages
        );

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return response()->json($this->messages, 500);
        }

        //Check if we have any password complexity rules. If we do, check them
        $rules = Profile::passwordComplexityRules();

        if(isset($rules) && isset($rules->regex)) {
            $validationMessage = "";
            $validationMessage .= "The minimum password length is " . $rules->minlength . ".";
            if($rules->contains_uppercase)
                $validationMessage .= "</br>Your password must contain at least 1 uppercase letter.";
            if($rules->contains_lowercase)
                $validationMessage .= "</br>Your password must contain at least 1 lowercase letter.";
            if($rules->contains_number)
                $validationMessage .= "</br>Your password must contain at least 1 number.";
            if($rules->contains_special_character)
                $validationMessage .= "</br>Your password must contain at least 1 special character.";
            $regexString = 'regex:/^' . $rules->regex . '$/';
            $validator = Validator::make($request, array('password' => $regexString), array('regex' => $validationMessage));
        } else {
            $validator = Validator::make($request, array('password' => 'min:6'));
        }

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return response()->json($this->messages, 500);
        }
        $user = new User;
        $user->firstName = Request::input('firstName');
        $user->lastName = Request::input('lastName');
        $user->email = Request::input('email');
        $user->phone = Request::input('phone');
        $user->suspended = Request::input('suspended') ? Request::input('suspended') : 0;
        $user->preferredLanguage_id = Request::input('preferredLanguage_id') !== null
                ? Request::input('preferredLanguage_id')
                : Language::where('abbreviation', '=', 'en')->first()->id;
        $user->save();
        //Reload the user to get any updated values, such as ID
        $user = User::where('email', '=', Request::input('email'))->orderBy('updated_at', 'desc')->withTrashed()->first();

        //Create the profile for the user
        $profile = new Profile;

        $profile->user_id = $user->id;
        $profile->username = Request::input('username');
        if(Request::input('password')) {
            $profile->password = Hash::make(Request::input('password'));
        }

        $profile->password_modified = date('Y-m-d H:i:s');
        $profile->email = Request::input('email');

        $profile->save();

        return response()->json($user->toArray());
    }

    function getUserProfiles() {
        // todo improve image functions
        $users = User::where('suspended', '!=', '1')->get()->map(function ($user) {
            if (!starts_with($user->image, 'api/'))
                $user->image = $user->image ? 'api/'.$user->image : $user->image;
            return $user;
        });
        return response()->json($users);
    }

    function getUserProfile($id) {
        $user = User::find($id);
        //todo improve image handling
        if (!starts_with($user->image, 'api/'))
            $user->image = $user->image ? 'api/'.$user->image : $user->image;
        return response()->json($user->toArray());
    }

    function getSavedEmails($id) {
        $emails = UserSavedEmail::where("user_id", "=", $id)->get();
        return response()->json($emails);
    }

    function updatePassword() {
        $email =  Request::input('email');
        $password =  Request::input('password');
        $confirmPassword =  Request::input('passwordConfirm');
        $token =  Request::input('token');

        $user = User::where('email', '=', $email)->where('resetHash', '=', $token)->first();
        if($user == null) {
            return response()->json(["User and Token combination not found"], 400);
        }
        if($password != $confirmPassword) {
            return response()->json(["Passwords do not match"], 400);
        }
        $rules = Profile::passwordComplexityRules();

        $regexString = 'required|regex:/^' . $rules->regex . '$/';
        $validator = Validator::make(array('password' => $password), array('password' => $regexString));

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return response()->json($this->messages, 400);
        }

        $hashedPassword  = Hash::make($password);
        $profile = $user->profiles[0];
        if(!$profile) {
            return response()->json(["No valid profile found"], 400);
        }
        $profile->password = $hashedPassword;
        $profile->save();
        $user->resetHash = null;
        $user->save();

    }

    public function userComplete() {
        $email =  Request::input('email');
        try {
            $users = User::where("email", "=", $email);
            /** @var User $user */
            $user = $users->first();
            $profile = $user ? $user->profiles[0] ?? false : false;
            $complete = $profile && strlen($profile->password ?? "") > 0;
            // If the user already has a password they must have completed registration
            return response()->json([
                'complete' => $complete ? "1" : "0"
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                "complete" => "0"
            ]);
        }
    }
}
