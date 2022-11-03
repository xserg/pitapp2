<?php

namespace App\Http\Controllers\Api\Project;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Models\Language\Language;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\User;
use App\Models\UserManagement\Profile;
use App\Http\Controllers\Api\StandardModule\SmartController;
use App\Writers\CDNWriter;
use App\Models\Configuration\Setting;
use App\Models\Project\PrecisionUser;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PrecisionUserController extends UserController {
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

         if (Request::exists('image')) {
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
         $user = PrecisionUser::where('email', '=', Request::input('email'))->orderBy('updated_at', 'desc')->withTrashed()->first();
         $id = Request::input('id');
         if($id)
         {
             $user = PrecisionUser::where('id', '=', $id)->orderBy('updated_at', 'desc')->withTrashed()->first();
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

        $user = new PrecisionUser();
        if(Request::has('company'))
            $user->company = Request::input('company');
        if(Request::exists('company_id'))
            $user->company_id = Request::input('company_id');
        if(Request::has('view_cpm'))
            $user->view_cpm = Request::input('view_cpm') ? true : false;
        if(Request::has('eula'))
           $user->eula = Request::input('eula');
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
        $user->companyObj;
        return response()->json($user->toArray());
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    protected function _update($id) {

        $user = PrecisionUser::withTrashed()->find($id);
      //  return response()->json('have just updated!');
        if(Request::has('company'))
            $user->company = Request::input('company');
        if(Request::exists('company_id'))
            $user->company_id = Request::input('company_id');
        if(Request::has('view_cpm'))
            $user->view_cpm = Request::input('view_cpm') ? true : false;
        if(Request::has('eula'))
            $user->eula = Request::input('eula');
        if ($this->setData($user) < 0) {
            return response()->json($this->messages, 500);
        }
        $user->companyObj;
        return response()->json($user->toArray());
    }

    protected function _show($id) {
        $user = PrecisionUser::withTrashed()->where('id', '=', $id)->first();
        $user->companyObj;
        return response()->json($user->toArray());
    }

    public function authuser() {
        $user = Auth::user()->user;
        $pUser = PrecisionUser::where("id", "=", $user->id)->with('companyObj')->first();
        return $pUser;
    }

}
