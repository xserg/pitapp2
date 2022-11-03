<?php

//use Bus;
use Illuminate\Database\Seeder;
use App\Models\Language\LanguageText;
use App\Models\Language\LanguageKey;
use App\Models\Language\Language;
use App\Models\StandardModule\Component;
use Illuminate\Support\Facades\Event as Event;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;
use App\Console\Commands\Queued\UpdateLanguages;
use App\Console\Commands\Queued\Publish;

/**
 * Description of LanguageSeeder
 * @author bjones
 */
class SeedLanguageKeys extends Seeder {

    protected $lang_directory = "";
    protected $path = "/language";

    /**
     * This should go through each json file and insert the key-value pairs into
     * the correct table.
     */
    public function run() {
        
        $begin = time();
        // set the directory to the base path
        $this->lang_directory = base_path();
        
        /* For each directory found in the language folder. */
        $language_folder = $this->lang_directory . $this->path;

        $lang_files = $this->getLangFiles($language_folder);

            /* For each file found in the directory. */
            foreach ($lang_files as $lang_file) {
                
                /* Decode the file contents. */
                $json_data = file_get_contents(/*$language_folder . $component_directory . '/' .*/ $lang_file);

                $json_decoded = json_decode($json_data, TRUE);
                
                /**
                 * If the language doesn't exist in the language table, add it 
                 * to the language table.       
                 */
                $file_info = pathInfo($lang_file);
                $language_filename = $file_info['filename'];
                
                $language_abbreviation = strtolower(substr($language_filename, -2));
                
                $num_languages = Language::where('abbreviation', 'LIKE', '%' . $language_abbreviation . '%')->get();
                
                if (count($num_languages) == 0) {
                    Language::create(array('abbreviation' => $language_abbreviation));
                }
                
                /* Get the language id for the file. */
                $language_id = Language::where('abbreviation', 'LIKE', '%' . $language_abbreviation . '%')->firstOrFail()->id;
                
                /* For each key-value pair. */
                foreach ($json_decoded as $key => $value) {
                    /**
                     * Update or create the corresponding record in the language_key
                     * table.
                     */
                    $component_name = strstr($key, '.', TRUE);

                    try {
                        $component_id = Component::where('name', 'LIKE', '%' . $component_name . '%')->firstOrFail()->id;
                    } catch (ModelNotFoundException $mnf) {
                        echo "WARNING: Could not assign `{$value}` to `{$key}`: No matching component name `{$component_name}`.\n";
                        continue;
                    }
                    
                    LanguageKey::updateOrCreate(array(
                        'key' => $key,
                        'component_id' => $component_id
                    ));

                    /**
                     * Update or create the corresponding record in the
                     * language_text table.
                     */

                    LanguageText::updateOrCreate(
                        array('language_id' => $language_id, 'language_key' => $key),
                        array('content' => $value)
                    );
                }
            }
        
        /* Fire an event signaling that the database has been updated. */
        Bus::dispatch(new UpdateLanguages());
        Bus::dispatch(new Publish());
        
        $end = time();
        $seconds = $end - $begin;
        $minutes = (int) ($seconds / 60);
        $minSeconds = $seconds % 60;
        echo "The language key seeder ran in $seconds seconds or $minutes minutes $minSeconds seconds. \n";
    }
    
    /**
     * Recursively look through the files in $path, and find all of the language json files,
     * and return them as an array of strings to be parsed
     */
    private function getLangFiles($path) {
        $files = array();
        foreach(scandir($path) as $file) {
            if(is_dir($path . "/" . $file) && $file !== '.' && $file !== "..") {
                $files = array_merge($files, $this->getLangFiles($path . "/" . $file));
            } else if(substr($file, 0, 5) === 'lang_' && substr($file, strpos($file, ".")) === ".json") {
                $files[] = $path . "/" . $file;
            }
        }
        return $files;
    }
}
