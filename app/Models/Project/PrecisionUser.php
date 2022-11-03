<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use App\Models\Project\Project;
use App\Models\Project\Company;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\Profile;
use App\Models\UserManagement\Group;


class PrecisionUser extends \App\Models\UserManagement\User {

    protected $table = 'users';
    protected $guarded = ['id'];

    //These describe the table relations
    public function projects() {
        return $this->hasMany(Project::class, 'user_id');
    }

    public function companyObj() {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function activities() {
        return $this->belongsToMany(Activity::class, 'activity_users', 'user_id');
    }

    public function profiles() {
        return $this->hasMany(Profile::class, 'user_id');
    }

    public function groups() {
        return $this->belongsToMany(Group::class, 'user_groups', 'user_id');
    }
}
