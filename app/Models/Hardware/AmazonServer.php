<?php namespace App\Models\Hardware;

use App\Models\Project\Cloud\PaymentOption;
use Illuminate\Database\Eloquent\Model;
use App\Models\Project\Provider;
use Illuminate\Support\Facades\Cache;

/**
 * Class AmazonServer
 * @package App\Models\Hardware
 * @property mixed $deployment_option
 * @property int $os_id
 * @property string $os_li_name
 * @property int $middleware_id
 * @property int $database_id
 * @property array $instances
 * @property array $middlewareInstances
 * @property array $databaseInstances
 * @property string $name
 * @property string $os_name
 * @property string $instance_type
 * @property string $pre_installed_sw
 * @property string $database_engine
 * @property string $database_edition
 * @property float $onDemandHourly
 * @property float $upfrontHourly
 * @property float $upfront3Hourly
 * @property float $upfront
 * @property string $physical_processor
 * @property float $clock_speed
 * @property float $calculatedUpfront
 * @property float $calculatedUpfrontPerYear
 * @property float $calculatedUpfrontTotal
 * @property float $upfrontSupportTiers
 * @property float $upfrontMonthlySupportTiers
 * @property float $upfrontSupport
 * @property float $upfrontMonthlySupport
 * @property float $upfrontBusinessSupport
 * @property float $upfrontTotalSupport
 * @property float $onDemandPerYear
 * @property float $onDemandSupportTiers
 * @property float $onDemandSupportPerMonth
 * @property float $onDemandSupportTotal
 * @property float $onDemandTotal
 * @property float $onDemandPerMonth
 * @property float $totalCostOnDemand
 * @property float $totalCostUpfront
 * @property float $calculatedUpfront3PerYear
 * @property float $calculatedUpfront3Total
 * @property float $totalCostUpfront3
 * @property mixed $database
 * @property int $cpm
 */
class AmazonServer extends Model
{
    const INSTANCE_TYPE_EC2 = 'EC2';
    const INSTANCE_TYPE_RDS = 'RDS';
    const INSTANCE_TYPE_AZURE = 'Azure';
    const INSTANCE_TYPE_GOOGLE = 'Google';
    const INSTANCE_TYPE_IBMPVS = 'IBMPVS';

    const DATABASE_ENGINE_AMAZON_AURORA = 'Amazon Aurora';
    const DATABASE_ENGINE_AURORA_MYSQL = 'Aurora MySQL';

    const EC2_GP_GB_MONTH_PRICE = .1;
    const EC2_GP_IOPS_PER_GB = 3;

    const GOOGLE_HDD_IOPS_PER_GB = 1.5;
    const GOOGLE_SSD_IOPS_PER_GB = 30;

    // If Hybrid is not available. Non hybrid ID map
    const HYBRID_DOWNGRADE_ID = [
        6 => 3,
        5 => 4
    ];

    const AZURE_ADS_PRISING_DOWNGRADE = [
        5 => 2, // Three Year Reserved With AHB => Pay As You Go With AHB
        4 => 1, // Three Year Reserved => Pay As You Go
        6 => 2, // One Year Reserved With AHB => Pay As You Go With AHB
        3 => 1, // One Year Reserved => Pay As You Go
        //2 => 1, // Pay As You Go With AHB => Pay As You Go
    ];

    /**
     * An array of various AWS services, their pricing sheets, and what regions we restrict to in our data imports.
     *
     * Filters are a key/array pair to match a column on the pricing sheet (key) with an array of accepted values that we use (array)
     *
     * @var array
     */
    const AWS_SERVICES = [
        [
            'name' => 'ec2',
            'sheet' => 'https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/AmazonEC2/current/index.csv',
            'filters' =>   [
                // 'Location' => ['US East (N. Virginia)', 'US East (Ohio)', 'US West (Northern California)', 'US West (N. California)', 'US West (Oregon)'],
                'Instance Family' => ['Compute optimized', 'General purpose', 'Memory optimized'],
                'Tenancy' => ['Shared'],
                'Current Generation' => ['Yes'],
                'Unit' => ['Hrs', 'Quantity', 'GB-Mo', 'IOs', 'IOPS-Mo']
            ]
        ],
        [
            'name' => 'rds',
            'sheet' => 'https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/AmazonRDS/current/index.csv',
            'filters' =>   [
                // 'Location' => ['US East (N. Virginia)', 'US East (Ohio)', 'US West (N. California)', 'US West (Oregon)'],
                'Current Generation' => ['Yes'],
                'Unit' => ['Hrs', 'Quantity', 'GB-Mo', 'IOs', 'IOPS-Mo']
            ]
        ]
    ];

