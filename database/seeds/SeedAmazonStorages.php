<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Hardware\AmazonStorage;

class SeedAmazonStorages extends Seeder
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
        //1 Region
        //2 PRICE PER GB-month
        //3 Price per Provisioned IOPS-month
        if(($handle = fopen(__DIR__.'/../data/AmazonStorages.csv', "r")) !== FALSE) {
            $counter = 0;
            $colNameIndex = [];
            \DB::beginTransaction();
            \DB::statement("TRUNCATE TABLE amazon_storages");
            try {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if ($colnames) {
                        $colnames = false;
                        foreach ($data as $index => $colName) {
                            $colNameIndex[$colName] = $index;
                        }
                    } else {
                        // Change Northern to N.
                        $region = $this->getData($data, $colNameIndex, "Region") !== 'US West (Northern California)' ? $this->getData($data, $colNameIndex, "Region") : 'US West (N. California)';
                        AmazonStorage::create(array(
                            "storage_type" => $this->getData($data, $colNameIndex, "Storage Type"),
                            "region" => $region,
                            "monthly_price_per_gb" => $this->getData($data, $colNameIndex, "PRICE PER GB-month") !== 0 ? $this->getData($data, $colNameIndex, "PRICE PER GB-month") : null,
                            "monthly_price_per_iops" => $this->getData($data, $colNameIndex, "Price per Provisioned IOPS-month")? $this->getData($data, $colNameIndex, "Price per Provisioned IOPS-month") : null,
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
