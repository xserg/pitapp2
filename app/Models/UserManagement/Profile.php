<?php namespace App\Models\UserManagement;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Models\Configuration\Setting;
use App\Models\StandardModule\Profile as BaseProfile;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Models\UserManagement\User;

class Profile extends BaseProfile implements AuthenticatableContract, CanResetPasswordContract {

	use Authenticatable, CanResetPassword, HasApiTokens, Notifiable;
    const regex_contains_uppercase = "(?=.*[A-Z])";
    const regex_contains_lowercase = "(?=.*[a-z])";
    const regex_contains_number = "(?=.*[0-9])";
    const regex_contains_special_character = "(?=.*[^a-zA-Z0-9])";
    
    public function user() {
        return $this->belongsTo(User::class);
    }
    
    public function reload() {
        return Profile::where('username', '=', $this->username)->first();
    }
    
    public function logName() {
        return $this->username;
    }
    
    // Returns password complexity rules that are enabled
    public static function passwordComplexityRules() {
        $data = new \stdClass;
        $validatorString = "";
        
        $minlength = Setting::where('key', '=', 'Core.UserManagement_PasswordMinLength')->first();
        $containsUppercase = Setting::where('key', '=', 'Core.UserManagement_PasswordContainsUppercase')->first();
        $containsLowercase = Setting::where('key', '=', 'Core.UserManagement_PasswordContainsLowercase')->first();
        $containsNumber = Setting::where('key', '=', 'Core.UserManagement_PasswordContainsNumber')->first();
        $containsSpecialCharacter = Setting::where('key', '=', 'Core.UserManagement_PasswordContainsSpecialCharacter')->first();
        
        // Set a minlength value here that is an int because the client querries for this material making the validator string 
        // not all that matters.
        $data->minlength = isset($minlength) && isset($minlength->value) ? $minlength->value : 6;
        
        // Now for the regex
        if (isset($containsUppercase) ||
            isset($containsLowercase) ||
            isset($containsNumber) ||
            isset($containsSpecialCharacter)
        ) {
            if(isset($containsUppercase) && $containsUppercase->value) {
                $data->contains_uppercase = Profile::regex_contains_uppercase;
                $validatorString .= Profile::regex_contains_uppercase;
            }

            if(isset($containsLowercase) && $containsLowercase->value) {
                $data->contains_lowercase = Profile::regex_contains_lowercase;
                $validatorString .= Profile::regex_contains_lowercase;
            }

            if(isset($containsNumber) && $containsNumber->value) {
                $data->contains_number = Profile::regex_contains_number;
                $validatorString .= Profile::regex_contains_number;
            }

            if(isset($containsSpecialCharacter) && $containsSpecialCharacter->value) {
                $data->contains_special_character = Profile::regex_contains_special_character;
                $validatorString .= Profile::regex_contains_special_character;
            }
        }
        
        $validatorString .= ".{" . (isset($minlength) && $minlength->value ? $minlength->value : "6") . ",50}";
        $data->regex = $validatorString;
        
        return $data;
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        $user = \App\Models\UserManagement\User::with('groups')->find($this->user_id);
        foreach ($user->groups as $group) {
            if ($group->name == "Admin") {
                return true;
            }
        }

        return false;
    }
}
