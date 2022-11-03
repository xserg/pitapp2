<?php namespace App\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Response;
use App\Models\UserManagement\User;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\UserManagement\Profile;
use App\Models\StandardModule\Activity;
use App\Http\Controllers\Api\StandardModule\SmartController;
use App\Models\Configuration\Setting;
use App\Http\Controllers\Api\AuthController;

class ProfileController extends SmartController {

	/*
	|--------------------------------------------------------------------------
	| Profile Controller
	|--------------------------------------------------------------------------
	|
	| This controller provides authentication access and allows storing
    | storing of user credentials to the database.
	|
	*/
    
    private $messages;
    private $restricted;
    protected $activity = 'User Management';

    //Overrides method in Resource Controller for more granular control
    //of user management
    protected function isAllowed() {
        if (AuthController::staticAuthorize(Activity::where('name', '=', $this->activity)->firstOrFail()->id)) {
            return true;
        }
        if (Request::input('id') && Request::input('id') == Auth::user()->id) {
            $this->restricted = true;
            return true;
        }

        return false;
    }

// private method to set the data of the user for the create and update.
    private function setData($profile) {
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

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	protected function _index() {
        $user = Profile::all();
        
		return response()->json($user->toArray());
	}
    
    public function index() {
        return $this->_index();
    }
    
    /**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
    protected function _store() {
        
        $user = new Profile();
        
        $valid = Validator::make(
            Request::all(),
            array(
                'username' => 'required|unique:profiles,username|alpha_num|max:255',
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
    
    /**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _show($id) {
        $user = Profile::find($id);
        
        return response()->json($user->toArray());
    }
    
    public function show($id) {
        return $this->_show($id);
    }
    
    /**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _update($id) {
        
        $user = Profile::find($id);
        
        if(Request::input('username') !== $user->username) {
            $this->messages[] = array('username' => array('You cannot change an existing username.'));
            return response()->json($this->messages, 500);
        }
        
        if(Request::input('password')) {
            if(!(Request::input('oldPassword') && Hash::check(Request::input('oldPassword'), $user->password))) {
                $this->messages = new MessageBag();
                $this->messages->add('oldPassword', 'The password you entered is invalid');
                return response()->json($this->messages, 500);
            }
        }

        if($this->setData($user) < 0) {
            return response()->json($this->messages, 500);
        }
        
        return response()->json($user->username . " saved", 200);
    }
    
    /**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
    protected function _destroy($id) {
        
        $user = Profile::findOrFail($id);
        $user->delete();

        return response()->json("Profile deleted");
    }
    
    // Returns password complexirty rules from Profile model
    public function passwordComplexityRules() {
        return response()->json(Profile::passwordComplexityRules(), 200);
    }

}
