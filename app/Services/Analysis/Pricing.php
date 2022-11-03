<?php
/**
 *
 */

namespace App\Services\Analysis;


use App\Services\Analysis\Pricing\Environment\Cloud\BandwidthCalculator;
use App\Services\Analysis\Pricing\Environment\Cloud\Instance\LowestCostCalculator;
use App\Services\Analysis\Pricing\Environment\Cloud\InstanceCalculator;
use App\Services\Analysis\Pricing\Environment\NetworkCalculator;
use App\Services\Analysis\Pricing\Environment\StorageCalculator;
use App\Services\Analysis\Pricing\Environment\Cloud\StorageCalculator as CloudStorageCalculator;
use App\Services\Analysis\Pricing\Environment\TotalsCalculator;
use App\Services\Analysis\Pricing\ServerConfiguration\HardwareCalculator;
use App\Services\Analysis\Pricing\ServerConfiguration\SoftwareCalculator;
use App\Services\Analysis\Pricing\ServerConfiguration\GroupSoftwareCalculator ;
use App\Services\Analysis\Pricing\Software\Calculator as GeneralSoftwareCalculator;
use App\Services\Analysis\Pricing\Software\CloudCalculator as CloudSoftwareCalculator;
use App\Services\Analysis\Pricing\Software\FeatureCalculator;

class Pricing
{
    /**
     * @return StorageCalculator
     */
    public function environmentStorageCalculator()
    {
        return resolve(StorageCalculator::class);
    }

    /**
     * @return NetworkCalculator
     */
    public function environmentNetworkCalculator()
    {
        return resolve(NetworkCalculator::class);
    }

    /**
     * @return TotalsCalculator
     */
    public function environmentTotalsCalculator()
    {
        return resolve(TotalsCalculator::class);
    }

    /**
     * @return BandwidthCalculator
     */
    public function cloudBandwidthCalculator()
    {
        return resolve(BandwidthCalculator::class);
    }

    /**
     * @return CloudStorageCalculator
     */
    public function cloudStorageCalculator()
    {
        return resolve(CloudStorageCalculator::class);
    }

    /**
     * @return InstanceCalculator
     */
    public function cloudInstanceCalculator()
    {
        return resolve(InstanceCalculator::class);
    }

    /**
     * @return LowestCostCalculator
     */
    public function cloudInstanceLowestCostCalculator()
    {
        return resolve(LowestCostCalculator::class);
    }

    /**
     * @return GeneralSoftwareCalculator
     */
    public function softwareCalculator()
    {
        return resolve(GeneralSoftwareCalculator::class);
    }

    /**
     * @return FeatureCalculator
     */
    public function softwareFeatureCalculator()
    {
        return resolve(FeatureCalculator::class);
    }

    /**
     * @return CloudSoftwareCalculator
     */
    public function cloudSoftwareCalculator()
    {
        return resolve(CloudSoftwareCalculator::class);
    }

    /**
     * @return HardwareCalculator
     */
    public function serverConfigurationHardwareCalculator()
    {
        return resolve(HardwareCalculator::class);
    }

    /**
     * @return SoftwareCalculator
     */
    public function serverConfigurationSoftwareCalculator()
    {
        return resolve(SoftwareCalculator::class);
    }

    /**
     * @return GroupSoftwareCalculator
     */
    public function serverConfigurationGroupSoftwareCalculator()
    {
        return resolve(GroupSoftwareCalculator::class);
    }
}