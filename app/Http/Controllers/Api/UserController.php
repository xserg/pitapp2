<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
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

class UserController extends SmartController {
    /*
      |--------------------------------------------------------------------------
      | User Controller
      |--------------------------------------------------------------------------
      |
      | This controller renders the "marketing page" for the application and
      | is configured to only allow guests. Like most of the other sample
      | controllers, you are free to modify or remove it as you desire.
      |
     */

    protected $messages = null;
    protected $restricted;
    protected $activity = 'User Management';
    
    protected $cached = true;
    protected $model = "App\Models\UserManagement\User";
    
    protected $table = 'users';

    //Overrides method in Resource Controller for more granular control
    //of user management
    protected function isAllowed() {
        if (AuthController::staticAuthorize(Activity::where('name', '=', $this->activity)->firstOrFail()->id)) {
            return true;
        } else if (Request::input('id') && Request::input('id') == Auth::user()->id) {
            $this->restricted = true;
            return true;
        } else {
            return false;
        }
    }

// protected method to set the data of the user for the create and update.
    protected function setData(&$user) {
        // Set the variables of the user
        //Validate the request server-side
        $request = Request::all();

        $validator = Validator::make(
            $request, array(
                'firstName' => 'required|regex:/^[a-zA-Z ]+$/|max:255',
                'lastName' => 'required|regex:/^[a-zA-Z ]+$/|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'regex:/^[0-9]*$/|min:7|max:20',
                'preferredLanguage_id' => 'exists:languages,id'
            )
        );

        if ($validator->fails()) {
            /*
             * Addition rlouro 09/30/2016.
             * If validation failed and an image was uploaded, delete the image
             * in order to prevent untracked image files from flooding the server
             */
            if (Request::input('image')) {
                if(file_exists(public_path() . '/' . Request::input('image'))) {
                    unlink(public_path() . '/' . Request::input('image'));
                }
            }
            $this->messages = $validator->failed();
            return -1;
        }

        $user->firstName = Request::input('firstName');
        $user->lastName = Request::input('lastName');
        $user->email = Request::input('email');
        $user->phone = Request::input('phone');
        $user->suspended = Request::input('suspended') ? Request::input('suspended') : 0;
        $user->preferredLanguage_id = Request::input('preferredLanguage_id') !== null
                ? Request::input('preferredLanguage_id')
                : Language::where('abbreviation', '=', 'en')->first()->id;

        if (Request::input('image')) {
            //Remove the old image
            $oldImage = $user->image;
            if($oldImage !== NULL && $oldImage !== Request::input('image')) {
                // Alteration swheeler 2016-06-17 for #787. No longer stored as absolute.
                //Saved in database as absolute, we need to take off the domain.
                //Only try to delete if the image exists
                if(file_exists(public_path() . '/' . $oldImage)) {
                    unlink(public_path() . '/' . $oldImage);
                }
            }
            
            $user->image = Request::input('image');
        }

        $user->save();
        $user = User::where('email', '=', Request::input('email'))->orderBy('updated_at', 'desc')->withTrashed()->first();
        $id = Request::input('id');
        if($id)
        {
            $user = User::where('id', '=', $id)->orderBy('updated_at', 'desc')->withTrashed()->first();
        }
        

        foreach ($user->profiles as $identity) {
            $identity->email = $user->email;
            $identity->save();
        }

        // get the attach variables
        if (!$this->restricted) {
            $groupAttach = (Request::input('groupAttach')) ? Request::input('groupAttach') : Array();
            $groupDetach = (Request::input('groupDetach')) ? Request::input('groupDetach') : Array();
            $activityAttach = (Request::input('activityAttach')) ? Request::input('activityAttach') : Array();
            $activityDetach = (Request::input('activityDetach')) ? Request::input('activityDetach') : Array();

            // Loop through each of them and perform the need atcion.
            foreach ($groupDetach as $detach) {
                $user->groups()->detach($detach);
            }

            foreach ($groupAttach as $attach) {
                $user->groups()->attach($attach);
            }

            foreach ($activityDetach as $detach) {
                $user->activities()->detach($detach);
            }

            foreach ($activityAttach as $attach) {
                $user->activities()->attach($attach);
            }
        }
        //Addition. jdobrowolski 7/26/2016
        //Check if the Deleted toggle has changed. If it has, delete or restore the user
        //depending on which way it changed
        if(Request::input('deleted_at') === true && empty($user->deleted_at)) {
            $user->delete();
        } else if(Request::input('deleted_at') === false && !empty($user->deleted_at)) {
            $user->restore();
        }

        return $user->id;
    }

