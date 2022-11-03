<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Environment\Cloud;


use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\AmazonStorage;
use App\Models\Hardware\AzureStorage;
use App\Models\Hardware\GoogleStorage;
use App\Models\Hardware\IBMPVSStorage;
use App\Models\Hardware\AzureAds;
use App\Models\Project\Environment;

class StorageCalculator
{
    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        if ($this->cloudHelper()->environmentIsAzure($targetEnvironment)) {
            $this->calculateAzureCosts($targetEnvironment, $existingEnvironment);
        } else if ($this->cloudHelper()->environmentIsGoogle($targetEnvironment)) {
            $this->calculateGoogleCosts($targetEnvironment, $existingEnvironment);
        } else if ($this->cloudHelper()->environmentIsIBMPVS($targetEnvironment)) {
            $this->calculateIBMPVSCosts($targetEnvironment, $existingEnvironment);
        } else {
            $this->calculateAwsCosts($targetEnvironment, $existingEnvironment);
        }

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateAwsCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $discountRate = $targetEnvironment->discount_rate ?  $targetEnvironment->discount_rate : 0;
        $targetEnvironment->discount = $targetEnvironment->gbMonthPrice * ($discountRate/100);

        if ($targetEnvironment->isAwsProvisionedIops()) {
            $targetEnvironment->totalIops = $targetEnvironment->provisioned_iops;
            $targetEnvironment->monthly_storage_purchase = ($targetEnvironment->iopsMonthPrice * $targetEnvironment->provisioned_iops + ($targetEnvironment->gbMonthPrice - $targetEnvironment->discount) * $targetEnvironment->total_storage * 1000) * ($targetEnvironment->max_utilization / 100.0);
            $targetEnvironment->storage_maintenance_tiered = $this->cloudHelper()->tieredCost($targetEnvironment->monthly_storage_purchase);
            $targetEnvironment->monthly_storage_maintenance = round(collect($targetEnvironment->storage_maintenance_tiered)->sum());
            $targetEnvironment->storage_maintenance = $targetEnvironment->monthly_storage_maintenance * 12;
            $targetEnvironment->total_storage_maintenance = $targetEnvironment->storage_maintenance * $targetEnvironment->project->support_years;
            $targetEnvironment->storage_purchase_price = $targetEnvironment->monthly_storage_purchase * 12 * $targetEnvironment->project->support_years;
            $targetEnvironment->initial_monthly_iops_price = 0;
            $targetEnvironment->initial_iops_purchase_price = 0;
            $targetEnvironment->iopsSurplus = 0;
            $targetEnvironment->iopsDeficit = 0;
            $targetEnvironment->monthly_iops_purchase = 0;
            $targetEnvironment->iops_purchase_price = 0;
        } else {
            $ioTotalPrice = 0;
            if ($targetEnvironment->ioRatePrice && $targetEnvironment->io_rate) {
                $ioTotalPrice = $targetEnvironment->ioRatePrice * $targetEnvironment->io_rate;
            }
            $gbTotal = $targetEnvironment->total_storage * 1000;
             
            $targetEnvironment->monthPrice = $targetEnvironment->gbMonthPrice;
            $targetEnvironment->monthly_storage_purchase = ($targetEnvironment->gbMonthPrice - $targetEnvironment->discount) * $gbTotal + $ioTotalPrice;
            $targetEnvironment->storage_purchase_price = $targetEnvironment->monthly_storage_purchase * 12 * $targetEnvironment->project->support_years;

            $targetEnvironment->totalIops = $gbTotal * $targetEnvironment->iops_per_gb;
            if (intval($existingEnvironment->iops) && $targetEnvironment->totalIops >= $existingEnvironment->iops) {
                $targetEnvironment->iopsSurplus = $targetEnvironment->totalIops - $existingEnvironment->iops;
                $targetEnvironment->iopsDeficit = 0;
                $targetEnvironment->iopsGbNeeded = 0 * ($targetEnvironment->max_utilization / 100.0);
                $targetEnvironment->monthly_iops_purchase = 0;
                $targetEnvironment->iops_purchase_price = 0;
            } elseif (intval($existingEnvironment->iops)) {
                $targetEnvironment->iopsSurplus = 0;
                $targetEnvironment->iopsDeficit = $existingEnvironment->iops - $targetEnvironment->totalIops;
                $targetEnvironment->iopsGbNeeded = ceil($targetEnvironment->iopsDeficit / 3);
                $targetEnvironment->monthly_iops_purchase = $targetEnvironment->iopsGbNeeded * .1 * ($targetEnvironment->max_utilization / 100.0);
                $targetEnvironment->iops_purchase_price = $targetEnvironment->monthly_iops_purchase * 12 * $targetEnvironment->project->support_years;
            } else {
                $targetEnvironment->iopsSurplus = 0;
                $targetEnvironment->iopsDeficit = 0;
                $targetEnvironment->iopsGbNeeded = 0;
                $targetEnvironment->monthly_iops_purchase = 0;
                $targetEnvironment->iops_purchase_price = 0.00;
            }

            $targetEnvironment->storage_maintenance_tiered = $this->cloudHelper()->tieredCost($targetEnvironment->monthly_storage_purchase + $targetEnvironment->monthly_iops_purchase);
            $targetEnvironment->monthly_storage_maintenance = round(collect($targetEnvironment->storage_maintenance_tiered)->sum());
            $targetEnvironment->storage_maintenance = $targetEnvironment->monthly_storage_maintenance * 12;
            $targetEnvironment->total_storage_maintenance = $targetEnvironment->storage_maintenance * $targetEnvironment->project->support_years;
        }

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateAzureCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $discountRate = $targetEnvironment->discount_rate ?  $targetEnvironment->discount_rate : 0;
        $targetEnvironment->discount = $targetEnvironment->gbMonthPrice * ($discountRate/100);

