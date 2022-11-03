<?php

/**
 * Seeds the activities and states for Project related things
 *
 * @author jdobrowolski
 */

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Hardware\AmazonServer;
use App\Models\Project\Cloud\InstanceCategory;
use App\Models\Project\Cloud\OsSoftware;
use App\Models\Project\Cloud\PaymentOption;
use App\Models\Project\Provider;
use App\Models\Project\Region;
use App\Services\CsvImportService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * An Azure servers(`amazon_servers`) seeder
 * 
 * Populates Azure servers(`amazon_servers`) table from Azure pricing spreadsheet and
 * insert new  `Region`, `PaymentOption`, `InstanceCategory`, `OsSoftware` instances
 * for Azure `Provider` instance form provided CSV file.
 * 
 */
class SeedAzureServers extends Seeder {
    private $csvFile;

    private array $instanceCategories = [];

    private array $paymentOptions = [];

    private array $osSoftwares = [];

    private array $regions = [];


    function __construct($csvFile = __DIR__.'/../data/AzureServers.csv') {
        $this->csvFile = $csvFile;
    }

    public function run() {
        Eloquent::unguard();

        try {
            $this->printStatus(sprintf("Deleting old Azure pricing records...\n"));
            // DB::statement('DELETE FROM amazon_servers WHERE instance_type = "Azure"');
            $this->deleteOldServers();

            DB::transaction(function () {
                $azureProvider = Provider::where('name', 'Azure')->first();

                echo sprintf("Importing Azure pricing...\n");
                $this->import($this->csvFile, $azureProvider);
            });
            
            // DB::commit();
            Eloquent::reguard();

            return true;

        } catch (\Throwable $e) {
            // if (DB::transactionLevel() > 0) {
            //     DB::rollBack();
            // }

            throw $e;
        }
    }

    /**
     * Checks if payment option type is "hybrid"
     *
     * @param string $termType
     *
     * @return bool
     */
    private function isHybridPaymentOption($termType): bool
    {
        return strpos($termType, 'hybrid') !== false
            || strpos($termType, 'ahb') !== false;
    }

    /**
     * Returns payment option name from server's term_type
     *
     * @param string $termType
     *
     * @return string
     */
    private function getPaymentOptionName($termType): string
    {
        //* trim white space, uppercase words, extend abbr. "ahb", trim "(% savings)" 
        return trim(
            ucwords(
                str_replace(
                    'ahb',
                    'Azure Hybrid Benefit',
                    str_replace('(% savings)', '', $termType)
                )
            )
        );
    }

    /**
     * Separate payment option "Reserved" vs "Pay as you go"
     *
     * @param string $termType
     *
     * @return string
     */
    private function getPaymentOptionTermType($termType): string
    {
        return strpos(strtolower($termType), 'reserved')
            ? 'Reserved'
            : 'Pay as you go';
    }

    /**
     * Insert InstanceCategory for specified provider
     *
     * @param string $name The intance family/category name
     * @param Provider $provider Azure provider instance
     *
     * @return void
     */
    private function addInstanceCategory(string $name, Provider $provider)
    {
        InstanceCategory::firstOrCreate([
            'name' => $name,
            'provider_id' => $provider->id,
        ]);


    }

    /**
     * Insert Os/Software for specified provider
     *
     * @param string $name The software name
     * @param string $name The OS name
     * @param Provider $provider Azure provider instance
     *
     * @return void
     */
    private function addOsSoftware(string $name, string $osType, Provider $provider)
    {
        OsSoftware::firstOrCreate([
            'name' => $name,
            'os_type' => $osType,
            'provider_id' => $provider->id,
        ]);


    }

    /**
     * Add new Payment Option for specified provider
     *
     * - Adds a new record to `payment_options` table for a given
     * provider(only if it doesn't exist).
     * 
     * - Adds OS/Software from CSV line to payment option's available softwares field 
     * 
     * @param array $fields An array formated CSV line with payment option values
     * @param Provider $provider Azure provider instance
     *
     * @return void
     */
    private function addPaymentOption(array $fields, Provider $provider)
    {
        $termLength = $fields['term_length'] ? $fields['term_length'] : '';
        $name = $this->getPaymentOptionName($fields['term_type']);

        $paymentOption = PaymentOption::updateOrCreate(
            [ 'name' => $name, 'provider_id' => $provider->id ],
            [
                'term_type' => $this->getPaymentOptionTermType($fields['term_type']),
                'lease_contract_length' => $termLength,
                'is_hybrid' => $this->isHybridPaymentOption($fields['term_type']),
                'provider_service_type' => null,
            ]
        );

        if (!in_array($fields['software_type'], $paymentOption->available_os_softwares)) {
            $paymentOption->available_os_softwares = array_merge(
                $paymentOption->available_os_softwares,
                [$fields['software_type']]
            );

            $paymentOption->save();
        }

        /* keep reference to imported payment options' name */
        if (!in_array($name, $this->paymentOptions)) {
            $this->paymentOptions[] = $name;
        }
    }

