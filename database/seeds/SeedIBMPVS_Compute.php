<?php

/**
 * Seeds the activities and states for Project related things
 *
 * @author jdobrowolski
 */

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Hardware\AmazonServer;
use App\Console\Commands\Pricing\IBMPVS;
use Illuminate\Support\Facades\DB;

class SeedIBMPVS_Compute extends Seeder {

    private $csvFile;

    function __construct($csvFile = __DIR__.'/../data/IBMPVS_Compute.csv') {
        $this->csvFile = $csvFile;
    }

    public function run() {
        Model::unguard();
        $colnames = true;
        // The columns are as follows:
        //0 operating_system
        //1 software_type
        //2 category
        //3 instance
        //3 vcpu
        //4 ram
        //5 price_per_hour
        //6 currency
        //7 region

        if(($handle = fopen($this->csvFile, "r")) !== FALSE) {
            $colNameIndex = [];
            try {
                DB::beginTransaction();
                DB::statement("DELETE FROM amazon_servers WHERE instance_type = 'IBMPVS'");

                while (($data = fgetcsv($handle)) !== FALSE) {
                    if ($colnames) {
                        $colnames = false;
                        foreach ($data as $index => $colName) {
                            $colNameIndex[$colName] = $index;
                        }
                    } else {
                        
                        AmazonServer::firstOrCreate([
                            "name" => $this->getData($data, $colNameIndex, "instance"),
                            "vcpu_qty" => $this->getData($data, $colNameIndex, "vcpu"),
                            "ram" => $this->getData($data, $colNameIndex, "ram"),
                            "server_type" => $this->getData($data, $colNameIndex, "instance"),
                            "os_name" => $this->getData($data, $colNameIndex, "software_type"),
                            "price_per_unit" => $this->getData($data, $colNameIndex, "price_per_hour"),
                            "price_unit" => "Hrs",
                            "term_type" => 'Pay as you go',
                            "currency" => $this->getData($data, $colNameIndex, "currency"),
                            "location" => $this->getData($data, $colNameIndex, "region"),
                            "instance_family" => $this->getData($data, $colNameIndex, "category"),
                            "software_type" => $this->getData($data, $colNameIndex, "software_type"),
                            "instance_type" => "IBMPVS",
                            "cpm" => $this->getData($data, $colNameIndex, "cpm"),
                        ]);
                    }
                }
                DB::commit();
                Model::reguard();
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                throw $e;
            }
        }

    }
    private function getData($data, $colNameIndex, $colName) {
        if(array_key_exists($colName, $colNameIndex)) {
            return trim($data[$colNameIndex[$colName]]);
        }
        return null;
    }
}
