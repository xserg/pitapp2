<?php

namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Api\AuthController;
use App\Models\UserManagement\Profile;
use App\Models\Configuration\Setting;
use App\Models\UserManagement\User;
use App\Models\Project\Log;
use App\Models\Project\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;


class PrecisionAuthController extends AuthController {
    private $failedLoginMessage = 'The email or password entered is incorrect. Please try again or click the forgot password link if you are still unable to login.';

    public function authenticate(): JsonResponse {
        $username = Request::input('username');
        $password = Request::input('password');

        $expires = Setting::where('key', '=', 'Core.UserManagement_PasswordExpires')->first();
        if(isset($expires)) {
            $expires = is_numeric($expires->value) && $expires->value > 0 ? $expires->value : null;
        }

        if(isset($expires)) {
            $profile = Profile::where('username', '=', $username)->first();
            if($profile !== null && strtotime($profile->password_modified) > strtotime("-$expires days")) {
                if(Auth::attempt(array('username' => $username, 'password' => $password)) && !Auth::user()->user->suspended) {

                    return response()->json(Auth::user()->user, 200);
                } else {
                    return response()->json($this->failedLoginMessage, 403);
                }
            } else {
                return response()->json("Password expired.", 403);
            }
        }

        if(Auth::attempt(array('username' => $username, 'password' => $password)) && !Auth::user()->user->suspended) {
            $user = Auth::user()->user;
            $user->activities = $user->allActivities();
            $requestPage = Request::header('referer');
            $requestPage = strtolower($requestPage);
            if(strpos($requestPage, 'admin') !== false) {
                foreach($user->groups as $group) {
                    if($group->name == "Admin") {
                        return response()->json($user, 200);
                    }
                }
                return response()->json("User does not have access to this.", 403);
            }
            $company = Company::find($user->company_id);
            if ($company == null) {
                return response()->json("User is not associated to a company", 403);
            }
            if($company->access_start && strtotime($company->access_start) > time()) {
                return response()->json("Company access has not started", 403);
            }
            if($company->access_end && strtotime($company->access_end) < time()) {
                return response()->json("Company access has ended", 403);
            }

            $log = new Log();
            $log->user_id = Auth::user()->user->id;
            $log->log_type = "login";
            $log->save();
            $usr = User::find(Auth::user()->user->id);
            $usr->ytd_logins++;
            $usr->save();
            if (!empty($user->image) && !starts_with($user->image, 'api/'))
                $user->image = 'api/'.$user->image;
            return response()->json($user, 200);
        } else {
            return response()->json($this->failedLoginMessage, 403);
        }
    }

}
