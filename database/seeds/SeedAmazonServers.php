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

class SeedAmazonServers extends Seeder {

    private $csvFile;

    function __construct($csvFile = __DIR__.'/../data/AmazonServers.csv') {
        $this->csvFile = $csvFile;
    }

    public function run() {
        Eloquent::unguard();

        $colnames = true;
        // The columns are as follows:
        //0 SKU
        //1 OfferTermCode
        //2 RateCode
        //3 TermType
        //4 PriceDescription
        //5 EffectiveDate
        //6 StartingRange
        //7 EndingRange
        //8 Unit
        //9 PricePerUnit
        //10 Currency
        //11 LeaseContractLength
        //12 PurchaseOption
        //13 OfferingClass
        //14 Product Family
        //15 serviceCode
        //16 Location
        //17 Location Type
        //18 Instance Type
        //19 Current Generation
        //20 Instance Family
        //21 vCPU
        //22 Physical Processor
        //23 Clock Speed
        //24 Memory
        //25 Storage
        //26 Network Performance
        //27 Processor Architecture
        //28 Storage Media
        //29 Volume Type
        //30 Max Volume Size
        //31 Max IOPS/volume
        //32 Max IOPS Burst Performance
        //33 Max throughput/volume
        //34 Provisioned
        //35 Tenancy
        //36 EBS Optimized
        //37 Operating System
        //38 License Model
        //39 Group
        //40 Group Description
        //41 Transfer Type
        //42 From Location
        //43 From Location Type
        //44 To Location
        //45 To Location Type
        //46 usageType
        //47 operation
        //48 Comments
        //49 Dedicated EBS Throughput
        //50 ECU
        //51 Enhanced Networking Supported
        //52 GPU
        //53 Instance Capacity - 10xlarge
        //54 Instance Capacity - 2xlarge
        //55 Instance Capacity - 4xlarge
        //56 Instance Capacity - 8xlarge
        //57 Instance Capacity - large
        //58 Instance Capacity - medium
        //59 Instance Capacity - xlarge
        //60 Intel AVX Available
        //61 Intel AVX2 Available
        //62 Intel Turbo Available
        //63 Physical Cores
        //64 Pre Installed S/W
        //65 Processor Features
        //66 Sockets
        if(($handle = fopen($this->csvFile, 'r')) !== FALSE) {
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
                    $unit = $this->getData($data, $colNameIndex, 'Unit');
                    $pricePerUnit = $this->getData($data, $colNameIndex, 'PricePerUnit');

                    if ($pricePerUnit == 0) continue; // Don't include $0 instances: All Upfront

                    $instanceName = $this->getData($data, $colNameIndex, 'Instance Type');
                    $os = $this->getData($data, $colNameIndex, 'Operating System');
                    $serviceCode = $this->getData($data, $colNameIndex, 'serviceCode');
                    $memory = $this->getData($data, $colNameIndex, 'Memory') ?: '';
                    $purchaseOption = $this->getData($data, $colNameIndex, 'PurchaseOption') ?: null;
                    $vCpu = $this->getData($data, $colNameIndex, 'vCPU') ?: 0;

                    //The first section of the instance name should be its type (M4/R4)
                    //* `m4.xlarge` ==> $serverType = m4, $serverSize = xlarge
                    @list($serverType, $serverSize) = explode('.', $instanceName);
                    $instanceType = $serverType ? strtoupper($serverType) : null;

                    //Ram is suffixed with ' GiB', we need to remove that
                    //* '16 GiB' ==> $ram = 16; $ramUnit = GiB
                    @list($ram, $ramUnit) = explode(' ', $memory);
                    $ram = $ram ? str_replace(',', '', $ram) : 0;

                    // Change Northern to N.
                    $location = $this->getData($data, $colNameIndex, 'Location') !== 'US West (Northern California)'
                        ? $this->getData($data, $colNameIndex, 'Location')
                        : 'US West (N. California)';

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
                        'max_volume_size' => $this->getData($data, $colNameIndex, 'Max Volume Size'),
                        'max_iops_volume' => $this->getData($data, $colNameIndex, 'Max IOPS/volume'),
                        'max_iops_burst_performance' => $this->getData($data, $colNameIndex, 'Max IOPS Burst Performance'),
                        'max_throughput_volume' => $this->getData($data, $colNameIndex, 'Max throughput/volume'),
                        'provisioned' => $this->getData($data, $colNameIndex, 'Provisioned'),
                        'tenancy' => $this->getData($data, $colNameIndex, 'Tenancy'),
                        'ebs_optimized' => $this->getData($data, $colNameIndex, 'EBS Optimized'),
                        'license_model' => $this->getData($data, $colNameIndex, 'License Model'),
                        'group' => $this->getData($data, $colNameIndex, 'Group'),
                        'group_description' => $this->getData($data, $colNameIndex, 'Group Description'),
                        'transfer_type' => $this->getData($data, $colNameIndex, 'Transfer Type'),
                        'from_location' => $this->getData($data, $colNameIndex, 'From Location'),
                        'from_location_type' => $this->getData($data, $colNameIndex, 'From Location Type'),
                        'to_location' => $this->getData($data, $colNameIndex, 'To Location'),
                        'to_location_type' => $this->getData($data, $colNameIndex, 'To Location Type'),
                        'usage_type' => $this->getData($data, $colNameIndex, 'usageType'),
                        'operation' => $this->getData($data, $colNameIndex, 'operation'),
                        'comments' => $this->getData($data, $colNameIndex, 'Comments'),
                        'dedicated_ebs_throughput' => $this->getData($data, $colNameIndex, 'Dedicated EBS Throughput'),
                        'ecu' => $this->getData($data, $colNameIndex, 'ECU'),
                        'enhanced_networking_supported' => $this->getData($data, $colNameIndex, 'Enhanced Networking Supported'),
                        'gpu' => $this->getData($data, $colNameIndex, 'GPU'),
                        'instance_capacity_10xlarge' => $this->getData($data, $colNameIndex, 'Instance Capacity - 10xlarge'),
                        'instance_capacity_2xlarge' => $this->getData($data, $colNameIndex, 'Instance Capacity - 2xlarge'),
                        'instance_capacity_4xlarge' => $this->getData($data, $colNameIndex, 'Instance Capacity - 4xlarge'),
                        'instance_capacity_8xlarge' => $this->getData($data, $colNameIndex, 'Instance Capacity - 8xlarge'),
                        'instance_capacity_large' => $this->getData($data, $colNameIndex, 'Instance Capacity - large'),
                        'instance_capacity_medium' => $this->getData($data, $colNameIndex, 'Instance Capacity - medium'),
                        'instance_capacity_xlarge' => $this->getData($data, $colNameIndex, 'Instance Capacity - xlarge'),
                        'intel_avx_available' => $this->getData($data, $colNameIndex, 'Intel AVX Available'),
                        'intel_avx2_available' => $this->getData($data, $colNameIndex, 'Intel AVX2 Available'),
                        'intel_turbo_available' => $this->getData($data, $colNameIndex, 'Intel Turbo Available'),
                        'physical_cores' => $this->getData($data, $colNameIndex, 'Physical Cores'),
                        'pre_installed_sw' => $this->getData($data, $colNameIndex, 'Pre Installed S/W'),
                        'processor_features' => $this->getData($data, $colNameIndex, 'Processor Features'),
                        //'sockets' => $data[66],
                        'instance_type' => 'EC2'
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
        }

        Eloquent::reguard();
    }

    private function getData($data, $colNameIndex, $colName) {
        if(array_key_exists($colName, $colNameIndex)) {
            return $data[$colNameIndex[$colName]];
        }

        return null;
    }
}
