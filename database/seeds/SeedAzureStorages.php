<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Hardware\AzureStorage;

class SeedAzureStorages extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() 
    {
        $colnames = true;
        // The columns are as follows:
        //0 Storage Type
        //1 Redundancy
        //2 Region
        //3 Disk Tier
        //4 DISK SIZE (TB)
        //5 PRICE PER MONTH
        //6 PRICE PER GB-month
        //7 IOPS PER DISK
        //8 THROUGHPUT PER DISK (MB/second)
        if(($handle = fopen(__DIR__.'/../data/AzureStorages.csv', "r")) !== FALSE) {
            $counter = 0;
            $colNameIndex = [];
            \DB::beginTransaction();
            \DB::statement("TRUNCATE TABLE azure_storages");
            try {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if ($colnames) {
                        $colnames = false;
                        foreach ($data as $index => $colName) {
                            $colNameIndex[$colName] = $index;
                        }
                    } else {
                        AzureStorage::create(array(
                            "storage_type" => $this->getData($data, $colNameIndex, "Storage Type"),
                            "redundancy" => $this->getData($data, $colNameIndex, "Redundancy"),
                            "region" => $this->getData($data, $colNameIndex, "Region"),
                            "disk_tier" => $this->getData($data, $colNameIndex, "Disk Tier"),
                            "disk_size" => $this->getData($data, $colNameIndex, "DISK SIZE (TB)"),
                            "monthly_price" => $this->getData($data, $colNameIndex, "PRICE PER MONTH")? $this->getData($data, $colNameIndex, "PRICE PER MONTH") : null,
                            "monthly_price_per_gb" => $this->getData($data, $colNameIndex, "PRICE PER GB-month")? $this->getData($data, $colNameIndex, "PRICE PER GB-month") : null,
                            "iops_per_disk" => $this->getData($data, $colNameIndex, "IOPS PER DISK"),
                            "throughput_per_disk" => $this->getData($data, $colNameIndex, "THROUGHPUT PER DISK (MB/second)")
                        ));

                    }
                }
                \DB::commit();
            } catch (\Throwable $e) {
                if (\DB::transactionLevel() > 0) {
                    \DB::rollBack();
                }
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
