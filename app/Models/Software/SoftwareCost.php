<?php

namespace App\Models\Software;

use App\Models\Project\Environment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Models\Software\Software;
use App\Models\Software\FeatureCost;
use App\Models\Software\Feature;

/**
 * Class SoftwareCost
 * @package App\Models\Software
 * @property int $id
 * @property int $software_type_id
 * @property Software $software
 * @property Collection|FeatureCost[] $featureCosts
 * @property float $license_cost_modifier
 * @property float $support_cost_modifier
 * @property Collection|Feature[] $features;
 * @property Environment $environment
 * @property int $environment_id
 * @property int $physical_processors
 * @property int $physical_cores
 */
class SoftwareCost extends Model
{
    protected $table = 'software_costs';
    protected $guarded = ['id'];
    protected $casts = [ 'license_cost_modifier' => 'float', 'support_cost_modifier' => 'float' ];

    /*
     * Uncomment once environment model added
    public function environment(){
        return $this->belongsTo(Environment::class);
    }
     * */

    public function software(){
        return $this->belongsTo(Software::class, 'software_type_id');
    }

    public function environment() {
        return $this->belongsTo(Environment::class);
    }

    public function featureCosts(){
        return $this->hasMany(FeatureCost::class);
    }

    public function features() {
        return $this->belongsToMany(Feature::class, 'feature_costs');
    }

}
