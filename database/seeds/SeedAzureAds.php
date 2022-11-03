<?php

use App\Models\Hardware\AzureAds;
use App\Models\Project\Cloud\AzureAdsServiceOption;
use App\Models\Project\Cloud\InstanceCategory;
use App\Models\Project\Cloud\PaymentOption;
use App\Models\Project\Environment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Project\Provider;
use App\Models\Project\Region;
use App\Services\CsvImportService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class SeedAzureAds extends Seeder
{
    const DEFAULT_AZURE_ADS_CSV = __DIR__ . '/../data/AzureAds.csv';

    private $csvFile;

    /**
     * @var array List of of `service_options` names in the csv 
     */
    private array $serviceOptions = [];

    /**
     * @var array List of of `category` names in the csv 
     */
    private array $instanceCategories = [];

    /**
     * @var array List of payment options(`term_type`) in the new csv
     */
    private array $paymentOptions = [];

    /**
     * @var array A mapping of all the regions in the new csv with their
     *  available payment options(`term_type`).
     */
    private array $regionsPaymentOptions = [];


    function __construct($csvFile = self::DEFAULT_AZURE_ADS_CSV) {
        $this->csvFile = $csvFile;
    }

    
    public function run()
    {
        DB::transaction(function () {
            $this->printStatus(sprintf("Deleting old Azure ADS pricing records...\n"));

            $this->deleteOldServers();
            
            $azureProvider = Provider::where('name', 'Azure')->first();

            $importCount = $this->import($this->csvFile, $azureProvider);
            
            /* rollback if no records were imported */
            if ($importCount === 0) {
                throw new \Exception('Azure ADS import: No new records were imported');
            }
        });
    }

    /**
     * Checks if payment option type is "hybrid"
     *
     * @param string $termType
     *
     * @return bool
     */
    private function isHybridPaymentOption(string $termType): bool
    {
        return str_contains($termType, 'hybrid') || str_contains($termType, 'ahb');
    }
    
    /**
     * Returns an InstanceCategory name based on the server's `instance_type` and category
     *
     * If `$instanceType` is zone redundant `General Purpose(Zone Redundant)` is
     * returned - else this method return `General Purpose(Locally Redundant)` for all
     * `General Purpose` instances.
     *
     * @param string $category The server category name
     * @param string $instanceType The server `instance_type` name
     *
     * @return string
     */
    private function getInstanceCategoryName(string $category, string $instanceType): string
    {
        if (!str_starts_with($category, AzureAds::CATEGORY_GENERAL_PURPOSE)) {
            return ucwords(trim($category));
        }

        return str_contains($instanceType, 'Zone Redundant')
            ? 'General Purpose (Zone Redundant)'
            : 'General Purpose (Locally Redundant)';
    }

    /**
     * Returns payment option name from server's term_type
     *
     * @param string $termType
     *
     * @return string
     */
    private function getPaymentOptionName(string $termType): string
    {
        $paymentName = ucwords($termType);
        
        //* azure hybrid benefit ... -> Pay As You Go With Azure Hybrid Benefit
        if (str_starts_with($paymentName, 'Azure Hybrid Benefit')) {
            $paymentName = PaymentOption::AZURE_PAY_AS_YOU_GO_AHB;
        }

        $paymentName = str_ireplace('(% savings)', '', $paymentName);
        $paymentName = str_ireplace('price', '', $paymentName);
        $paymentName = str_ireplace('capacity', '', $paymentName);
        $paymentName = str_ireplace('years', 'Year', $paymentName);
        $paymentName = trim($paymentName); // trim white space

        return $paymentName;
    }

    /**
     * Separate payment option "Reserved" vs "Pay as you go"
     *
     * @param string $termType
     *
     * @return string
     */
    private function getPaymentOptionTermType(string $termType): string
    {
        return str_contains(strtolower($termType), 'reserved')
            ? PaymentOption::TERMTYPE_RESERVED
            : PaymentOption::TERMTYPE_PAY_AS_YOU_GO;
    }
    
    /**
     * Update Azure SQL(ADS) available instance categories
     *
     * @param Provider $provider Azure provider instance
     * @param array $name An array of instance category names(General Purpose, Hyperscale, ...)
     *
     * @return void
     */
    private function updateInstanceCategories(Provider $provider, array $categories)
    {
        $importedCategories = [];

        foreach ($categories as $name => $paymentOptions) {
            $category = InstanceCategory::firstOrCreate([
                'name' => $name,
                'provider_id' => $provider->id,
                'provider_service_type' => AzureAds::SERVICE_TYPE,

            ]);
            $category->available_payment_options = array_values(
                array_unique($paymentOptions)
            );
            
            $category->save();

            $importedCategories[] = $name;
        }

        /* delete PaymentOptions not in the imported csv */
        InstanceCategory::where('provider_service_type', AzureAds::SERVICE_TYPE)
            ->whereNotIn('name', $importedCategories)
            ->delete();
    }

    /**
     * Updates Azure(ADS) list of service_options with their `database_type` and 
     *
     * @param array $serviceOptions
     *
     * @return void
     */
    public function updateServiceOptions(array $serviceOptions)
    {
        foreach ($serviceOptions as $name => $type) {
            $serviceType = AzureAdsServiceOption::updateOrCreate([
                'name' => ucwords(trim($name)),
                'database_type' => ucwords(trim($type['database_type'])),
            ]);
            $serviceType->available_categories = array_values(
                array_unique($type['categories'])
            );
            $serviceType->available_payment_options = array_values(
                array_unique($type['payment_options'])
            );

            $serviceType->save();
        }
    }

    /**
     * Updates Azure(ADS) available Payment Options
     *
     * @param Provider $provider Azure provider instance
     * @param array $paymentOptions An array with, `term_type`, `term_length` & `is_hybrid` fields
     *
     * @return void
     */
    private function updatePaymentOptions(Provider $provider, array $paymentOptions)
    {
        $importPaymentOptions = [];

        foreach ($paymentOptions as $termType => $paymentOption) {
            $termLength = $paymentOption['term_length'] ? $paymentOption['term_length'] : '';
            $name = $this->getPaymentOptionName($termType);
            
            PaymentOption::updateOrCreate(
                [
                    'name' => $name,
                    'provider_id' => $provider->id,
                    'provider_service_type' => AzureAds::SERVICE_TYPE,
                ],
                [
                    'term_type' => $paymentOption['term_type'],
                    'lease_contract_length' => $termLength,
                    'is_hybrid' => $paymentOption['is_hybrid'],
                ]
            );

            $importPaymentOptions[] = $name;
        }
        
        /* delete PaymentOptions not in the imported csv */
        PaymentOption::where('provider_service_type', AzureAds::SERVICE_TYPE)
            ->whereNotIn('name', $importPaymentOptions)
            ->delete();
    }

    /**
     * Updates available regions for Azure(ADS) provider
     *
     * Note: The list of available payment options for each region is also updated.
     * 
     * @param Provider $provider Azure provider instance
     * @param string $regions A mapping of regions with available payment options
     * 
     * @return void
     */
    private function updateRegions(Provider $provider, array $regions)
    {
        $importedRegions = [];
        
        foreach ($regions as $name => $paymentOptions) {
            $region = Region::updateOrCreate([
                'name' => $name,
                'provider_service_type' => AzureAds::SERVICE_TYPE,
                'provider_owner_id' => $provider->id,
            ]);
            $region->available_payment_options = array_values(
                array_unique($paymentOptions)
            );
            
            $region->save();

            $importedRegions[] = $name;
        }

        /* delete regions not in the imported csv */
        $regionsToDelete = Region::where('provider_service_type', AzureAds::SERVICE_TYPE)
            ->whereNotIn('name', $importedRegions)
            ->get();
        $defaultRegion = Region::where('provider_service_type', AzureAds::SERVICE_TYPE)
            ->where('name', 'West US 2')
            // ->orWhere('name', 'Us West 2')
            ->first();

        foreach ($regionsToDelete as $region) {
            /* update all environments referencing the region to
             default region(US West 2) and delete the region
            */
            Environment::where('region_id', $region->id)
                ->update(['region_id' => $defaultRegion->id]);

            $region->delete();
        }
    }

    /**
     * Delete Azure ADS records
     *
     * @return void
     */
    private function deleteOldServers()
    {
        DB::table('azure_ads')->truncate();
    }
    
    /**
     * Import Azure ADS spreadsheet
     *
     * Expected CSV columns name:
     * - term_type
     * - term_length
     * - service_options
     * - database_type
     * - category
     * - instance_type
     * - vcpu
     * - memory
     * - max_db_per_pool
     * - included_storage
     * - price_per_hour
     * - physical_processor
     * - clock_speed
     * - region
     * - compute_tier
     *
     * @param string $path Path to Azure ADS CSV file
     * @param \App\Models\Project\Provider $azureProvider An instance of Azure Provider type
     *
     * @return int The number of impoted records
     */
    protected function import(string $path, Provider $azureProvider): int
    {
        $importCount = 0;
        $rowCount = 0;
        $csvImportService = new CsvImportService();

        $csvImportService->parseCsv($path, function ($row, $counter) use (&$importCount, &$rowCount) {
            $maxDbPerPool = !empty($row['max_db_per_pool']) ? $row['max_db_per_pool'] : null;
            $ram = !empty($row['memory']) ? $row['memory'] : null;
            $vcpu = !empty($row['vcpu']) ? $row['vcpu'] : null;

            $category = $this->getInstanceCategoryName(
                $row['category'],
                $row['instance_type']
            );
            $paymentOption = $this->getPaymentOptionName($row['term_type']);
            $termType = $this->getPaymentOptionTermType($row['term_type']);
            $isHybrid = $this->isHybridPaymentOption($row['term_type']);

            if ($termType) {
                AzureAds::create([
                    'term_type' => $termType,
                    'term_length' => $row['term_length'],
                    'service_type' => ucwords(trim($row['service_options'])),
                    'database_type' => ucwords(trim($row['database_type'])),
                    'category' => $category,
                    'name' => $row['instance_type'],
                    'vcpu_qty' => $vcpu,
                    'ram' => $ram,
                    'max_db_per_pool' => $maxDbPerPool,
                    'included_storage' => $row['included_storage'],
                    'price_per_unit' => $row['price_per_hour'],
                    'physical_processor' => $row['physical_processor'],
                    'clock_speed' => $row['clock_speed'],
                    'region' => $row['region'],
                    'tier' => $row['compute_tier'],
                    'is_hybrid' => $isHybrid,
                ]);

                /* store reference to `regions`(w/ payment options) in the CSV */
                $this->regionsPaymentOptions[$row['region']][] = $paymentOption;

                /* keep reference to imported payment options' name */
                $this->paymentOptions[$row['term_type']] = [
                    'term_type' => $termType,
                    'term_length' => $row['term_length'],
                    'is_hybrid' => $isHybrid,
                ];
            
                /* keep reference to imported payment options' name */
                $this->instanceCategories[$category][] = $paymentOption;
                
                /* keep reference to imported database service types */
                if (!isset($this->serviceOptions[$row['service_options']])) {
                    $this->serviceOptions[$row['service_options']] = [
                        'database_type' => $row['database_type'],
                    ];
                }

                $this->serviceOptions[$row['service_options']]['categories'][] = $category;
                $this->serviceOptions[$row['service_options']]['payment_options'][] = $paymentOption;

                $importCount += 1;
            }

            $rowCount = $counter;

            $this->printStatus(
                sprintf("echo -n '%s row processed\r'\n", $rowCount),
                true
            );
        });

        $this->updateInstanceCategories($azureProvider, $this->instanceCategories);
        $this->updateServiceOptions($this->serviceOptions);
        $this->updatePaymentOptions($azureProvider, $this->paymentOptions);
        $this->updateRegions($azureProvider, $this->regionsPaymentOptions);

        if ($importCount > 0) {
            $this->printStatus(
                sprintf(
                    "\n%s/%s were imported successfully.\n\n",
                    $importCount,
                    $rowCount
                )
            );
        }

        return $importCount;
    }

    /**
     * Print import status to stdout(local) or to log channel(prod, staging,..)
     *
     * @param string $message The status message
     * @param bool $isProgress Display a progress status message if `true` 
     *
     * @return void
     */
    private function printStatus(string $message, bool $isProgress = false): void
    {
        if (App::environment('local', 'testing')) {
            if ($isProgress) {
                system($message);
                return;
            }

            echo $message;

        } else {
            Log::info($message);
        }
    }
}
