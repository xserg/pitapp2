<?php

namespace App\Models\Software;

use Illuminate\Database\Eloquent\Model;
use App\Models\Software\SoftwareCost;
use App\Models\Software\Software;

/**
 * Class Feature
 * @package App\Models\Software
 * @property string $name
 * @property FeatureCost[] $featureCosts
 * @property Software[] $softwares
 * @property string $architecture
 * @property string $full_name
 * @property int $id
 * @property float $license_cost
 * @property float $multiplier
 * @property float $cost_per
 * @property int $nup
 * @property int $support_type
 * @property float $annual_cost_per
 * @property float $support_multiplier
 * @property float $support_cost
 */
class Feature extends AbstractSoftware
{
    protected $table = 'features';
    protected $guarded = ['id'];
    protected $appends = ['full_name'];

    public function featureCosts()
    {
        return $this->hasMany(SoftwareCost::class);
    }

    public function softwares()
    {
        return $this->belongsToMany(Software::class, 'software_features');
    }

}
