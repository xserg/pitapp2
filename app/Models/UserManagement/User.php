<?php namespace App\Models\UserManagement;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\StandardModule\User as BaseUser;
use App\Models\StandardModule\Activity;
use App\Models\UserManagement\Profile;
use App\Models\UserManagement\Group;
use App\Models\Project\Company;
use App\Models\Language\Language;

/**
 * Class User
 * @package App\Models\UserManagement
 * @property $ytd_login_queries
 */
class User extends BaseUser implements AuthenticatableContract, CanResetPasswordContract {

        use Authenticatable, CanResetPassword, SoftDeletes;

    /**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = ['password', 'remember_token', 'preferred_language'];
    protected $fillable = [
        'firstName',
        'lastName',
        'email',
        'analysis_counter',
        'analysis_target_counter',
        'analysis_counter_last_reset',
    ];

    protected $appends = ['preferred_language_abbreviation'];

    public $incrementing = false;
    
    protected $index = [['email', 'LIKE', 'OR'], ['lastName', 'LIKE', 'OR'], ['firstName', 'LIKE', 'OR']];
    
    protected $viewColumns = ['lastName', 'firstName', 'email', 'phone', 'id'];
    
    public static $elasticMapping = array(
        "firstName" => array(
            "type" => "string",
            "index" => "not_analyzed"
        ),
        "lastName" => array(
            "type" => "string",
            "index" => "not_analyzed"
        )
    );

    protected function prepare($query) {
        $query = (array) $query;
        
        if(isset($query['firstName'])) {
            $query['firstName'] = "'%" . $query['firstName'] . "%'";
        }
        
        if(isset($query['email'])) {
            $query['email'] = "'%" . $query['email'] . "%'";
        }
        
        if(isset($query['lastName'])) {
            $query['lastName'] = "'%" . $query['lastName'] . "%'";
        }
        
        return $query;
    }
    
    public function reload() {
        return User::where('email', '=', $this->email)->where('created_at', '=', $this->created_at)->first();
    }
        
    public function activities() {
        return $this->belongsToMany(Activity::class, 'activity_users');
    }

    public function profiles() {
        return $this->hasMany(Profile::class);
    }
    
    public function groups() {
        return $this->belongsToMany(Group::class, 'user_groups');
    }

    public function defaultCompany() {
        return $this->belongsTo(Company::class, 'company_id');
    }
    
    public function preferred_language() {
        return $this->belongsTo(Language::class, 'preferredLanguage_id');
    }
    
    public function getPreferredLanguageAbbreviationAttribute() {
        // During seeding, languages might not be available, so this works around that.
        $language = $this->preferred_language;
        return  $language ? $language->abbreviation : $this->preferredLanguage_id;
    }
    
    /*
     * Created By - rsegura
     * Special Method to retrieve all of a user's groups activities and put them
     * into an array.
     * 
     */
    public function groupActivities() {
        $active = $this->groups()->first() ? $this->groups()->first()->activities : array();
        
        //$active = array();
        
        foreach($this->groups as $group) {
            $active->merge($group->activities);
        }
        
        return $active;
    }
    
    public function allActivities() {
        $userActivities = $this->activities()->get();
        $groupActivites = $this->groupActivities();
        
        return $userActivities->merge($groupActivites);
    }
    
    public function logName() {
        return $this->firstName . " " . $this->lastName;
    }

}
