<?php namespace App\Console\Commands\Queued;

use App\Console\Commands\Command;
use App\Console\Commands\Scheduled\ScheduledCommand;
use App\Models\Language\Language;
use App\Models\Language\LanguageKey;
use App\Models\Language\LanguageText;
use App\Writers\Bucket;
use App\Writers\Logger;
use App\Writers\CDNWriter;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;

class Publish extends Command implements ShouldQueue {

	use DispatchesJobs, SerializesModels;
    
    protected $logger;
    protected $languageDestination = 'language';
    protected $langSource;
    protected $manifestJson;
    protected $cdnWriter;
    
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
        $this->logger = new Logger(new Bucket('language', new Bucket('foundation')));
        $this->langSource = public_path() . "/language";
        
        //We only need one instance of the CDNWriter for the entire publish routine
        $this->cdnWriter = new CDNWriter();
        
        //Set up an associative array for the manifestJson
        $this->manifestJson = [];
	}

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle() {
        //Loop through all the files in /public/language
        $fileList = scandir($this->langSource);
        
        foreach($fileList as $file)
        {
            
            if($file !== "." && $file !== ".." && preg_match('/lang_[a-zA-Z]{2}.json/', $file)) {
                $this->logger->log("publish language file $file.gz");
                
                // file name without the extension
                $newFile = substr($file, 0, strpos($file, "."));
                
                // abbreviation for language
                $lang = substr($newFile, strpos($newFile, "_") + 1);
                
                // Append date and extension to file name
                $newFile = $newFile . "_" . date("Ymd") . ".json";
                $this->logger->log("The new public file will be created as $newFile");
                
                //Compress the file
                //Save it to the cdn (env variable) as lang_{abbr}_{datestamp}.json
                //Language folder parallel to vendor folder
                
                // empty constructor so that all sites in cdn path are published to
                $this->cdnWriter->write(file_get_contents("$this->langSource/$file"), $this->languageDestination, $newFile, true);
                
                //Add the new key value pair to the manifest file
                $this->logger->log("Adding $lang as $newFile.gz to the language manifest.");
                $this->manifestJson[$lang] = (object)['file' => "$newFile.gz"];

            }
        }
        
        //Update the mamifest file
        $this->updateManifest();
	}
        
    private function updateManifest() {
//        if (!isset($this->manifestJson) || !is_array($this->manifestJson)) {
//            $this->manifestJson = [];
//        }
//        
//        $this->manifestJson[$lang] = (object)['file' => $newFilename]; 
        $this->logger->log("Manifest json contents: " . json_encode($this->manifestJson));
        
        // empty constructor so that all sites in cdn path are published to
        $this->cdnWriter->write(json_encode($this->manifestJson), $this->languageDestination, 'lang_manifest.json', false);
    }

    
}
