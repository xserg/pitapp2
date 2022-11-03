<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class LanguageSeeder extends Seeder {
 
    public function run() {
        Model::unguard();
        
        $this->call(SeedLanguageCommon::class);
        $this->call(SeedLanguage::class);
        // TODO: What is this? Looking for lang_[?].json file and not finding a language directory
//        $this->call(SeedLanguageKeys::class);
    }
    
}