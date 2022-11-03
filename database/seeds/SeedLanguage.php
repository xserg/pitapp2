<?php


/**
 * Seeds the Language table
 *
 * @author bjones
 */

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\StandardModule\Component;
use App\Models\Language\Language;

class SeedLanguage extends Seeder{
    
    public function run() {
        
        /* English seed. */
        Language::firstOrCreate(array(
            "name"=> "English",
            "abbreviation"=> "en"
        ));
        
        /* Spanish seed. */
        Language::firstOrCreate(array(
            "name"=>"Spanish",
            "abbreviation"=> "es"     
        ));
    }       
}