    const PAYMENT_OPTIONS = [
        ['id' => 1 , 'name' => 'On-Demand (No Contract)', 'code' => 'onDemand', 'purchase_option' => 'All Upfront', 'offering_class' => '', 'lease_contract_length' => '', 'type' => ['rds', 'ebs']],
        ['id' => 2 , 'name' => '1 Yr No Upfront Reserved', 'code' => 'noUpfrontReserved1Yr', 'purchase_option' => 'No Upfront', 'offering_class' => 'standard', 'lease_contract_length' => '1yr', 'type' => ['rds', 'ebs']],
        ['id' => 3 , 'name' => '1 Yr Partial Upfront Reserved', 'code' => 'partialUpfrontReserved1Yr', 'purchase_option' => 'Partial Upfront', 'offering_class' => 'standard', 'lease_contract_length' => '1yr', 'type' => ['rds', 'ebs']],
        ['id' => 4 , 'name' => '1 Yr All Upfront Reserved', 'code' => 'allUpfrontReserved1Yr', 'purchase_option' => 'All Upfront', 'offering_class' => 'standard', 'lease_contract_length' => '1yr', 'type' => ['rds', 'ebs']],
        ['id' => 5 , 'name' => '3 Yr No Upfront Reserved', 'code' => 'noUpfrontReserved3Yr', 'purchase_option' => 'No Upfront', 'offering_class' => 'standard', 'lease_contract_length' => '3yr', 'type' => ['ebs']],
        ['id' => 6 , 'name' => '3 Yr Partial Upfront Reserved', 'code' => 'partialUpfrontReserved3Yr', 'purchase_option' => 'Partial Upfront', 'offering_class' => 'standard', 'lease_contract_length' => '3yr', 'type' => ['rds', 'ebs']],
        ['id' => 7 , 'name' => '3 Yr All Upfront Reserved', 'code' => 'allUpfrontReserved3Yr', 'purchase_option' => 'All Upfront', 'offering_class' => 'standard', 'lease_contract_length' => '3yr', 'type' => ['rds', 'ebs']],
        ['id' => 8 , 'name' => '1 Yr No Upfront Convertible', 'code' => 'noUpfrontConvertible1Yr', 'purchase_option' => 'No Upfront', 'offering_class' => 'Convertible', 'lease_contract_length' => '1yr', 'type' => ['ebs']],
        ['id' => 9 , 'name' => '1 Yr Partial Upfront Convertible', 'code' => 'partialUpfrontConvertible1Yr', 'purchase_option' => 'Partial Upfront', 'offering_class' => 'Convertible', 'lease_contract_length' => '1yr', 'type' => ['ebs']],
        ['id' => 10 , 'name' => '1 Yr All Upfront Convertible', 'code' => 'allUpfrontConvertible1Yr', 'purchase_option' => 'All Upfront', 'offering_class' => 'Convertible', 'lease_contract_length' => '1yr', 'type' => ['ebs']],
        ['id' => 11 , 'name' => '3 Yr No Upfront Convertible', 'code' => 'noUpfrontConvertible3Yr', 'purchase_option' => 'No Upfront', 'offering_class' => 'Convertible', 'lease_contract_length' => '3yr', 'type' => ['ebs']],
        ['id' => 12 , 'name' => '3 Yr Partial Upfront Convertible', 'code' => 'partialUpfrontConvertible3Yr', 'purchase_option' => 'Partial Upfront', 'offering_class' => 'Convertible', 'lease_contract_length' => '3yr', 'type' => ['ebs']],
        ['id' => 13 , 'name' => '3 Yr All Upfront Convertible', 'code' => 'allUpfrontConvertible3Yr', 'purchase_option' => 'All Upfront', 'offering_class' => 'Convertible', 'lease_contract_length' => '3yr', 'type' => ['ebs']],
    ];

    const AZURE_ONE_YEAR_RESERVED_NAME = '1 Year Reserved';
    const AZURE_ONE_YEAR_RESERVED_WITH_AHB_NAME = '1 Year Reserved With Azure Hybrid Benefit';
    const AZURE_PAYMENT_OPTIONS = [
        ['id' => 1 , 'name' => 'Pay As You Go', 'term_type' => 'Pay as you go', 'is_hybrid' => 0, 'lease_contract_length' => ''],
        ['id' => 2 , 'name' => 'Pay As You Go With Azure Hybrid Benefit', 'term_type' => 'Pay as you go', 'is_hybrid' => 1, 'lease_contract_length' => ''],
        ['id' => 3 , 'name' => self::AZURE_ONE_YEAR_RESERVED_NAME, 'term_type' => 'Reserved', 'is_hybrid' => 0, 'lease_contract_length' => '1'],
        ['id' => 6 , 'name' => self::AZURE_ONE_YEAR_RESERVED_WITH_AHB_NAME, 'term_type' => 'Reserved', 'is_hybrid' => 1,  'lease_contract_length' => '1'],
        ['id' => 4 , 'name' => '3 Year Reserved', 'term_type' => 'Reserved', 'is_hybrid' => 0, 'lease_contract_length' => '3'],
        ['id' => 5 , 'name' => '3 Year Reserved With Azure Hybrid Benefit', 'term_type' => 'Reserved', 'is_hybrid' => 1,  'lease_contract_length' => '3']
    ];

