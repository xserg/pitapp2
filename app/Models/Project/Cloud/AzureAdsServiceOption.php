<?php

namespace App\Models\Project\Cloud;

use App\Models\Project\Provider;
use Illuminate\Database\Eloquent\Model;

/**
 * AzureAds's database service type
 */
class AzureAdsServiceOption extends Model
{
    protected $table = 'azure_ads_service_options';
    
    protected $fillable = [
        'name',
        'database_type',
        'available_categories',
        'available_payment_options',
        'provider_id',
    ];
    
    protected $attributes = [
        'available_categories' => '[]',
        'available_payment_options' => '[]',
    ];
    
    protected $casts = [
        'available_categories' => 'array',
        'available_payment_options' => 'array',
    ];

    /**
     * The Payment Option's Provider
     */
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