    private function isValidated($firstName, $lastName, $email, $username, $preferredLanguageID) {
        $validator = Validator::make(
            array (
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'username' => $username,
                'preferredLanguage_id' => $preferredLanguageID
            )
            , array(
                'firstName' => 'required|regex:/^[a-zA-Z ]*$/|max:255',
                'lastName' => 'required|regex:/^[a-zA-Z ]*$/|max:255',
                'email' => 'required|email|max:255',
                'username' => 'required|regex:/^[a-zA-Z0-9]*$/|max:255',
                'preferredLanguage_id' => 'sometimes|exists:languages,id'
            )
        );
        
        if($validator->fails()) {
            $this->messages = $validator->messages();
        }
        
//        return !$validator->fails();
    }
    
    /**
     * Creates a new user with the request's given credentials.
     * @return Response
     */
    public function customerCreateUser() {
        return $this->_store();
    }

    public function getProfiles($id) {
        $profiles = Profile::where('user_id', '=', $id)->get();
        return response()->json ($profiles, 200);
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
        
        //Check that the username chosen doesn't already exist
        if(Request::input('username') !== null && count(Profile::where('username', '=', Request::input('username'))->get())) {
            return response()->json(array('username' => array('Username is already taken')), 501);
        }
        
        //Check that the password meets all of the requirements of the system
        $rules = Profile::passwordComplexityRules();
        
        $regexString = 'required|regex:/^' . $rules->regex . '$/';
        $validator = Validator::make(array('password' => Request::input('password')), array('password' => $regexString));

        
        if($validator->fails()) {
            $this->messages = $validator->messages();
            return response()->json($this->messages, 500);
        }

        $user = new User();

        $id = $this->setData($user);
        
        //Create a profile for the user
        $profile = new Profile();
        $profile->username  = Request::input('username');
        $profile->password  = Hash::make(Request::input('password'));
        $profile->password_modified = date('Y-m-d H:i:s');
        $profile->user_id   = $id;
        $profile->email = Request::input('email');

        if ($this->messages) {
            return response()->json($this->messages, 500);
        }
        
        $profile->save();

        return response()->json($user->toArray());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    protected function _show($id) {
//        $user = User::find($id);
        $user = User::withTrashed()->where('id', '=', $id)->first();

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

        $user = User::withTrashed()->find($id);

        if ($this->setData($user) < 0) {
            return response()->json($this->messages, 500);
        }

        return response()->json($user->toArray());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    protected function _destroy($id) {

        $user = User::findOrFail($id);
        $profiles = $user->profiles;

        //Detach the user from all groups and activities
        foreach ($user->groups()->get() as $group) {
            $user->groups()->detach($group->id);
        }

        foreach ($user->activities()->get() as $activity) {
            $user->activities()->detach($activity->id);
        }

        foreach ($profiles as $profile) {
            $profile->delete();
        }

        $user->delete();

        return response()->json("User deleted");
    }

    /**
     * 
     * 
     * @return type
     */
    public function upload() {
        $file = Request::file('file'); //file_get_contents($_FILES['file']['tmp_name']);
        $name = Request::input('filename');
        
        /*
         * Modified 09/30/2016 rlouro. 
         * Was going to push uploads to the Public / Admin CDN's however copying 
         * the file multiple times seemed unnecessary and may not be appropriate 
         * to be continuously writing to multiple CDN's. 
         */
        $user_id = Auth::user()->id;
        $upload_path = '/core/images/uploads/' . $user_id . '/';
//        $mCDNWriter = new CDNWriter();
//        $success = $mCDNWriter->write(file_get_contents($file), "images/uploads/" . $user_id . "/", $name);
//        return "images/uploads/" . $user_id . "/" . $name;
        
        /*Deletion 8/20/2015 jdobrowolski. Deleting the old file should only happen if
        * we know we're going to use the new one. Moved up in to set data
         */

        /*
         * Modification 09/30/12016 rlouro. Modified the path to use the variable 
         * defined aboove
         */
        //Check for the existence of the file name.
        if(file_exists(public_path() . $upload_path . $name)) {
            //If it exists, try to append '-#' to the end of the file number
            //Keep incrementing until we find a number that isn't being used
            $newName = substr($name, 0, strrpos($name, '.'));
            $ext = substr($name, strrpos($name, '.'));
            $suffixNum = 0;
            
            while(file_exists(public_path() . $upload_path . $newName . '-' . $suffixNum . $ext)) {
                $suffixNum++;
            }
            
            $file->move(public_path() . $upload_path, $newName . '-' . $suffixNum . $ext);
            return $upload_path . $newName . '-' . $suffixNum . $ext;
        }
        else {
            $file->move(public_path() . $upload_path, $name);

            return $upload_path . $name;
        }
    }

    // 
    public function authuser() {
        $user = Auth::user()->user;
        $user->defaultCompany;
        return $user;
    }

}

/** 
                                ALL HAIL THE DRAGON!

                                        ,   ,  
                                        $,  $,     ,            
                                        "ss.$ss. .s'     
                                ,     .ss$$$$$$$$$$s,              
                                $. s$$$$$$$$$$$$$$`$$Ss       
                                "$$$$$$$$$$$$$$$$$$o$$$       ,       
                               s$$$$$$$$$$$$$$$$$$$$$$$$s,  ,s  
                              s$$$$$$$$$"$$$$$$""""$$$$$$"$$$$$,     
                              s$$$$$$$$$$s""$$$$ssssss"$$$$$$$$"   
                             s$$$$$$$$$$'         `"""ss"$"$s""      
                             s$$$$$$$$$$,              `"""""$  .s$$s
                             s$$$$$$$$$$$$s,...               `s$$'  `
                         `ssss$$$$$$$$$$$$$$$$$$$$####s.     .$$"$.   , s-
                           `""""$$$$$$$$$$$$$$$$$$$$#####$$$$$$"     $.$'
                                 "$$$$$$$$$$$$$$$$$$$$$####s""     .$$$|
                                  "$$$$$$$$$$$$$$$$$$$$$$$$##s    .$$" $ 
                                   $$""$$$$$$$$$$$$$$$$$$$$$$$$$$$$$"   `
                                  $$"  "$"$$$$$$$$$$$$$$$$$$$$S""""' 
                             ,   ,"     '  $$$$$$$$$$$$$$$$####s  
                             $.          .s$$$$$$$$$$$$$$$$$####"
                 ,           "$s.   ..ssS$$$$$$$$$$$$$$$$$$$####"
                 $           .$$$S$$$$$$$$$$$$$$$$$$$$$$$$#####"
                 Ss     ..sS$$$$$$$$$$$$$$$$$$$$$$$$$$$######""
                  "$$sS$$$$$$$$$$$$$$$$$$$$$$$$$$$########"
           ,      s$$$$$$$$$$$$$$$$$$$$$$$$#########""'
           $    s$$$$$$$$$$$$$$$$$$$$$#######""'      s'         ,
           $$..$$$$$$$$$$$$$$$$$$######"'       ....,$$....    ,$
            "$$$$$$$$$$$$$$$######"' ,     .sS$$$$$$$$$$$$$$$$s$$
              $$$$$$$$$$$$#####"     $, .s$$$$$$$$$$$$$$$$$$$$$$$$s.
   )          $$$$$$$$$$$#####'      `$$$$$$$$$###########$$$$$$$$$$$.
  ((          $$$$$$$$$$$#####       $$$$$$$$###"       "####$$$$$$$$$$ 
  ) \         $$$$$$$$$$$$####.     $$$$$$###"             "###$$$$$$$$$   s'
 (   )        $$$$$$$$$$$$$####.   $$$$$###"                ####$$$$$$$$s$$'
 )  ( (       $$"$$$$$$$$$$$#####.$$$$$###' -Robert S.     .###$$$$$$$$$$"
 (  )  )   _,$"   $$$$$$$$$$$$######.$$##'                .###$$$$$$$$$$
 ) (  ( \.         "$$$$$$$$$$$$$#######,,,.          ..####$$$$$$$$$$$"
(   )$ )  )        ,$$$$$$$$$$$$$$$$$$####################$$$$$$$$$$$"        
(   ($$  ( \     _sS"  `"$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$S$$,       
 )  )$$$s ) )  .      .   `$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$"'  `$$   
  (   $$$Ss/  .$,    .$,,s$$$$$$##S$$$$$$$$$$$$$$$$$$$$$$$$S""        ' 
    \)_$$$$$$$$$$$$$$$$$$$$$$$##"  $$        `$$.        `$$.
        `"S$$$$$$$$$$$$$$$$$#"      $          `$          `$
            `"""""""""""""'         '           '           '

**/
