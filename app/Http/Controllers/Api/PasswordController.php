<?php namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Project\UserProfileController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\SendPasswordEmailTrait;
use App\Models\UserManagement\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use App\Models\UserManagement\Profile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller {

	/*
	|--------------------------------------------------------------------------
	| Password Reset Controller
	|--------------------------------------------------------------------------
	|
	| This controller is responsible for handling password reset requests
	| and uses a simple trait to include this behavior. You're free to
	| explore this trait and override any methods you wish to tweak.
	|
	*/

//	use ResetsPasswords;
	use SendPasswordEmailTrait;
    
    protected $redirectTo = '/';
    
    protected $complex;

        /**
	 * Create a new password controller instance.
	 *
	 * @param  \Illuminate\Contracts\Auth\Guard  $auth
	 * @param  \Illuminate\Contracts\Auth\PasswordBroker  $passwords
	 * @return void
	 */
	public function __construct(Guard $auth, PasswordBroker $passwords)
	{
		$this->auth = $auth;
		$this->passwords = $passwords;

		$this->middleware('guest');
	}
    
    public function postEmail(\Illuminate\Http\Request $request)
    {
        Log::debug('test');
        $this->validate($request, ['email' => 'required|email']);
        
        $proto = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
        Session::put('referer', $proto . "://" . Request::getHttpHost() . "/");

        $user = User::where('email', $request->get('email'))->first();
        $this->sendPasswordEmail($user);


    }
    
    //Override Laravel's built-in method to handle this
    //so that we can deal with it in Angular
    public function postReset(\Illuminate\Http\Request $request) {


        /**
         *
         * ================================================
         *
         * @deprecated This is not used by the system.
         * @see UserProfileController::updatePassword()
         *
         * ================================================
         *
         *
         */



        //Check that the password meets all of the requirements of the system
        $rules = Profile::passwordComplexityRules();
        
        if(isset($rules) && isset($rules->regex)) {
            $regexString = 'regex:/^' . $rules->regex . '$/';
            $this->complex = true;
        } else {
            $regexString = "min:6";
            $this->complex = false;
        }
        
        $validator = Validator::make($request->all(), array(
            'token' => 'required',
			'email' => 'required|email',
			'password' => 'required|confirmed|' . $regexString
        ));
        
        if($validator->fails()) {
            //Check the password format, and give it a descriptive error message
            $messages = $validator->messages()->getMessages();
            foreach($messages['password'] as &$error) {
                if(strpos($error, "password format")) {
                    $error = $this->complex ? "The password must be at least eight characters, and contain upper and lower case letters, and a number or special character." : "The password must be at least six characters.";
                }
            }
            return response()->json($messages, 500);
        }
        
//		$this->validate($request, [
//			'token' => 'required',
//			'email' => 'required|email',
//			'password' => 'required|confirmed|' . $regexString,
//		]);

		$credentials = $request->all(
			'email', 'password', 'password_confirmation', 'token'
		);

		$response = $this->passwords->reset($credentials, function($user, $password)
		{
			$user->password = bcrypt($password);
            
            $user->password_modified = date('Y-m-d H:i:s');

			$user->save();
            
            return response()->json("Password Successfully reset.", 200);

//			$this->auth->login($user);
		});

		switch ($response)
		{
			case PasswordBroker::PASSWORD_RESET:
				return response()->json("Password Successfully reset.", 200);

			default:
				return response()->json("Failed to reset password.", 500);
		}
	}

}
