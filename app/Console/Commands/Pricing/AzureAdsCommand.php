<?php

namespace App\Console\Commands\Pricing;

use App\Http\Controllers\Api\Project\EmailController;
use App\Services\CsvImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class AzureAdsCommand extends Command
{
    const FILE_NAME = 'new-azure-databases.csv';
    const FILE_TO_IMPORT = 'azure-databases.csv';

    const EMAIL_SUBJECT = 'Azure SQL Pricing Confirmation';
    const MESSAGE_SUCCESS = 'The Azure databases have been seeded on ';
    const MESSAGE_ERROR = 'The Azure SQL pricing import failed - Please upload a new spreadsheet.';


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pricing:azure-ads';


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
     * Load & Update Azure SQL Pricing
     * 
     * @return bool
     */
    public function handle()
    {
        $newFile = CsvImportService::IMPORT_DIR . self::FILE_NAME;
        $fileToImport = CsvImportService::IMPORT_DIR . self::FILE_TO_IMPORT;

        if (Storage::exists($newFile) && !Storage::exists($fileToImport)) {
            Storage::move($newFile, $fileToImport);
        }

        ini_set('max_execution_time', 0);
        ini_set('max_input_time', -1);

        if (Storage::exists($fileToImport)) {
            $status = sprintf("Processing Azure SQL pricing file: %s...\n", $fileToImport);

            echo $status;
            Log::info($status);

            try {
                $this->importCsv($fileToImport);

                $status = self::MESSAGE_SUCCESS . env('APP_ENV');

                echo $status . "\n";

                Log::info($status);
                
                $this->notifyImportStatus(self::EMAIL_SUBJECT . ': Success', $status);

            } catch (\Throwable $e) {
                $status = self::MESSAGE_ERROR . env('APP_ENV');
                
                echo $status . "\n";

                Log::error($e->getMessage() . "\n" . $e->getTraceAsString());
    
                /* delete import file on failure */
                Storage::delete($fileToImport);

                $this->notifyImportStatus(self::EMAIL_SUBJECT . ': Error', $status);

                return false;
            }
        }

        return true;
    }

    /**
     * Import CSV pricing file
     *
     * @param string $file Path to import file - relative to app's storage directory
     *
     * @return void
     */
    private function importCsv(string $file)
    {
        $filePath = Storage::path($file);
    
        (new \SeedAzureAds($filePath))->run();

        Storage::delete($file);
    }

    /**
     * Send import status notification(email)
     * 
     * @param string $subject
     * @param string $body
     * 
     * @return void
     */
    private function notifyImportStatus(string $subject, string $body): void
    {
        (new EmailController)->sendPricingConfirmationEmail($subject , $body);
    }
}
