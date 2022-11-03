<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use App\Models\UserManagement\User;

class UserSavedEmail extends Model{

    protected $table = 'user_saved_emails';
    protected $guarded = ['id'];
    public $timestamps = false;

    //These describe the table relations
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function create(array $attributes = [])
    {
        return static::query()->create($attributes);
    }
}