    /**
     * Insert new region/location for specified provider
     *
     * @param string $name The region name
     * @param string $name The payment option name
     * @param Provider $provider Azure provider instance
     *
     * @return void
     */
    private function addRegion(string $name, string $paymentOption, Provider $provider)
    {
        $region = Region::firstOrCreate([
            'name' => $name,
            'provider_service_type' => null,
            'provider_owner_id' => $provider->id,
        ]);

        /* add PaymentOption name available for this region */
        $region->available_payment_options = $region->available_payment_options ?: [];

        if (!in_array($paymentOption, $region->available_payment_options)) {
            $region->available_payment_options = array_merge(
                $region->available_payment_options,
                [$paymentOption]
            );
        }

        $region->save();
    }

    /**
     * Delete Azure servers records
     *
     * @return void
     */
    private function deleteOldServers()
    {
        $serverCount = DB::table('amazon_servers')
            ->where('instance_type', 'Azure')
            ->count();

        //* delete entries by chunks of 1000
        while ($serverCount > 0) {
            DB::transaction(function () {
                DB::table('amazon_servers')
                    ->where('instance_type', 'Azure')
                    ->limit(1000)
                    ->delete();
            });

            $serverCount = DB::table('amazon_servers')
                ->where('instance_type', 'Azure')
                ->count();
        
            $this->printStatus(
                sprintf("echo -n '%s servers left\r'\n", $serverCount),
                true
            );
            sleep(5);
        }
    }

    /**
     * Import Azure servers spreadsheet
     *
     * Expected CSV columns name:
     * - term_type
     * - term_length
     * - operating_system
     * - software_type
     * - category
     * - instance
     * - vcpu
     * - ram
     * - temporary_storage
     * - price_per_hour
     * - physical_processor
     * - clock_speed
     * - currency
     * - region
     *
     * @param string $path Path to Azure ADS CSV file
     * @param \App\Models\Project\Provider $azureProvider An instance of Azure Provider type
     *
     * @return int The number of impoted records
     * 
     * @todo delete all regions not in the new CSV from the database
     *  - requires updating references to those regions in the `environments` table
     *  https://stackoverflow.com/questions/20869072/laravel-schema-ondelete-set-null
     */
    private function import(string $path, Provider $azureProvider): int
    {
        $importCount = 0;
        $rowCount = 0;
        $importService = new CsvImportService();

        $importService->parseCsv($path, function ($row, $counter) use ($azureProvider, &$importCount, &$rowCount) {
            $vcpu = $row['vcpu'] ?: null;
            
            AmazonServer::create([
                'name' => $row['instance'],
                'vcpu_qty' => $vcpu,
                'ram' => $row['ram'],
                'server_type' => $row['instance'],
                'os_name' => $row['operating_system'],
                'price_per_unit' => $row['price_per_hour'],
                'price_unit' => 'Hrs',
                'term_type' => $this->getPaymentOptionTermType($row['term_type']),
                'is_hybrid' => $this->isHybridPaymentOption($row['term_type']),
                'currency' => $row['currency'],
                'lease_contract_length' => $row['term_length'],
                'location' => $row['region'],
                'instance_family' => $row['category'],
                'storage' => $row['temporary_storage'],
                'software_type' => $row['software_type'],
                'instance_type' => 'Azure'
            ]);
        
            $this->addInstanceCategory($row['category'], $azureProvider);

            $this->addPaymentOption($row, $azureProvider);
            
            $this->addRegion(
                $row['region'],
                $this->getPaymentOptionName($row['term_type']),
                $azureProvider
            );

            $this->addOsSoftware(
                $row['software_type'],
                $row['operating_system'],
                $azureProvider
            );

            $importCount += 1;

            $rowCount = $counter;

            $this->printStatus(
                sprintf("echo -n '%s row processed\r'\n", $rowCount),
                true
            );
        });

        /*@todo delete instance categories not in the csv */
        // InstanceCategory::whereNotIn('name', $this->instanceCategories)->delete();

        /* delete payment options(term_type) not in the csv */
        PaymentOption::whereNotIn('name', $this->paymentOptions)->delete();

        /*@todo delete regions not in the csv */
        // Region::whereNotIn('name', $this->regions)->delete();

        /*@todo delete OS/Softwares not in the csv */
        // OsSoftware::whereNotIn('name', $this->osSoftwares)->delete();

        /* manually add "System Optimized" instance category to the system */
        $this->addInstanceCategory(
            InstanceCategory::CATEGORY_SYSTEM_OPTIMIZED,
            $azureProvider
        );

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
