<?php namespace App\Models\StandardModule;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class Component extends Model {

    protected $fillable = ['name', 'component', 'description', 'language_key'];

    public function activities() {
        return $this->hasMany('Activity');
    }

    public function languageKeys() {
        return $this->hasMany('LanguageKey');
    }

    public function logName() {
        return $this->name;
    }
}
