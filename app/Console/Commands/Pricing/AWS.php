<?php

namespace App\Console\Commands\Pricing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\Project\EmailController;
use App\Models\Hardware\AmazonServer;
use Illuminate\Support\Facades\DB;

class AWS extends Command
{
    protected $successMessage = 'The AWS pricing has successfully been updated on ';
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pricing:aws {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get latest dump of AWS pricing info';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Load & Update AWS Pricing
     * @param null $type
     * @return string
     * @throws \Throwable
     */
    public function handle($type = null)
    {
        ini_set("max_execution_time", 0);
        ini_set("max_input_time", -1);

        if (method_exists($this, 'getArgument') && $this->argument('type')) {
            $type = $this->argument('type');
        }
        
        // Remove existing downloaded files
        $this->removeDownloadedFiles();
        
        //now get the latest ones and filter them
        $this->getAWSPrices($type);

        DB::beginTransaction();
        try {
            echo sprintf("Starting \"%s\" import process...\n", $type);
            //clear any existing content
            $types = [$type];
            if ($types[0] == null) $types = ['ec2', 'rds'];

            echo "Deleting existing AWS pricing(amazon_servers)...\n";
            
            DB::table('amazon_servers')->whereIn('instance_type', $types)->delete();

            //Load data
            if ($type == null || $type == 'ec2') {
                $ec2Seeder = new \SeedAmazonServers(storage_path() . '/spreadsheet/ec2.csv');
                $ec2Seeder->run();
            }
            
            if ($type == null || $type == 'rds') {
                $rdsSeeder = new \SeedAmazonServersRDS(storage_path() . '/spreadsheet/rds.csv');
                $rdsSeeder->run();
            }

            // Commit updates
            DB::commit();

            Log::info($this->successMessage . getenv('APP_ENV'));
            $this->sendEmail($this->successMessage . getenv('APP_ENV'));

        } catch (\Exception $e) {
            DB::rollBack();

            // Remove downloaded files
            $this->removeDownloadedFiles();

            Log::error($e->getMessage() . "\n" . $e->getTraceAsString());
            $this->sendEmail($e->getMessage() . "\n" . $e->getTraceAsString());
            
            throw $e;
        }
    }

    private function sendEmail($body)
    {
        $emailController = new EmailController;
		$emailController->sendPricingConfirmationEmail("AWS Pricing Confirmation", $body);
    }

    /**
     * Gets the AWS prices
     *
     * @param null $type
     * @return mixed
     */
    public function getAWSPrices($type)
    {
        foreach(AmazonServer::AWS_SERVICES as $service) {
            // echo sprintf(
            //     "Processing AWS \"%s\" pricing files...\n",
            //     $service['name']
            // );

            //if this is called for a specific service type and it doesn't match, don't run it;
            if($type == $service['name'] || $type == null) {
                $downloadDir = storage_path() . '/spreadsheet/';
                
                if (!is_dir($downloadDir)) mkdir($downloadDir);

                $raw = $downloadDir . $service['name'] . '_raw.csv';
                $trimmed = $downloadDir . $service['name'] . '.csv';

                // Both raw and trimmed files already exist
                if (file_exists($raw) && filesize($raw) && file_exists($trimmed) && filesize($trimmed)) continue;

                //download the appropriate pricing sheet
                if (!file_exists($raw) || !filesize($raw)) $this->downloadFile($service['sheet'], $raw);
                
                //create a placeholder for the trimmed sheet
                $trimmedFile = fopen($trimmed, 'w');
                $trimmedCount = 1;

                if(($handle = fopen($raw, 'r')) !== false) {
                    // loop through the file line-by-line to read the leading lines of key/value metadata that prepend the actual CSV data
                    $i = 0;
                    while(($data = fgetcsv($handle)) !== false && $i < 4) {
                        unset($data);
                        $i++;
                    }

                     //now we're at the actual data part, get the headers and copy them into the new file
                    $header = fgetcsv($handle);
                    fputcsv($trimmedFile, $header);

                    //now pull out all the data that matches the filters setup for that service
                    while(($data = fgetcsv($handle)) !== false) {

                        //combine data row with the headers to make it easy to look up by name
                        $collection = collect($header);
                        $combined = $collection->combine($data);
                        $data = $combined->all();

                        $rowPassedFilters = true;

                        foreach($service['filters'] as $filter_name => $filter_value) {
                            //if any of the filters fail, stop checking and mark this as a record that doesn't get into the trimmed file.
                            if (!in_array($data[$filter_name], $filter_value)) {
                                $rowPassedFilters = false;    
                                break;
                            }
                        }

                        if ($service['name'] == 'ec2' && strstr(strtolower($data['usageType']), 'reservation')) {
                            $rowPassedFilters = false;
                        }

                        if($rowPassedFilters) {
                            fputcsv($trimmedFile, $data);  
                        }

                        unset($data);
                        $i++;

                        echo $trimmedCount . " rows trimmed\n";
                        $trimmedCount++;
                    }

                    fclose($handle);
                    fclose($trimmedFile);
                    exec(sprintf('chmod 666 %s', $trimmed));
                }
            }
        }
    }
    
    /**
     * @param string $url
     * @param ressource $dest
     * 
     * @return boolean 
     */
    public static function downloadFile($url, $dest) 
    {
        $options = array(
            CURLOPT_FILE  => is_resource($dest) ? $dest : fopen($dest, 'w'),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_URL => $url,
            CURLOPT_FAILONERROR => true,
        );
        
        // echo "Downloading AWS pricing files...\n";

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $return = curl_exec($ch);

        if ($return === false) {
            return curl_error($ch);
        } else {
            exec(sprintf('chmod 666 %s', $dest));
            return true;
        }
    }

    /**
     * @return $this
     */
    public function removeDownloadedFiles()
    {
        foreach(AmazonServer::AWS_SERVICES as $service) {
            $files = [
                storage_path() . '/spreadsheet/' . $service['name'] . '.csv',
                storage_path() . '/spreadsheet/' . $service['name'] . '_raw.csv'
            ];
            
            foreach($files as $file) {
                try {
                    if(file_exists($file)) {
                        unlink($file);  
                    }
                } catch(ErrorException $e) {

                }
            }
        }

        return $this;
    }
}
