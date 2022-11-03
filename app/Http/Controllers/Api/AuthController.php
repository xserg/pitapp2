<?php namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserManagement\Profile;
use App\Models\Configuration\Setting;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class AuthController extends Controller {

	/*
	|--------------------------------------------------------------------------
	| Registration & Login Controller
	|--------------------------------------------------------------------------
	|
	| This controller handles the registration of new users, as well as the
	| authentication of existing users. By default, this controller uses
	| a simple trait to add these behaviors. Why don't you explore it?
	|
	*/
    use AuthenticatesUsers;

	public function __construct()
	{
		$this->middleware('guest', ['except' => 'getLogout']);
	}

    public function authenticate() {
        $username = Request::input('username');
        $password = Request::input('password');

        $expires = Setting::where('key', '=', 'Core.UserManagement_PasswordExpires')->first();
        if(isset($expires)) {
            $expires = is_numeric($expires->value) && $expires->value > 0 ? $expires->value : null;
        }

        $profile = Profile::where('username', '=', $username)->first();

        if(isset($expires)) {
            $profile = Profile::where('username', '=', $username)->first();
            if($profile !== null && strtotime($profile->password_modified) > strtotime("-$expires days")) {
                if(Auth::attempt(array('username' => $username, 'password' => $password)) && !Auth::user()->user->suspended) {
                    return response()->json(Auth::user()->user, 200);
                } else {
                    return response()->json("Invalid user credentials", 401);
                }
            } else {
                return response()->json("Password expired.", 403);
            }
        }

        if(Auth::attempt(array('username' => $username, 'password' => $password)) && !Auth::user()->user->suspended) {
            return response()->json(Auth::user()->user, 200);
        } else {
            return response()->json("Invalid user credentials", 401);
        }
    }

    public function check() {
        return response()->json(Auth::check(), 200);
    }

    public static function user() {
        if(Auth::check()) {
            return Auth::user();
        }
        return NULL;
    }

    public function logout() {
        if(Auth::check()) {
            Auth::logout();
        }
    }

    public function customerLogout() {
        if (Auth::check()) {
            Auth::logout();
            response()->json("Logout successful.", 200);
        } else {
            response()->json("No user needed to be logged out.", 200);
        }
    }

    /**
     * @param mixed $activity
     * @param array $arguments
     * @return bool
     */
    public function authorize($activity, $arguments = []): bool {
        if (Auth::check()) {
            $ids = collect(Auth::user()->user->allActivities())->pluck('id');
            return $ids->contains($activity);
        }
        return false;
    }

    /**
     * @param integer $activity
     * @return bool
     */
    public static function staticAuthorize($activity): bool
    {
        if (Auth::check()) {
            $ids = collect(Auth::user()->user->allActivities())->pluck('id');
            return $ids->contains($activity);
        }
        return false;
    }

    // Modification rsegura 2/20/2015
    // added new method in order to authenticate the user is editing there own profile
    public static function authorizeProfile($id): bool
    {
        if (Auth::check()) {
            return Auth::user()->id === intval($id);
        }
        return false;
    }
}
