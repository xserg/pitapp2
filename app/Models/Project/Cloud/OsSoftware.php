<?php

namespace App\Models\Project\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * A cloud provider's OS/Software
 */
class OsSoftware extends Model
{
    protected $table = 'os_softwares';

    protected $fillable = [
        'name',
        'os_type',
        'provider_id',
    ];

    /**
     * The Software's Provider
     */
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
