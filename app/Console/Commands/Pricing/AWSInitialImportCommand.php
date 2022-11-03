<?php

namespace App\Console\Commands\Pricing;

use App\Http\Controllers\Api\Project\EmailController;
use App\Models\Hardware\AmazonServer;
use App\Models\Project\Provider;
use App\Models\Project\Region;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SeedAmazonServers;
use SeedAmazonServersRDS;

class AWSInitialImportCommand extends Command
{
    const MAX_CHUNK_FILE_SIZE = 10000000; // limit chunk file size to 10MB
    const SERVICE_TYPE_EC2 = 'ec2';
    const SERVICE_TYPE_RDS = 'rds';
    const IMPORT_DIR = 'spreadsheet/aws/';
    const IMPORT_CHUNK_DIR = 'spreadsheet/aws/chunks/';
    const IMPORT_INIT_FILENAME = 'spreadsheet/aws-import-initd.txt';

    protected $nextChunkFile = null;
    protected $successMessage = 'The initial AWS pricing has successfully been updated on ';
    protected $initMessage = 'Kicking off the initial AWS pricing import. Env: ';
        
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pricing:aws-initial-import {type?}';
    
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
        //* abort if the `Initial` import already ran
        if (Storage::exists(self::IMPORT_INIT_FILENAME)) {
            echo "AWS initial import already ran\n";

            return;
        }

        ini_set("max_execution_time", 0);
        ini_set("max_input_time", -1);

        if (method_exists($this, 'getArgument') && $this->argument('type')) {
            $type = $this->argument('type');
        }

        // Remove existing downloaded files
        // $this->removeDownloadedFiles();
        
        //now get the latest ones and filter them
        $this->getAWSPrices($type);

        echo "Starting import process...\n";

