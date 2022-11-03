<?php namespace App\Models\UserManagement;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Models\UserManagement\User;
use App\Models\StandardModule\Activity;

class Group extends CoreModel {
    
    protected $fillable = ['name', 'description'];
    
    public function users() {
        return $this->belongsToMany(User::class, 'user_groups');
    }
    
    public function activities() {
        return $this->belongsToMany(Activity::class, 'activity_groups');
    }
    
    public function reload() {
        return $this;
    }
    
    public function logName() {
        return $this->name;
    }

}