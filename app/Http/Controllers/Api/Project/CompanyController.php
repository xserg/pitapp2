<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use App\Services\Filesystems;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Company;
use App\Models\UserManagement\User;
use App\Models\UserManagement\Profile;
use App\Http\Controllers\Traits\SendPasswordEmailTrait;

class CompanyController extends Controller {

    use SendPasswordEmailTrait;
    protected $model = "App\Models\Project\Company";

    protected $activity = 'Company Management';
    protected $table = 'companies';


    // Private Method to set the data
    private function setData(&$company) {
        $validator = Validator::make(
            Request::all(),
            array(
                'name' => 'required|string|max:255',
                'address' => 'string|max:255'
        ));

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return false;
        }

        // Set all the data here (fill in all the fields)
        $company->name = Request::input('name');
        $company->address = Request::input('address');
        $company->access_start = Request::input('access_start');
        $company->access_end = Request::input('access_end');

        $analyses_attributes = [
            'licensed_analyses',
            'licensed_analyses_targets',
            'licensed_analyses_interval',
        ];

        foreach ($analyses_attributes as $attribute) {
            if (Request::has($attribute)) {
                $value = Request::input($attribute);

                $company->{$attribute} = empty($value) ? null : $value;
            }
        }

        if(Request::has('view_cpm'))
            $company->view_cpm = Request::input('view_cpm');

        if (Request::exists('logo')) {
            //Remove the old image
            $oldImage = $company->logo;
            if($oldImage !== NULL && $oldImage !== Request::input('logo')) {
                // Alteration swheeler 2016-06-17 for #787. No longer stored as absolute.
                //Saved in database as absolute, we need to take off the domain.
                //Only try to delete if the image exists
                if(file_exists(public_path() . '/' . $oldImage)) {
                    unlink(public_path() . '/' . $oldImage);
                }
            }

            $company->logo = Request::input('logo');
        }

        $company->save();

        if(Request::exists('users')) {
            $users = Request::input('users');
            //Go through the users

            foreach ($users as $user) {
                $sendNewUserEmail = false;
                //If it doesn't have an ID, it's a new user
                if (Arr::exists($user, 'id')) {
                    $companyUser = User::find($user['id']);
                } else if (Arr::exists($user, 'email')) {
                    // Check if user with that email already exists
                    $companyUser = User::where('email', '=', $user['email'])->first();
                    if ($companyUser == null) {
                        // New user, create it
                        $companyUser = new User();
                        $companyUser->email = $user['email'];
                        $companyUser->firstName = $user['firstName'];
                        $companyUser->lastName = $user['lastName'];
                        $companyUser->save();
                        $companyUser = User::where("email", $companyUser->email)->firstOrFail();

                        $profile = Profile::where('user_id', '=', $companyUser->id)->first();
                        if ($profile == null) {
                            $profile = new Profile();
                            $profile->user_id = $companyUser->id;
                        }
                        $profile->username = $companyUser->email;
                        $profile->email = $companyUser->email;
                        $profile->password = '';
                        $profile->save();

                        $sendNewUserEmail = true;
                    }
                }

                if ($companyUser == null) {
                    Log::error("User not found:\n" . json_encode($user));
                    return false;
                } else if ($companyUser->company_id != null && $companyUser->company_id != $company->id) {
                    Log::error("User " . $companyUser->id . " is already associated with a different company!");
                    return false;
                }

                // Associate company to user
                $companyUser->company_id = $company->id;
                $companyUser->image = str_replace('api/', '', $company->logo);
                $companyUser->view_cpm = $company->view_cpm;
                $companyUser->save();

                if ($sendNewUserEmail) {
                    // Send new user email
                    $this->sendPasswordEmail($companyUser);
                }
            }
        }

        if (Request::exists('removedUsers')) {
            $users = Request::input('removedUsers');
            //Go through the users
            foreach($users as $user) {
                //If it doesn't have an ID, it's a new user
                if (Arr::exists($user, 'id')) {
                    $existingUser = User::find($user['id']);
                    $existingUser->image = null;
                    $existingUser->company_id = null;
                    $existingUser->save();
                }
            }
        }

        return $company;
    }

    protected function index() {
        $companies = Company::all();

        return response()->json($companies);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $company = Company::find($id);
        $company->users;
        if (!starts_with($company->logo, 'api/'))
            $company->logo = $company->logo ? 'api/'.$company->logo : null;
        return response()->json($company->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        //return "store()";

        // Create item and set the data
        $company = new Company;
        if(!$this->setData($company)) {
            return response()->json($this->messages, 500);
        }
        $company->users;
        return response()->json($company->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        //return "update($id)";

        // Retrieve the item and set the data
        $company = Company::find($id);
        if(!$this->setData($company)) {
            return response()->json($this->messages, 500);
        }
        $company->users;
        return response()->json($company->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Company::destroy($id);

        // return a success
        return response()->json("Destory Successful");
    }

    public function download($id, $path) {
        return Filesystems::imagesFilesystem()->download("images/company/uploads/{$id}/{$path}");
    }

    public function upload($id) {
        $company = Company::find($id);

        $file = Request::file('file'); //file_get_contents($_FILES['file']['tmp_name']);
        $name = Request::input('filename');

        $upload_path = 'images/company/uploads/'.$id.'/';

        $path = Filesystems::imagesFilesystem()->putFileAs($upload_path, $file, $name);
        Filesystems::imagesFilesystem()->setVisibility($upload_path.$name, 'public');
        if ($path) {
            logger($path);
            $company->logo = $upload_path.$name;
            $company->save();
            return 'api/'. $company->logo;
        }



//        /*
//         * Modified 09/30/2016 rlouro.
//         * Was going to push uploads to the Public / Admin CDN's however copying
//         * the file multiple times seemed unnecessary and may not be appropriate
//         * to be continuously writing to multiple CDN's.
//         */
//
//        //$user_id = Auth::user()->id;
//        $upload_path = '/core/company/images/uploads/' . $id . '/';
////        $mCDNWriter = new CDNWriter();
////        $success = $mCDNWriter->write(file_get_contents($file), "images/uploads/" . $user_id . "/", $name);
////        return "images/uploads/" . $user_id . "/" . $name;
//
//        /*Deletion 8/20/2015 jdobrowolski. Deleting the old file should only happen if
//        * we know we're going to use the new one. Moved up in to set data
//         */
//
//        /*
//         * Modification 09/30/12016 rlouro. Modified the path to use the variable
//         * defined aboove
//         */
//        //Check for the existence of the file name.
//        if(file_exists(public_path() . $upload_path . $name)) {
//            //If it exists, try to append '-#' to the end of the file number
//            //Keep incrementing until we find a number that isn't being used
//            $newName = substr($name, 0, strrpos($name, '.'));
//            $ext = substr($name, strrpos($name, '.'));
//            $suffixNum = 0;
//
//            while(file_exists(public_path() . $upload_path . $newName . '-' . $suffixNum . $ext)) {
//                $suffixNum++;
//            }
//
//            $file->move(public_path() . $upload_path, $newName . '-' . $suffixNum . $ext);
//            return $upload_path . $newName . '-' . $suffixNum . $ext;
//        }
//        else {
//            $file->move(public_path() . $upload_path, $name);
//
//            return $upload_path . $name;
//        }
    }

    public function sendAccountEmail($id) {
        try {
            if (!$id || !intval($id)) {
                throw new \Exception("No id provided.");
            }
            $user = User::findOrFail($id);
            $this->sendPasswordEmail($user);
        } catch (\Throwable $e) {

        }
    }
}
