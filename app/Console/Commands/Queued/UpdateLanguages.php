<?php namespace App\Console\Commands\Queued;

use App\Console\Commands\Command;
use App\Console\Commands\Scheduled\ScheduledCommand;
use App\Models\Language\Language;
use App\Models\Language\LanguageKey;
use App\Models\Language\LanguageText;
use App\Writers\Bucket;
use App\Writers\Logger;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;

class UpdateLanguages extends Command implements ShouldQueue {

	use DispatchesJobs, SerializesModels;
    
    protected $logger;
    
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
        $this->logger = new Logger(new Bucket('language', new Bucket('foundation')));

	}

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle() {
        
        /* For each language. */
        foreach (Language::all() as $language) {

            $language_array = array();

            /* Get the abbreviation for the language. */
            $abbreviation = $language->abbreviation;

            /* For each language key. */
            foreach (LanguageKey::all() as $language_key) {

                /* Use the language key-language id pair to get a language text. */
                $language_texts = LanguageText::whereRaw('language_key = \'' .
                        $language_key->key. '\' and language_id = ' . $language->id)->get();
                        
                /* If there are no language texts that contain this pair, skip. */
                if (count($language_texts)==0) {
                    continue;
                }

                /* Get the language text from the array. */
                $language_text = $language_texts->first();
                
                /* Create the key and insert into the array for the language. */
                $language_array[$language_key->key] = $language_text->content;
            }
            
            /* If the public folder doesn't exist, make it. */
            $public_folder = __DIR__.'/../../../public/';
            if (!file_exists($public_folder)) {
                mkdir($public_folder);
            }
            
            /* If the public's language folder doesn't exist, make it. */
            $language_folder = public_path() . '/language/';
            //$this->logger->log("$language_folder exists?" . !file_exists($language_folder));
            if (!file_exists($language_folder)) {
                mkdir($language_folder, 0775, true);
            }
            
            /* Make the language json file. */
            $language_file = fopen($language_folder.'lang_' . $abbreviation . ".json", 'w');
            fwrite($language_file, json_encode($language_array));
            fclose($language_file);
        }
        
	}

}
