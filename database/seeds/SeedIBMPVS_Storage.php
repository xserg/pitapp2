<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Hardware\IBMPVSStorage;

class SeedIBMPVS_Storage extends Seeder
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
        //2 PRICE PER GB MONTH
        if(($handle = fopen(__DIR__.'/../data/IBMPVS_Storage.csv', "r")) !== FALSE) {
            $counter = 0;
            $colNameIndex = [];
            \DB::beginTransaction();
            \DB::statement("TRUNCATE TABLE ibmpvs_storages");
            try {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if ($colnames) {
                        $colnames = false;
                        foreach ($data as $index => $colName) {
                            $colNameIndex[$colName] = $index;
                        }
                    } else {
                        IBMPVSStorage::firstOrCreate(array(
                            "storage_type" => $data[0],
                            "region" => $this->getData($data, $colNameIndex, "Region"),
                            "monthly_price_per_gb" => $this->getData($data, $colNameIndex, "PRICE PER GB MONTH")? $this->getData($data, $colNameIndex, "PRICE PER GB MONTH") : null
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
