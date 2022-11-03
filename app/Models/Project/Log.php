<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use App\Models\Project\PrecisionUser;


class Log extends Model {

    protected $table = 'logs';
    protected $guarded = ['id'];

    public function user() {
        return $this->belongsTo(PrecisionUser::class, 'user_id');
    }
}
