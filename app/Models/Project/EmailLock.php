<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;

class EmailLock extends Model{

    protected $table = 'email_locks';
    public $timestamps = false;
    protected $primaryKey = null;
}
