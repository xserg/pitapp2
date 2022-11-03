<?php namespace App\Models\StandardModule;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Models\StandardModule\Component;

class ActivityLogType extends Model {
    
    public function component() {
        return $this->belongsTo(Component::class);
    }
    
    public function reload() {
        return ActivityLogType::where('key', '=', $this->key)
            ->where('created_at', '=', $this->created_at)->first();
    }

}