        $adsInstance = $this->cloudHelper()->getEnvironmentAdsInstance($targetEnvironment);

        if ($adsInstance) {
            $gbTotal = $targetEnvironment->total_storage * 1024;

            $additionalStorageGb = $gbTotal;
            $targetEnvironment->ads_free_storage = 0;

            if ($adsInstance->service_type == AzureAds::SERVICE_TYPE_MANAGED_INSTANCE) {
                $additionalStorageGb = max(0, $additionalStorageGb - AzureAds::STORAGE_FREE_GB_MANAGED_INSTANCE);
                $targetEnvironment->ads_free_storage = AzureAds::STORAGE_FREE_GB_MANAGED_INSTANCE;
                $targetEnvironment->ads_total_storage = $gbTotal;
                $targetEnvironment->ads_storage_surplus = round(max(0, AzureAds::STORAGE_FREE_GB_MANAGED_INSTANCE - $gbTotal));
            }

            $targetEnvironment->ads_additional_storage = $additionalStorageGb;
            $targetEnvironment->monthPrice = $this->cloudHelper()->getAdsStorageCostPerUnit($adsInstance);
            $targetEnvironment->monthly_storage_purchase = $targetEnvironment->monthPrice * $targetEnvironment->ads_additional_storage * ($targetEnvironment->max_utilization / 100.00);
            $targetEnvironment->storage_purchase_price = $targetEnvironment->monthly_storage_purchase * 12 * $targetEnvironment->project->support_years;
        } else {
            $targetEnvironment->iops_purchase_price = 0;
            $gbTotal = $targetEnvironment->total_storage * 1024; // Total storage amount in GB
            $targetEnvironment->storageDisks = round($gbTotal / ($targetEnvironment->diskSize * 1024), 2);
            $targetEnvironment->monthPrice = round($targetEnvironment->gbMonthPrice, 2);
            $targetEnvironment->monthly_storage_purchase = ($targetEnvironment->gbMonthPrice - $targetEnvironment->discount) * $targetEnvironment->storageDisks * ($targetEnvironment->max_utilization / 100.0);
            $targetEnvironment->storage_purchase_price = $targetEnvironment->monthly_storage_purchase * 12 * $targetEnvironment->project->support_years;
            $targetEnvironment->total_storage_maintenance = 0;
            $targetEnvironment->totalIops = $targetEnvironment->iopsPerDisk  * $targetEnvironment->storageDisks;

            if (intval($existingEnvironment->iops) && $targetEnvironment->totalIops >= $existingEnvironment->iops) {
                $targetEnvironment->iopsSurplus = $targetEnvironment->totalIops - $existingEnvironment->iops;
                $targetEnvironment->iopsDeficit = 0;
                $targetEnvironment->iopsGbNeeded = 0;
                $targetEnvironment->monthly_iops_purchase = 0;
                $targetEnvironment->iops_purchase_price = 0;
            } elseif (intval($existingEnvironment->iops)) {
                $targetEnvironment->iopsSurplus = 0;
                $targetEnvironment->iopsDeficit = max($existingEnvironment->iops - $targetEnvironment->totalIops, 0);
                $targetEnvironment->iopsDisksNeeded = round($targetEnvironment->iopsDeficit / $targetEnvironment->iopsPerDisk, 2);
                $targetEnvironment->monthly_iops_purchase = $targetEnvironment->iopsDisksNeeded * $targetEnvironment->monthPrice * ($targetEnvironment->max_utilization / 100.0);
                $targetEnvironment->iops_purchase_price = $targetEnvironment->monthly_iops_purchase * 12 * $targetEnvironment->project->support_years;
            } else {
                $targetEnvironment->iopsSurplus = 0;
                $targetEnvironment->iopsDeficit = 0;
                $targetEnvironment->iopsGbNeeded = 0;
                $targetEnvironment->monthly_iops_purchase = 0;
                $targetEnvironment->iops_purchase_price = 0.00;
            }
        }

