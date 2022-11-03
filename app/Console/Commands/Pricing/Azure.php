<?php

namespace App\Console\Commands\Pricing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\Project\EmailController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SeedAzureServers;

class Azure extends Command
{
    const FILE_NAME = 'new-azure-servers.csv';
    const FILE_PATH = '/storage/app/spreadsheet/azure/';
    const IMPORT_DIR = 'spreadsheet/azure/';
    const IMPORT_FILE = 'spreadsheet/azure/azure-servers.csv';

    const MESSAGE_SUCCESS = 'The Azure servers have been seeded on ';
    const MESSAGE_ERROR = 'Azure servers import failed - Please upload a new Azure server pricing spreadsheet.';
    const EMAIL_SUBJECT = 'Azure Pricing Confirmation';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pricing:azure';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Azure servers from pricing data spreadsheet.';

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
     * Load & Update Azure Pricing
     * 
     * @return string
     * 
     * @throws \Throwable
     */
    public function handle()
    {
        $newFile = self::IMPORT_DIR . self::FILE_NAME;

        if (Storage::exists($newFile) && !Storage::exists(self::IMPORT_FILE)) {
            Storage::move($newFile, self::IMPORT_FILE);
        }

        ini_set('max_execution_time', 0);
        ini_set('max_input_time', -1);

        if (Storage::exists(self::IMPORT_FILE)) {
            Log::info('Processing azure servers spreadsheet...');

            try {
                $this->importCsv(self::IMPORT_FILE);

                Log::info(self::MESSAGE_SUCCESS . getenv('APP_ENV'));
                
                $this->sendEmail(
                    self::EMAIL_SUBJECT . ': Success',
                    self::MESSAGE_SUCCESS . getenv('APP_ENV')
                );

            } catch (\Throwable $e) {
                Log::error($e->getMessage() . "\n" . $e->getTraceAsString());

                $this->sendEmail(
                    self::EMAIL_SUBJECT . ': Error',
                    $e->getMessage() . getenv('APP_ENV')
                );

                return false;
            }
        }

        return true;
    }

    private function importCsv(string $file)
    {
        $filePath = Storage::path($file);
    
        DB::transaction(function () use ($filePath, $file) {
            (new SeedAzureServers($filePath))->run();

            Storage::delete($file);
        });
    }

    /**
     * Send import status email
     * 
     * @param string $subject
     * @param string $body
     * 
     * @return void
     */
    private function sendEmail(string $subject, string $body): void
    {
        (new EmailController)->sendPricingConfirmationEmail($subject , $body);
    }
}
