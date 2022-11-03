<?php

namespace App\Console\Commands\Pricing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\Project\EmailController;
use SeedIBMPVS_Compute;

class IBMPVS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pricing:ibmpvs';

    const SUCCESS_MESSAGE = 'The IBM PVS database has been seeded on ';

    const ERROR_MESSAGE = 'You need to upload a new IBM PVS pricing spreadsheet.';

    const FILE_NAME = 'ibmpvs-servers.csv';

    const FILE_PATH = '/storage/spreadsheet/';

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
     * Load & Update IBM PVS Pricing
     * @return string
     * @throws \Throwable
     */
    public function handle()
    {
        ini_set("max_execution_time", 0);
        ini_set("max_input_time", -1);

        $file = base_path() . IBMPVS::FILE_PATH . IBMPVS::FILE_NAME;
        $toProcess = base_path() . IBMPVS::FILE_PATH . 'new-' . IBMPVS::FILE_NAME;
        try {
            if (file_exists($file) && !file_exists($toProcess)) {
                Log::info("Processing ibmpvs file $file...");
                rename($file, $toProcess);
                $ibmpvsSeeder = new \SeedIBMPVS_Compute($toProcess);
                $ibmpvsSeeder->run();
                echo self::SUCCESS_MESSAGE . getenv('APP_ENV') . "\n";
                Log::info(self::SUCCESS_MESSAGE . getenv('APP_ENV'));
                $this->sendEmail(self::SUCCESS_MESSAGE . getenv('APP_ENV'));
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        } finally {
            if (file_exists($toProcess)) {
                unlink($toProcess);
            }
        }
        return true;
    }

    /**
     * @param string $body
     */
    private function sendEmail($body)
    {
        $emailController = new EmailController;
        $emailController->sendPricingConfirmationEmail("IBM PVS Pricing Confirmation", $body);
    }
}