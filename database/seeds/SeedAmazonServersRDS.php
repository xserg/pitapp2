<?php

/**
 * Seeds the activities and states for Project related things
 *
 * @author jdobrowolski
 */

use Illuminate\Database\Seeder;
use App\Models\Hardware\AmazonServer;
use App\Models\Project\Provider;
use App\Models\Project\Region;

class SeedAmazonServersRDS extends Seeder {

    private $csvFile;

    function __construct($csvFile = __DIR__.'/../data/AmazonServersRDS.csv') {
        $this->csvFile = $csvFile;
    }

    public function run() {
        Eloquent::unguard();
        $this->seedFile($this->csvFile);
        Eloquent::reguard();
    }

    public function seedFile($filename) {
        $colnames = true;
        
        if(($handle = fopen($filename, 'r')) !== FALSE) {
            $colNameIndex = [];
            $parsedRowCount = 0;
            $awsProvider = Provider::where('name', 'AWS')->first();

            while(($data = fgetcsv($handle)) !== FALSE) {
                if($colnames) {
                    $colnames = false;

                    foreach($data as $index=>$colName) {
                        $colNameIndex[$colName] = $index;
                    }

                } else {
                    //Check the processor name until we hit a number or space. This is the processor
                    //type.
                    $os = '';
                    $instanceName = $this->getData($data, $colNameIndex, 'Instance Type');
                    $location = $this->getData($data, $colNameIndex, 'Location');
                    $pricePerUnit = $this->getData($data, $colNameIndex, 'PricePerUnit');
                    $serviceCode = $this->getData($data, $colNameIndex, 'serviceCode');
                    $unit = $this->getData($data, $colNameIndex, 'Unit');
                    $memory = $this->getData($data, $colNameIndex, 'Memory') ?: '';
                    $vCpu = $this->getData($data, $colNameIndex, 'vCPU') ?: 0;
                    $purchaseOption = $this->getData($data, $colNameIndex, 'PurchaseOption')
                        ?: 'No Upfront';

                    //The RDS instances are prefixed with 'db.'
                    //The first section of the instance name should be its type (M4/R4)
                    //* `db.m5d.12xlarge` ==> $prefix = db, $serverType = m5d, $serverSize = 12xlarge
                    @list($prefix, $serverType, $serverSize) = explode('.', $instanceName);
                    $instanceType = $serverType ? strtoupper($serverType) : '';

                    //* '16 GiB' ==> $ram = 16; $ramUnit = GiB
                    @list($ram, $ramUnit) = explode(' ', $memory);
                    $ram = $ram ?: 0;

                    AmazonServer::firstOrCreate(array(
                        'name' => $instanceName,
                        'vcpu_qty' => $vCpu,
                        'ram' => $ram,
                        'server_type' => $instanceType,
                        'os_name' => $os,
                        'price_per_unit' => $pricePerUnit,
                        'price_unit' => $unit,
                        'purchase_option' => $purchaseOption,
                        'sku' => $this->getData($data, $colNameIndex, 'SKU'),
                        'offer_term_code' => $this->getData($data, $colNameIndex, 'OfferTermCode'),
                        'rate_code' => $this->getData($data, $colNameIndex, 'RateCode'),
                        'term_type' => $this->getData($data, $colNameIndex, 'TermType'),
                        'price_description' => $this->getData($data, $colNameIndex, 'PriceDescription'),
                        'effective_date' => $this->getData($data, $colNameIndex, 'EffectiveDate'),
                        'starting_range' => $this->getData($data, $colNameIndex, 'StartingRange'),
                        'ending_range' => $this->getData($data, $colNameIndex, 'EndingRange'),
                        'currency' => $this->getData($data, $colNameIndex, 'Currency'),
                        'related_to' => $this->getData($data, $colNameIndex, 'RelatedTo'),
                        'lease_contract_length' => $this->getData($data, $colNameIndex, 'LeaseContractLength'),
                        'offering_class' => $this->getData($data, $colNameIndex, 'OfferingClass'),
                        'product_family' => $this->getData($data, $colNameIndex, 'Product Family'),
                        'service_code' => $serviceCode,
                        'location' => $location,
                        'location_type' => $this->getData($data, $colNameIndex, 'Location Type'),
                        'current_generation' => $this->getData($data, $colNameIndex, 'Current Generation'),
                        'instance_family' => $this->getData($data, $colNameIndex, 'Instance Family'),
                        'physical_processor' => $this->getData($data, $colNameIndex, 'Physical Processor'),
                        'clock_speed' => $this->getData($data, $colNameIndex, 'Clock Speed'),
                        'storage' => $this->getData($data, $colNameIndex, 'Storage'),
                        'network_performance' => $this->getData($data, $colNameIndex, 'Network Performance'),
                        'processor_architecture' => $this->getData($data, $colNameIndex, 'Processor Architecture'),
                        'storage_media' => $this->getData($data, $colNameIndex, 'Storage Media'),
                        'volume_type' => $this->getData($data, $colNameIndex, 'Volume Type'),
                        'min_volume_size' => $this->getData($data, $colNameIndex, 'Min Volume Size'),
                        'max_volume_size' => $this->getData($data, $colNameIndex, 'Max Volume Size'),
                        'engine_code' => $this->getData($data, $colNameIndex, 'Engine Code'),
                        'database_engine' => $this->getData($data, $colNameIndex, 'Database Engine'),
                        'database_edition' => $this->getData($data, $colNameIndex, 'Database Edition'),
                        'license_model' => $this->getData($data, $colNameIndex, 'License Model'),
                        'deployment_option' => $this->getData($data, $colNameIndex, 'Deployment Option'),
                        'group' => $this->getData($data, $colNameIndex, 'Group'),
                        'group_description' => $this->getData($data, $colNameIndex, 'Group Description'),
                        'transfer_type' => $this->getData($data, $colNameIndex, 'Transfer Type'),
                        'from_location' => $this->getData($data, $colNameIndex, 'From Location'),
                        'from_location_type' => $this->getData($data, $colNameIndex, 'From Location Type'),
                        'to_location' => $this->getData($data, $colNameIndex, 'To Location'),
                        'to_location_type' => $this->getData($data, $colNameIndex, 'To Location Type'),
                        'usage_type' => $this->getData($data, $colNameIndex, 'usageType'),
                        'operation' => $this->getData($data, $colNameIndex, 'operation'),
                        'dedicated_ebs_throughput' => $this->getData($data, $colNameIndex, 'Dedicated EBS Throughput'),
                        'enhanced_networking_supported' => $this->getData($data, $colNameIndex, 'Enhanced Networking Supported'),
                        'processor_features' => $this->getData($data, $colNameIndex, 'Processor Features'),
                        'instance_type' => 'RDS'
                    ));

                    //* add row's region to `regions` table if new
                    Region::firstOrCreate([
                        'name' => $location,
                        'provider_service_type' => $serviceCode,
                        'provider_owner_id' => $awsProvider->id,
                    ]);
                }
                                    
                $parsedRowCount++;
            }

            echo $parsedRowCount . " rows processed\n";

            fclose($handle);
        }
    }

    private function getData($data, $colNameIndex, $colName) {
        if(array_key_exists($colName, $colNameIndex)) {
            return $data[$colNameIndex[$colName]];
        }

        return null;
    }
}