    const GOOGLE_PAYMENT_OPTIONS = [
        ['id' => 1 , 'name' => 'On demand', 'term_type' => 'Pay as you go', 'lease_contract_length' => ''],
        ['id' => 2 , 'name' => '1 year commitment', 'term_type' => 'Reserved', 'lease_contract_length' => '1'],
        ['id' => 3 , 'name' => '3 year commitment', 'term_type' => 'Reserved', 'lease_contract_length' => '3'],
    ];

    const IBMPVS_PAYMENT_OPTIONS = [
        ['id' => 1 , 'name' => 'On demand', 'term_type' => 'Pay as you go', 'lease_contract_length' => '']
    ];

    /**
     * @param integer $id
     * @return array
     */
    static function getAmazonPaymentOptionById($id)
    {
        foreach (self::PAYMENT_OPTIONS as $paymentOption) {
            if ($paymentOption['id'] === $id) return $paymentOption;
        }
    }

    /**
     * Returns a single Azure PaymentOption by id
     * 
     * @param int $id The PaymentOption ID
     * @param bool $isAds Whether AzureADS or Azure cloud payment option is returned
     * 
     * @return array
     */
    static function getAzurePaymentOptionById(int $id, bool $isAds = false)
    {
        $provider = Provider::where('name', 'Azure')->first();
        $query = PaymentOption::where('provider_id', $provider->id)->where('id', $id);

        if ($isAds) $query->where('provider_service_type', AzureAds::SERVICE_TYPE);

        $paymentOption = $query->first();
            
        //@note: this is only used to support old hard-coded payment options ID
        if (!$paymentOption) {
            foreach (self::AZURE_PAYMENT_OPTIONS as $oldPaymentOption) {
                if ($oldPaymentOption['id'] === $id) {
                    $paymentOption = PaymentOption::where('provider_id', $provider->id)
                        ->where('name', 'like', $oldPaymentOption['name'])
                        ->first();
    
                    break;
                }
            }
        }

        return $paymentOption ? $paymentOption->toArray() : [];
    }

    /**
     * Returns a Azure PaymentOption instance by name
     * 
     * @param string $name The PaymentOption's name
     * 
     * @return \App\Models\Project\Cloud\PaymentOption
     */
    static function getAzurePaymentOptionByName($name)
    {
        $provider = Provider::where('name', 'Azure')->first();
        $paymentOption = PaymentOption::where('provider_id', $provider->id)
            ->where('name', 'like', $name)
            ->first();

        return $paymentOption;
    }

    /**
     * @param integer $id
     * @return array
     */
    static function getGooglePaymentOptionById($id)
    {
        foreach (self::GOOGLE_PAYMENT_OPTIONS as $paymentOption) {
            if ($paymentOption['id'] === $id) return $paymentOption;
        }
    }

    /**
     * @param integer $id
     * @return array
     */
    static function getIBMPVSPaymentOptionById($id)
    {
        foreach (self::IBMPVS_PAYMENT_OPTIONS as $paymentOption) {
            if ($paymentOption['id'] === $id) return $paymentOption;
        }
    }

    public function providers() {
        return $this->belongsToMany(Provider::class);
    }

    /**
     * @return bool
     */
    public function isRds()
    {
        return $this->instance_type == self::INSTANCE_TYPE_RDS;
    }

    /**
     * @return bool
     */
    public function isEc2()
    {
        return $this->instance_type == self::INSTANCE_TYPE_EC2;
    }

    /**
     * @return bool
     */
    public function isAzure()
    {
        return $this->instance_type == self::INSTANCE_TYPE_AZURE;
    }

    /**
     * @return bool
     */
    public function isGoogle()
    {
        return $this->instance_type == self::INSTANCE_TYPE_GOOGLE;
    }

    /**
     * @return bool
     */
    public function isIBMPVS()
    {
        return $this->instance_type == self::INSTANCE_TYPE_IBMPVS;
    }

    /**
     * @return bool
     */
    public function databaseIsAmazonAurora()
    {
        return $this->database_engine == self::DATABASE_ENGINE_AMAZON_AURORA;
    }

    /**
     * @return array
     */
    public static function getInstanceCategories(): array
    {
        return Cache::remember('instance-categories', 60 * 60 * 24, function () {
            return collect([
                Provider::AWS => [
                    'type' => self::INSTANCE_TYPE_EC2,
                    'delimiter' => '"."'
                ],
                Provider::AZURE => [
                    'type' => self::INSTANCE_TYPE_AZURE,
                    'delimiter' => '1',
                ],
                Provider::GOOGLE => [
                    'type' => self::INSTANCE_TYPE_GOOGLE,
                    'delimiter' => '"-"',
                ]
            ])->map(function ($params) {
                return self::selectRaw(sprintf('substring_index(name, %s, 1) as type', $params['delimiter']))
                    ->where('instance_type', $params['type'])
                    ->groupBy('type')
                    ->orderBy('type')
                    ->get()
                    ->pluck('type');
            })->toArray();
        });
    }
}
