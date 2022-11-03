<?php

/**
 * Description of SeedValueTypes
 *
 * @author hnguyen
 */

use Illuminate\Database\Seeder;
use App\Models\Configuration\ValueType;

class SeedValueTypes extends Seeder {
    
    public function run() {
        
        ValueType::firstOrCreate(array(
            "label" => "boolean"
        ));
        
        ValueType::firstOrCreate(array(
            "label" => "unsigned integer"
        ));
        
        ValueType::firstOrCreate(array(
            "label" => "string"
        ));
    }
}