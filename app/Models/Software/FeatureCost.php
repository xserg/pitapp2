<?php

namespace App\Models\Software;

use Illuminate\Database\Eloquent\Model;
use App\Models\Software\SoftwareCost;
use App\Models\Software\Feature;

/**
 * Class FeatureCost
 * @package App\Models\Software
 * @property Feature $feature
 * @property SoftwareCost $softwareCost
 * @property float $license_cost_discount
 * @property float $support_cost_discount
 * @property string $formula
 * @property int $id
 * @property int $feature_id
 */
class FeatureCost extends Model
{
    protected $table = 'feature_costs';
    protected $guarded = ['id'];
    public $timestamps = false;

    /*
     * Uncomment once environment model added
    public function environment(){
        return $this->belongsTo(Environment::class);
    }
     * */

    public function softwareCost(){
        return $this->belongsTo(SoftwareCost::class);
    }
    public function feature(){
        return $this->belongsTo(Feature::class);
    }

}
