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
use Illuminate\Support\Facades\DB;

class SeedGoogleServers extends Seeder {

    private $csvFile;

    function __construct($csvFile = __DIR__.'/../data/GoogleServers.csv') {
        $this->csvFile = $csvFile;
    }

    public function run() {
        Eloquent::unguard();
        
        $colnames = true;
        // The columns are as follows:
        //0 TermType
        //1 Term Length (YR)
        //2 Operating System
        //3 Software type
        //4 Category
        //5 Instance Type
        //6 vCPU
        //7 Memory (GiB)
        //8 PriceperHour
        //9 Currency
        //10 Region

        if(($handle = fopen($this->csvFile, "r")) !== FALSE) {
            $colNameIndex = [];
            $parsedRowCount = 0;
            $googleProvider = Provider::where('name', 'Google')->first();

            try {
                echo "Deleting existing Google servers...\n";

                DB::beginTransaction();
                DB::statement("DELETE FROM amazon_servers WHERE instance_type = 'Google'");

                while (($data = fgetcsv($handle)) !== FALSE) {
                    if ($colnames) {
                        $colnames = false;

                        foreach ($data as $index => $colName) {
                            $colNameIndex[$colName] = $index;
                        }

                    } else {
                        $location = $this->getData($data, $colNameIndex, "region");
                        $termType = $this->getData($data, $colNameIndex, "term_type");

                        // Separate payment option "Commitment" vs "Pay as you go"
                        if (preg_match('/commit/i', $termType)) {
                            $termType = 'Reserved';
                        } else {
                            $termType = 'Pay as you go';
                        }

                        AmazonServer::firstOrCreate([
                            "name" => $this->getData($data, $colNameIndex, "instance"),
                            "vcpu_qty" => $this->getData($data, $colNameIndex, "vcpu"),
                            "ram" => $this->getData($data, $colNameIndex, "ram"),
                            "server_type" => $this->getData($data, $colNameIndex, "instance"),
                            "os_name" => $this->getData($data, $colNameIndex, "operating_system"),
                            "price_per_unit" => $this->getData($data, $colNameIndex, "price_per_hour"),
                            "price_unit" => "Hrs",
                            "term_type" => $termType,
                            "currency" => $this->getData($data, $colNameIndex, "currency"),
                            "lease_contract_length" => $this->getData($data, $colNameIndex, "term_length"),
                            "location" => $location,
                            "instance_family" => $this->getData($data, $colNameIndex, "category"),
                            "software_type" => $this->getData($data, $colNameIndex, "software_type"),
                            "instance_type" => "Google"
                        ]);

                        //* add row's region to `regions` table if new
                        Region::firstOrCreate([
                            'name' => $location,
                            'provider_service_type' => null,
                            'provider_owner_id' => $googleProvider->id,
                        ]);
                    }

                    $parsedRowCount++;
                }

                echo $parsedRowCount . " rows processed\n";

                //* find all disabled(`enabled = 0`) Google regions and enabled them
                //*  those regions are disabled in the UI
                Region::where('provider_owner_id', $googleProvider->id)
                    ->where('enabled', 0)
                    ->update(['enabled' => 1]);

                DB::commit();
                Eloquent::reguard();

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
