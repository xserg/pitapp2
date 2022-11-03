<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\AmazonStorage;
use App\Models\Hardware\AzureStorage;
use App\Models\Hardware\GoogleStorage;
use App\Models\Hardware\IBMPVSStorage;
use App\Models\Project\Cloud\InstanceCategory;
use App\Models\Project\Cloud\OsSoftware;
use App\Models\Project\Cloud\PaymentOption;
use App\Models\Project\Region;


/**
 * A Cloud Provider
 * 
 * @package App\Models\Project
 * 
 * @property string $name
 */
class Provider extends Model
{
    const AWS = 'AWS';
    const AZURE = 'Azure';
    const GOOGLE = 'Google';
    const IBMPVS = 'IBM PVS';

    protected $table = 'providers';
    protected $guarded = ['id'];
    protected $appends = ['payment_options', 'cloud_storage_types', 'hardware_maintenance_per_year'];

    protected $paymentOptions = [];

    /**
     * Get provider's Regions
     */
    public function regions()
    {
        return $this->hasMany(Region::class, 'provider_owner_id');
    }
    
    /**
     * Get provider's Instance Categories
     */
    public function instanceCategories()
    {
        return $this->hasMany(InstanceCategory::class);
    }

    /**
     * Get provider's Softwares
     */
    public function osSoftwares()
    {
        return $this->hasMany(OsSoftware::class);
    }

    /**
     * Get provider's Payment Options
     */
    public function paymentOptions()
    {
        return $this->hasMany(PaymentOption::class);
    }

    /**
     * Get PaymentOption for a given cloud provider
     *
     * @param string $providerName The `Provider` name
     * @param int $providerId An optional `Provider` ID
     *
     * @return array|\App\Models\Project\Cloud\PaymentOption
     */
    static public function getPaymentOptions($providerName, $providerId = null)
    {
        if ($providerName === self::AWS ) {
            return AmazonServer::PAYMENT_OPTIONS;
        }

        if ($providerName === self::AZURE) {
            return PaymentOption::where('provider_id', $providerId)->get();
        }

        if ($providerName === self::GOOGLE) {
            return AmazonServer::GOOGLE_PAYMENT_OPTIONS;
        }

        if ($providerName === self::IBMPVS) {
            return AmazonServer::IBMPVS_PAYMENT_OPTIONS;
        }

        return [];
    }

    public function getHardwareMaintenancePerYearAttribute() {
        $prices = [
            self::AZURE => 12000,
            self::GOOGLE => 3000,
            self::IBMPVS => 0,
        ];

        return key_exists($this->name, $prices) ? $prices[$this->name] : 0;
    }

    public function getPaymentOptionsAttribute()
    {
        if ($this->isAzure()) { //* get (dynamic)payment option from DB
            return self::getPaymentOptions($this->name, $this->id);
        }
        
        return self::getPaymentOptions($this->name);
    }

    static public function getCloudStorageTypes($providerName)
    {
        if ($providerName === self::AWS ) {
            return AmazonStorage::STORAGE_TYPES;
        } 
        if ($providerName === self::AZURE) {
            return AzureStorage::STORAGE_TYPES;
        }
        if ($providerName === self::GOOGLE) {
            return GoogleStorage::STORAGE_TYPES;
        }
        if ($providerName === self::IBMPVS) {
            return IBMPVSStorage::STORAGE_TYPES;
        }
        return [];
    }

    public function getCloudStorageTypesAttribute()
    {
        return self::getCloudStorageTypes($this->name);
    }

    /**
     * @return bool
     */
    public function isAws()
    {
        return $this->name == self::AWS;
    }

    /**
     * @return bool
     */
    public function isAzure()
    {
        return $this->name == self::AZURE;
    }
    
    /**
     * @return bool
     */
    public function isGoogle()
    {
        return $this->name == self::GOOGLE;
    }
    
    /**
     * @return bool
     */
    public function isIBMPVS()
    {   
        return $this->name == self::IBMPVS;
    }
}
