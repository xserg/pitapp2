<?php

namespace App\Models\Software;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SoftwareFeature
 * @property Software $software
 * @property Feature $feature
 */
class SoftwareFeature extends Model {
    protected $table = 'software_features';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function software(){
        return $this->belongsTo(Software::class, 'software_id');
    }
    public function feature(){
        return $this->belongsTo(Feature::class, 'feature_id');
    }

}
