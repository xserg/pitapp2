<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use App\Models\Project\Region;


class Currency extends Model{

    protected $table = 'currencies';
    protected $guarded = ['id'];
    
    public function regions() {
        return $this->belongsToMany(Region::class);
    }
}
