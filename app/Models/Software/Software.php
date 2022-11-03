<?php

namespace App\Models\Software;

use Illuminate\Database\Eloquent\Model;
use App\Models\Software\SoftwareCost;
use App\Models\Software\SoftwareType;
use App\Models\Software\SoftwareFeature;
use App\Models\Software\Feature;
use App\Models\UserManagement\User;

/**
 * Class Software
 * @package App\Models\Software
 * @property int $id
 * @property SoftwareType $softwareType
 * @property SoftwareCost $softwareCost
 * @property Feature[] $features
 * @property float $support_cost
 * @property string $annual_cost_per
 * @property float $support_multiplier
 * @property float $nup
 * @property float $multiplier
 * @property string $cost_per
 * @property string $name
 * @property string $calculatedFormula
 * @property string $formula
 * @property float $license_cost
 * @property string $full_name
 * @property int $support_type
 * @property string $architecture
 */
class Software extends AbstractSoftware
{
    protected $table = 'softwares';
    protected $guarded = ['id'];
    protected $appends = ['full_name'];

    const GROUP_WINDOWS = 'Windows';
    const GROUP_RHEL = 'RHEL';
    const GROUP_VMWARE = 'VMware';
    const GROUP_NONE = 'No Group';

    /**
     * @var array
     */
    protected $_groupTypes = [
        self::GROUP_WINDOWS => '/(MS Windows|Microsoft Windows)/i',
        self::GROUP_RHEL => '/(RedHat|Red Hat)/i',
        self::GROUP_VMWARE => '/VMware/i'
    ];

    public function softwareCost()
    {
        return $this->hasMany(SoftwareCost::class);
    }

    public function softwareType()
    {
        return $this->belongsTo(SoftwareType::class, 'type');
    }

    public function softwareFeatures()
    {
        return $this->hasMany(SoftwareFeature::class);
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'software_features');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return string
     */
    public function hasGroup()
    {
        return $this->getGroup() != self::GROUP_NONE;
    }

    /**
     * @param $group
     * @return bool
     */
    public function isGroup($group)
    {
        return $this->getGroup() == $group;
    }

    /**
     * @return string
     */
    public function getGroup()
    {
        foreach($this->_groupTypes as $groupName => $groupRegex) {
            if (preg_match($groupRegex, $this->full_name)) {
                return $groupName;
            }
        }

        return self::GROUP_NONE;
    }
}
