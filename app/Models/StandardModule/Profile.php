<?php namespace App\Models\StandardModule;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Models\StandardModule\User;
use App\Models\StandardModule\StandardModel;

abstract class Profile extends StandardModel implements AuthenticatableContract, CanResetPasswordContract {

	use Authenticatable, CanResetPassword;

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = array('password', 'remember_token');
    
    public function user() {
        return $this->belongsTo(User::class);
    }
    
    public function logName() {
        return User::where('id', '=', $this->user_id)->first()->email;
    }

}
