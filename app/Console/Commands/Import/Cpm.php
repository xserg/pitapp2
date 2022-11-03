<?php

namespace App\Console\Commands\Import;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\Project\EmailController;
use App\Services\CpmImport;

class Cpm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:cpm';

    const SUCCESS_MESSAGE = 'The CPM file has been imported ';

    const ERROR_MESSAGE = 'You need to upload a new CPM import file.';

    const EMAIL_ERROR_MESSAGE = 'The CPM import has been failed. You need to upload a new CPM import file.';

    const FILE_NAME = 'cpm-import.csv';

    const FILE_PATH = '/storage/';

    const PROCESS_FILE_NAME = 'cpm-import-new.csv';
    const FAILED_FILE_NAME = 'cpm-import-failed.csv';

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
     * Load & Update CPM values
     * @return string
     * @throws \Throwable
     */
    public function handle()
    {
        Log::info("Running CPM Command");
        ini_set("max_execution_time", 0);
        ini_set("max_input_time", -1);

        // Check if there is a new spreadsheet to import and if there is an import currently happening
        $path = base_path() . self::FILE_PATH . self::FILE_NAME;
        $toProcess = base_path() . self::FILE_PATH . self::PROCESS_FILE_NAME;
        if(!file_exists($path) || file_exists($toProcess)) {
            return false;
        }

        try {
            rename($path, $toProcess);
            Log::info("Processing CPM file $toProcess...");
            $cpmImport = new CpmImport();

            if ($cpmImport->import($toProcess)) {
                echo self::SUCCESS_MESSAGE . getenv('APP_ENV') . "\n";

                Log::info(self::SUCCESS_MESSAGE . getenv('APP_ENV'));

                $this->sendEmail($this->getFormattedErrorMessage(
                    self::SUCCESS_MESSAGE,
                    getenv('APP_ENV')
                ));
            }

            unlink($toProcess);
        } catch (\Throwable $e) {
            Log::error($e->getMessage() . "\n" . $e->getTraceAsString());

            copy($toProcess, base_path() . self::FILE_PATH . self::FAILED_FILE_NAME);
            unlink($toProcess);

            $this->sendEmail($this->getFormattedErrorMessage(
                self::EMAIL_ERROR_MESSAGE,
                getenv('APP_ENV'),
                $e->getMessage(),
                $e->getTraceAsString()
            ));

            return false;
        }

        return true;
    }

    /**
     * @param string $body
     */
    private function sendEmail($body)
    {
        $emailController = new EmailController;
        $emailController->sendPricingConfirmationEmail("CPM Import", $body);
    }

    private function getFormattedErrorMessage($message, $environment, $error = null, $trace = null)
    {
       return collect([
           'Message' => $message,
           'Error' => $error,
           'Trace' => $trace,
           'Environment' => $environment
       ])->filter(function($value) {
           return !empty($value);
       })->map(function($value, $key) {
           return $key . ': ' . $value;
       })->join('\n');
    }
}