        try {
            //clear any existing content
            $types = [$type];

            if ($types[0] == null) {
                $types = [ self::SERVICE_TYPE_EC2, self::SERVICE_TYPE_RDS ];
            }

            // echo "Deleting existing AWS pricing(amazon_servers)...\n\n";
            $awsServerCount = DB::table('amazon_servers')
                ->whereIn('instance_type', $types)
                ->count();

            //* delete 'amazon_servers' rows by chunks of 1000
            while ($awsServerCount > 0) {
                DB::transaction(function () use ($types) {
                    DB::table('amazon_servers')
                        ->whereIn('instance_type', $types)
                        ->limit(1000)
                        ->delete();
                });

                $awsServerCount = DB::table('amazon_servers')
                    ->whereIn('instance_type', $types)
                    ->count();

                echo sprintf("%s AWS servers left..\n", $awsServerCount);
            }

            $awsProvider = Provider::where('name', 'AWS')->first();

            //* import trimmed data
            if ($type == null || $type == self::SERVICE_TYPE_EC2) {
                //* delete existing EC2 regions
                // Region::where('provider_owner_id', $awsProvider->id)
                //     ->where('provider_service_type', 'like', '%ec2')
                //     ->delete();
                $this->importEC2Servers();
            }
            
            if ($type == null || $type == self::SERVICE_TYPE_RDS) {
                //* delete existing RDS regions
                // Region::where('provider_owner_id', $awsProvider->id)
                //     ->where('provider_service_type', 'like', '%rds')
                //     ->delete();
                $this->importRDSServers();
            }

            //* write the import completion date to the filesystem
            Storage::put(
                self::IMPORT_INIT_FILENAME,
                sprintf(
                    "%s Initial import completed. \n",
                    Carbon::now()->toDateTimeString()
                )
            );

            Log::info($this->successMessage . getenv('APP_ENV'));
            $this->sendEmail($this->successMessage . getenv('APP_ENV'));
    
            $this->removeDownloadedFiles();

        } catch (\Exception $e) {
            $this->removeDownloadedFiles();

            Log::error($e->getMessage() . "\n" . $e->getTraceAsString());
            $this->sendEmail($e->getMessage() . "\n" . $e->getTraceAsString());
            
            throw $e;
        }
    }

    /**
     * Send import comfirmation email
     *
     * @param mixed $body
     *
     * @return void
     */
    private function sendEmail($body)
    {
        $emailController = new EmailController;
		$emailController->sendPricingConfirmationEmail("Initial AWS Pricing Confirmation", $body);
    }

    /**
     * Gets the AWS prices
     *
     * @param null $type
     * @return mixed
     */
    public function getAWSPrices($type)
    {
        if (!Storage::exists(self::IMPORT_DIR)) {
            Storage::makeDirectory(self::IMPORT_DIR);
        }

        foreach(AmazonServer::AWS_SERVICES as $service) {
            //if this is called for a specific service type and it doesn't match, don't run it;
            if($type == $service['name'] || $type == null) {
                $raw = self::IMPORT_DIR . $service['name'] . '_raw.csv';
                $rawCsvPath = Storage::path(self::IMPORT_DIR) . $service['name'] . '_raw.csv';
                $trimmedCsvPath = Storage::path(self::IMPORT_DIR) . $service['name'] . '.csv';

                //* raw and trimmed files already downloaded
                if (file_exists($rawCsvPath)
                    && filesize($rawCsvPath)
                    && file_exists($trimmedCsvPath)
                    && filesize($trimmedCsvPath)
                ) continue;

                //* download the appropriate pricing sheet
                if (!Storage::exists($raw) || Storage::size($raw) <= 0) {
                    $this->downloadFile($service['sheet'], $rawCsvPath);
                }
                
                //* split large files(> 10MB) into chuncks
                if (Storage::size($raw) > self::MAX_CHUNK_FILE_SIZE) {
                    $this->trimChunkRawCsv($service, $rawCsvPath);

                } else {
                    $this->trimRawCsv($service, $rawCsvPath, $trimmedCsvPath);
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
        File::cleanDirectory(Storage::path(self::IMPORT_DIR));

        // foreach(AmazonServer::AWS_SERVICES as $service) {
        //     $files = [
        //         $this->baseDir . $service['name'] . '.csv',
        //         $this->baseDir . $service['name'] . '_raw.csv'
        //     ];

        //     foreach($files as $file) {
        //         try {
        //             if(file_exists($file)) unlink($file);
                    
        //         } catch(\ErrorException $e) {

        //         }
        //     }
        // }

        return $this;
    }

    /**
     * Create and open next chunk file handle, then return the file name
     * 
     * This method assign the open chunk file resource to
     *  `$this->nextChunkFile` for use by other methods.
     *
     * @param string $serviceType
     * @param array $csvHeaders
     *
     * @return string The opened file name
     */
    public function setNextChunkFile(string $serviceType, array $csvHeaders)
    {
        $chunkDir = self::IMPORT_CHUNK_DIR . $serviceType;

        if (!Storage::exists($chunkDir)) {
            Storage::makeDirectory($chunkDir);
        }

        $nextChunkIndex = count(Storage::files($chunkDir));

        //* storage/app/spreadsheet/chunks/ec2_<#>.csv
        // $chunkFileName = sprintf('%s_%s.csv', $serviceType, $nextChunkIndex);
        $chunkFile = sprintf(
            '%s/%s_%s.csv',
            Storage::path($chunkDir),
            $serviceType,
            $nextChunkIndex
        );
        
        if (is_resource($this->nextChunkFile)) {
            fclose($this->nextChunkFile);
        }

        // $chunkFile = Storage::path($chunkDir) . '/' . $chunkFileName;

        $this->nextChunkFile = fopen($chunkFile, 'w');
        // $this->nextChunkFile = Storage::readStream($chunkFile);
        
        // set chunk file's headers
        fputcsv($this->nextChunkFile, $csvHeaders);
        
        exec(sprintf('chmod 666 %s', $chunkFile));
        // Storage::setVisibility($chunkFile, 'private');

        return $chunkFile;
    }

    /**
     * Filter raw CSV file for given service
     *
     * @param array $service An array of metadata for the given AWS service(ecs, rds)
     * @param string $rawCsv Path to raw CSV file
     * @param string $trimmedFileName Name the trimmed file should be stored as
     *
     * @return void
     */
    public function trimRawCsv(array $service, string $rawCsv, string $trimmedFileName)
    {
        $trimmedFile = fopen($trimmedFileName, 'w');
        $trimmedCount = 1;

        if(($handle = fopen($rawCsv, 'r')) !== false) {
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

                if ($service['name'] == self::SERVICE_TYPE_EC2 && strstr(strtolower($data['usageType']), 'reservation')) {
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
            exec(sprintf('chmod 666 %s', $trimmedFileName));
        }
    }

    /**
     * Parse large raw CSV file into filtered chuncks of smaller CSV files
     *
     * @param array $service An array of metadata for the given AWS service(ecs, rds)
     * @param string $rawCsv Path to CSV file to trim
     *
     * @return void
     */
    public function trimChunkRawCsv(array $service, string $rawCsv)
    {
        if (!Storage::exists(self::IMPORT_CHUNK_DIR . $service['name'])) {
            Storage::makeDirectory(self::IMPORT_CHUNK_DIR . $service['name']);
        }

        //* clear previous chunks files for the given serveice
        $oldChunkFiles = Storage::files(
            self::IMPORT_CHUNK_DIR . $service['name']
        );
        Storage::delete($oldChunkFiles);

        if (($handle = fopen($rawCsv, 'r')) !== false) {            
            // loop through the file line-by-line to read the leading lines of
            //   key/value metadata that prepend the actual CSV data
            $i = 0;
            while(($data = fgetcsv($handle)) !== false && $i < 4) {
                unset($data);
                $i++;
            }

             // get CSV headers
            $csvHeaders = fgetcsv($handle);
            $this->setNextChunkFile($service['name'], $csvHeaders);
            $trimmedCount = 1;
            
            // get all rows that match filters for the given service
            while(($data = fgetcsv($handle)) !== false) {
                //combine data row with the headers to make it easy to look up by name
                $collection = collect($csvHeaders);
                $combined = $collection->combine($data);
                $data = $combined->all();
                $rowPassedFilters = true;

                foreach($service['filters'] as $filter_name => $filter_value) {
                    //if any of the filters fail, stop checking and mark this as a
                    //  record that doesn't get into the trimmed file.
                    if (!in_array($data[$filter_name], $filter_value)) {
                        $rowPassedFilters = false;    
                                $rowPassedFilters = false;    
                        $rowPassedFilters = false;    
                        break;
                    }
                }

                if ($service['name'] == self::SERVICE_TYPE_EC2 && strstr(strtolower($data['usageType']), 'reservation')) {
                    $rowPassedFilters = false;
                }

                if($rowPassedFilters) {
                    fputcsv($this->nextChunkFile, $data);  
                }

                unset($data);
                $i++;

                /* chunk file exceeds max size per chunk, get next chunk */
                if (fstat($this->nextChunkFile)['size'] >= self::MAX_CHUNK_FILE_SIZE) {
                    $this->setNextChunkFile($service['name'], $csvHeaders);
                }

                echo $trimmedCount . " rows trimmed\n";
                $trimmedCount++;
            }

            fclose($handle);
            fclose($this->nextChunkFile);
        }
    }

    /**
     * Import AWS EC2 pricing from single(or chunked) CSV file into the database
     *
     * @param string|array $csv File(s) name to import
     * 
     * @return void
     */
    public function importEC2Servers()
    {
        $ec2ChunkDir = self::IMPORT_CHUNK_DIR . 'ec2';

        if (Storage::exists($ec2ChunkDir)) {
            $ec2Chunks = Storage::files($ec2ChunkDir);

            //* import each chunk file then delete the file once imported
            foreach ($ec2Chunks as $chunkName) {
                $csvFile = Storage::path($chunkName);
            
                DB::transaction(function () use ($csvFile, $chunkName) {
                    (new SeedAmazonServers($csvFile))->run();

                    Storage::delete($chunkName);
                });
            }

            Storage::deleteDirectory($ec2ChunkDir);
            
        } else {
            DB::transaction(function () {
                (new SeedAmazonServers(Storage::path(self::IMPORT_DIR . 'ec2.csv')))
                    ->run();
            });
        }
    }

    /**
     * Import AWS RDS pricing from single(or chunked) CSV file into the database
     *
     * @param string|array $csv File(s) name to import
     * 
     * @return void
     */
    public function importRDSServers()
    {
        $rdsChunkDir = self::IMPORT_CHUNK_DIR . 'rds';

        if (Storage::exists($rdsChunkDir)) {
            $rdsChunks = Storage::files($rdsChunkDir);

            //* import each chunk file then delete the file once imported
            foreach ($rdsChunks as $chunkName) {
                $csvFile = Storage::path($chunkName);
            
                DB::transaction(function () use ($csvFile, $chunkName) {
                    (new SeedAmazonServersRDS($csvFile))->run();

                    Storage::delete($chunkName);
                });
            }

            Storage::deleteDirectory($rdsChunkDir);
            
        } else {
            DB::transaction(function () {
                (new SeedAmazonServersRDS(Storage::path(self::IMPORT_DIR . 'rds.csv')))
                    ->run();
            });
        }
    }
}
