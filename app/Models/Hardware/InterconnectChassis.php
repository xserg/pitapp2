<?php namespace App\Models\Hardware;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class InterconnectChassis extends Model{

    /**
     * @var string
     */
    protected $table = 'hardware_interconnect_chassis';

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $appends = array('uuid');

    /**
     * @var string
     */
    protected $uuid = '';

    public function model() {
        return $this->belongsTo(InterconnectChassisModel::class);
    }

    public function manufacturer() {
        return $this->belongsTo(Manufacturer::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }

    /**
     * @return string
     */
    public function getUuidAttribute()
    {
        return $this->getUuid();
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     * @return InterconnectChassis
     */
    public function setUuid(string $uuid)
    {
        $this->uuid = $uuid;
        return $this;
    }
}