        return $this;
    }
    
    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateGoogleCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
      $discountRate = $targetEnvironment->discount_rate ?  $targetEnvironment->discount_rate : 0;
      $targetEnvironment->discount = $targetEnvironment->gbMonthPrice * ($discountRate/100);
      
      $gbTotal = $targetEnvironment->total_storage * 1000;
      
      $targetEnvironment->monthPrice = $targetEnvironment->gbMonthPrice;
      $targetEnvironment->monthly_storage_purchase = ($targetEnvironment->gbMonthPrice - $targetEnvironment->discount) * $gbTotal;
      $targetEnvironment->storage_purchase_price = $targetEnvironment->monthly_storage_purchase * 12 * $targetEnvironment->project->support_years;

      $targetEnvironment->totalIops = $gbTotal * $targetEnvironment->iops_per_gb;
      if (intval($existingEnvironment->iops) && $targetEnvironment->totalIops >= $existingEnvironment->iops) {
          $targetEnvironment->iopsSurplus = $targetEnvironment->totalIops - $existingEnvironment->iops;
          $targetEnvironment->iopsDeficit = 0;
          $targetEnvironment->iopsGbNeeded = 0 * ($targetEnvironment->max_utilization / 100.0);
          $targetEnvironment->monthly_iops_purchase = 0;
          $targetEnvironment->iops_purchase_price = 0;
      } elseif (intval($existingEnvironment->iops)) {
          $targetEnvironment->iopsSurplus = 0;
          $targetEnvironment->iopsDeficit = $existingEnvironment->iops - $targetEnvironment->totalIops;
          $targetEnvironment->iopsGbNeeded = ceil($targetEnvironment->iopsDeficit / 3);
          $targetEnvironment->monthly_iops_purchase = $targetEnvironment->iopsGbNeeded * .1 * ($targetEnvironment->max_utilization / 100.0);
          $targetEnvironment->iops_purchase_price = $targetEnvironment->monthly_iops_purchase * 12 * $targetEnvironment->project->support_years;
      } else {
          $targetEnvironment->iopsSurplus = 0;
          $targetEnvironment->iopsDeficit = 0;
          $targetEnvironment->iopsGbNeeded = 0;
          $targetEnvironment->monthly_iops_purchase = 0;
          $targetEnvironment->iops_purchase_price = 0.00;
      }

      $targetEnvironment->total_storage_maintenance = 0;

      return $this;
    }
    
    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateIBMPVSCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
      $discountRate = $targetEnvironment->discount_rate ?  $targetEnvironment->discount_rate : 0;
      $targetEnvironment->discount = $targetEnvironment->gbMonthPrice * ($discountRate/100);
      
      $gbTotal = $targetEnvironment->total_storage * 1000;
      
      $targetEnvironment->monthPrice = $targetEnvironment->gbMonthPrice;
      $targetEnvironment->monthly_storage_purchase = ($targetEnvironment->gbMonthPrice - $targetEnvironment->discount) * $gbTotal;
      $targetEnvironment->storage_purchase_price = $targetEnvironment->monthly_storage_purchase * 12 * $targetEnvironment->project->support_years;
      
      $targetEnvironment->total_storage_maintenance = 0;

      return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function determineMonthlyStorageCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        if (count($targetEnvironment->analysis->consolidations) <= 0 || count($targetEnvironment->analysis->consolidations[0]->targets) <= 0) {
            return $this;
        }

        $isRds = $this->cloudHelper()->environmentHasRds($targetEnvironment);
        $isAds = $this->cloudHelper()->environmentHasAds($targetEnvironment);
        $isAzure = !$isAds && $targetEnvironment->isAzure();
        $isEc2 = !$isRds && $targetEnvironment->isAws();
        $isGoogle = !$isAds && $targetEnvironment->isGoogle();
        $isIBMPVS = !$isAds && $targetEnvironment->isIBMPVS();

        if ($isRds) {
            $this->determineRdsMonthlyStorageCosts($targetEnvironment);
        } else if ($isEc2) {
            $this->determineEc2MonthlyStorageCosts($targetEnvironment);
        } else if ($isAds) {
            $this->determineAdsMonthlyStorageCosts($targetEnvironment);
        } else if ($isAzure) {
            $this->determineAzureMonthlyStorageCosts($targetEnvironment, $existingEnvironment);
        } else if ($isGoogle) {
            $this->determineGoogleMonthlyStorageCosts($targetEnvironment, $existingEnvironment);
        } else if ($isIBMPVS) {
            $this->determineIBMPVSMonthlyStorageCosts($targetEnvironment, $existingEnvironment);
        }

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @return $this
     */
    public function determineRdsMonthlyStorageCosts(Environment $targetEnvironment)
    {
        $rdsDeploymentOption = $this->cloudHelper()->getEnvironmentRdsDeploymentOption($targetEnvironment);
        //We strip out everything from the space onwards. This is to change the Multi-AZ (SQL Server Mirror)
        //entries to Multi-AZ.
        $spaceInd = strpos($rdsDeploymentOption, ' ');
        if($spaceInd !== false) {
            $rdsDeploymentOption = substr($rdsDeploymentOption, 0, $spaceInd);
        }

        $targetEnvironment->storageType = AmazonStorage::getAmazonStorageTypeById($targetEnvironment->cloud_storage_type)['name'];
        $targetEnvironment->volumeType = AmazonStorage::getAmazonStorageTypeById($targetEnvironment->cloud_storage_type)['volume_type'];
        $gbMonthPrice = AmazonServer::where('volume_type', '=', $targetEnvironment->volumeType)
            ->where('location', '=', trim($targetEnvironment->region['name']))
            ->where('deployment_option', 'like', $rdsDeploymentOption . '%')
            ->first();

        if ($targetEnvironment->volumeType === "General Purpose-Aurora" || $targetEnvironment->volumeType === "Magnetic") {
            $ioRatePrice = AmazonServer::where('instance_type', '=', 'RDS')
                ->where('location', '=', trim($targetEnvironment->region['name']))
                ->where('price_unit', '=', 'IOs')
                ->where('database_engine', '=', 'Any');
            if ($targetEnvironment->volumeType === "Magnetic") {
                $ioRatePrice->where('volume_type', '=', 'Magnetic');
            }
            $ioRatePrice = $ioRatePrice->first();
            $targetEnvironment->ioRatePrice = !empty($ioRatePrice) ? $ioRatePrice->price_per_unit : 0;
        }

        if ($targetEnvironment->volumeType === "Provisioned IOPS") {
            $iopsMonthPrice = AmazonServer::where('instance_type', '=', 'RDS')
                ->where('location', '=', trim($targetEnvironment->region['name']))
                ->where('price_unit', '=', 'IOPS-Mo')
                ->where('deployment_option', 'like', $rdsDeploymentOption . '%')
                ->first();

            $targetEnvironment->iopsMonthPrice = !empty($iopsMonthPrice) ? $iopsMonthPrice->price_per_unit : 0;
        }
        $targetEnvironment->gbMonthPrice = !empty($gbMonthPrice) ? $gbMonthPrice->price_per_unit : 0;
        $targetEnvironment->iops_per_gb = !empty($gbMonthPrice) ? $gbMonthPrice->iops_per_gigabyte : 0;
        
        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @return $this
     */
    public function determineEc2MonthlyStorageCosts(Environment $targetEnvironment)
    {
        $targetEnvironment->storageType = AmazonStorage::getAmazonStorageTypeById($targetEnvironment->cloud_storage_type)['name'];
        $query = AmazonStorage::where('storage_type', '=', $targetEnvironment->storageType)
            ->where('region', '=', $targetEnvironment->region['name'])->first();

        //* Storage options doesn't exist for all regions yet(not in DB at least)
        //* If storage returns null, fallback to 'US East (N. Virginia)'
        if (!$query) {
            $query = AmazonStorage::where('storage_type', '=', $targetEnvironment->storageType)
                ->where('region', '=', 'US East (N. Virginia)')
                ->first();
        }

        $targetEnvironment->gbMonthPrice = $query->monthly_price_per_gb;
        $targetEnvironment->iopsMonthPrice = $query->monthly_price_per_iops;
        $targetEnvironment->iops_per_gb = AmazonServer::EC2_GP_IOPS_PER_GB;

        return $this;
    }

    /**
     * Ads currently has not price-per-unit
     * (This is technically not true, it's more that the price-per-unit is based solely on instance category type, not specific instance)
     * @param Environment $targetEnvironment
     * @return $this
     */
    public function determineAdsMonthlyStorageCosts(Environment $targetEnvironment)
    {
        return $this;
    }

    /**
     * Azure currently has no price-per-unit
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function determineAzureMonthlyStorageCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {  
        $targetEnvironment->storageType = AzureStorage::getAzureStorageTypeById($targetEnvironment->cloud_storage_type)['name'];
        $existingStorage = $existingEnvironment->total_storage?: 0;

        $query = AzureStorage::where('storage_type', '=', $targetEnvironment->storageType)
            ->where('region', '=', $targetEnvironment->region['name']);

        if ($targetEnvironment->storageType != 'Ultra SSD Managed Disks') {
            // Lowest storage disk size
            if ($existingStorage > 0.1280) {
                $query = $query->where('disk_size', '<=', $existingStorage);
            }
            $query = $query->orderBy('disk_size', 'desc')->first();
        } else {
            $query = $query->orderBy('monthly_price_per_gb', 'asc')->first();
        }

        if (!$query) {//* If storage returns null, fallback to 'West US 2'
            $query = AzureStorage::where('storage_type', '=', $targetEnvironment->storageType)
                ->where('region', '=', 'West US 2')
                ->first();
        }

        $targetEnvironment->gbMonthPrice = $query->monthly_price != 0 ? $query->monthly_price : $query->monthly_price_per_gb * ($targetEnvironment->total_storage * 1024);   
        $targetEnvironment->diskSize = $query->disk_size != 0 ? $query->disk_size : $existingStorage; // in TB
        $targetEnvironment->iopsPerDisk = $query->iops_per_disk != 0 ? $query->iops_per_disk : $existingEnvironment->iops;
        return $this;
    }
    
    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function determineGoogleMonthlyStorageCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {  
        $targetEnvironment->storageType = GoogleStorage::getGoogleStorageTypeById($targetEnvironment->cloud_storage_type)['name'];
        $query = GoogleStorage::where('storage_type', '=', $targetEnvironment->storageType)
            ->where('region', '=', $targetEnvironment->region['name'])->first();

        if (!$query) {//* If storage returns null, fallback to 'us-west2'
            $query = GoogleStorage::where('storage_type', '=', $targetEnvironment->storageType)
                ->where('region', '=', 'us-west2')
                ->first();
        }
            
        $targetEnvironment->gbMonthPrice = $query->monthly_price_per_gb;
        $targetEnvironment->iopsMonthPrice = 0;
        $targetEnvironment->iops_per_gb = preg_match('/ssd/i', $targetEnvironment->storageType) ? AmazonServer::GOOGLE_SSD_IOPS_PER_GB : AmazonServer::GOOGLE_HDD_IOPS_PER_GB;

        return $this;
    }
    
    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function determineIBMPVSMonthlyStorageCosts(Environment $targetEnvironment, Environment $existingEnvironment)
    {  
      $targetEnvironment->storageType = IBMPVSStorage::getIBMPVSStorageTypeById($targetEnvironment->cloud_storage_type)['name'];
      $query = IBMPVSStorage::where('storage_type', '=', $targetEnvironment->storageType)
          ->where('region', '=', $targetEnvironment->region['name'])->first();

      $targetEnvironment->gbMonthPrice = $query->monthly_price_per_gb;
      $targetEnvironment->iopsMonthPrice = 0;
      return $this;
    }

    /**
     * @return \App\Helpers\Analysis\Cloud
     */
    public function cloudHelper()
    {
        return resolve(\App\Helpers\Analysis\Cloud::class);
    }
}