<?php namespace App\Models\StandardModule;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Models\StandardModule\StandardModel;
use App\Models\StandardModule\User;
use App\Models\StandardModule\Component;
use App\Models\Language\LanguageKey;

class Activity extends StandardModel {
    
    protected $fillable = ['name', 'description', 'component_id', 'state',
        'icon', 'language_key'];
    
//    public function groups() {
//        return $this->belongsToMany(Group::class, 'activity_groups');
//    }
    
    public function users() {
        return $this->belongsToMany(User::class, 'activity_users');
    }
    
    public function activityComponent() {
        return $this->belongsTo(Component::class, 'component_id');
    }

    public function language_key() {
        return $this->belongsTo(LanguageKey::class, 'language_key');
    }
    
    public function logName() {
        return $this->name;
    }
}