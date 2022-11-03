<?php

namespace App\Console\Commands\Import;

use App\Http\Controllers\Api\Project\EmailController;
use App\Models\Hardware\Manufacturer;
use App\Models\Hardware\OptimalTarget;
use App\Models\Hardware\Processor;
use App\Services\CsvImportService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OptimalTargetCommand extends Command
{
    /** A list of imported processor models */
    private array $processorModels = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:optimal-target';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse uploaded "optimal target" CSV file and import parsed data into the database.';

    const SUCCESS_MESSAGE = 'All optimal targets have been imported. Environment: ';
    const ERROR_MESSAGE = 'You need to upload a new optimal target file.';
    const EMAIL_SUBJECT = 'Optimal Target Import';
    const EMAIL_ERROR_MESSAGE = 'The optimal target import has failed. Upload new optimal target file.';
    const FILE_NAME = 'optimal-targets.csv';
    const FILE_NAME_PROCESSING = 'optimal-targets-new.csv';
    const FAILED_FILE_NAME = 'optimal-targets-failed.csv';

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
     * Parse uploaded CSV file and DB import parsed data
     *
     * Expected CSV Fields/Headers format:
     * - "Model"
     * - "Processor Type"
     * - "GHz"
     * - "# of Processors"
     * - "Cores/Processor"
     * - "Total Cores"
     * - "RAM"
     * - "CPM Value"
     * - "Total Server Cost"
     * 
     * @return bool
     */
    public function handle()
    {
        Log::info('Running `import:optimal-target` Command');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', -1);

        //* Check if there is a new spreadsheet to import and if there is an import currently happening
        $path = CsvImportService::IMPORT_DIR . self::FILE_NAME;
        $toProcess = CsvImportService::IMPORT_DIR . self::FILE_NAME_PROCESSING;

        if(!Storage::exists($path) || Storage::exists($toProcess)) {
            return false;
        }

        try {
            echo "Processing Optimal Target file... \n";
            Log::info(sprintf('Processing Optimal Target file: "%s"...', $toProcess));

            //* rename originally uploaded file
            Storage::move($path, $toProcess);

            DB::transaction(function () {
                echo "Deleting old optimal targets... \n";
                DB::table('optimal_targets')->truncate();
            });

            $this->import(Storage::path($toProcess));

            echo self::SUCCESS_MESSAGE . getenv('APP_ENV') . "\n";

            Log::info(self::SUCCESS_MESSAGE . getenv('APP_ENV'));

            $this->notifyImportStatus(
                self::EMAIL_SUBJECT,
                $this->formatStatusMessage(self::SUCCESS_MESSAGE, getenv('APP_ENV'))
            );

            Storage::delete($toProcess);

        } catch (\Throwable $e) {
            $status = $this->formatStatusMessage(
                self::EMAIL_ERROR_MESSAGE,
                getenv('APP_ENV'),
                $e->getMessage(),
                $e->getTraceAsString()
            );

            Log::error($e->getMessage() . "\n" . $e->getTraceAsString());

            Storage::copy(
                $toProcess,
                CsvImportService::IMPORT_DIR .  time() . '_' . self::FAILED_FILE_NAME
            );
            Storage::delete($toProcess);

            $this->notifyImportStatus(self::EMAIL_SUBJECT, $status);
           
            return false;
        }

        return true;
    }

    /**
     * Update list of manufacturers' available processor models
     * 
     * @return void
     */
    private function updateManufacturerModels(): void
    {
        foreach ($this->processorModels as $vendor => $models) {
            $manufacturer = Manufacturer::where('name', $vendor)->first();

            if ($models) {
                $manufacturer->processor_models = $models;
                
            } else {
                $manufacturer->processor_models = [];
            }

            $manufacturer->save();
        }
    }

    /**
     * Parse and import Optimal Target CSV
     *
     * @param string $path Path to Optimal Target CSV file
     *
     * @return void
     * @throws Exception
     */
    public function import($path)
    {
        $csvImportService = new CsvImportService();
        $issues = [];

        // Clear the optimal targets table
        OptimalTarget::truncate();

        //* parse and import optimal target csv file
        $importCount = 0;
        $rowCount = 0;
        $csvImportService->parseCsv($path, function ($row, $counter) use (&$issues, &$importCount, &$rowCount) {
            $model = $row['Model'];
            /* remove comma seperator from numeric values */
            $ram = trim(str_replace(',', '', $row['RAM']));
            $serverCost = trim(str_replace(',', '', $row['Total Server Cost']));

            /* import only rows where "RAM" and "Total Server Cost" can be
                     parsed as interger value equals to the original value */
            if ($ram == (int)$ram && $serverCost == (int)$serverCost) {
                $processor = Processor::where([
                    'name' => $row['Processor Type'],
                    'ghz' => $row['GHz'],
                    'socket_qty' => $row['# of Processors'],
                    'core_qty' => $row['Cores/Processor'],
                    // 'rpm' => $row['CPM Value']
                ])
                ->where('model_name', 'like', '%' . $model . '%')
                ->get();

                $count = $processor->count();
                
                if ($count == 0) { /* nothing is imported if no matched cpu found */
                    $issue = 'No results found.';

                } else {
                    $matchedProcessor = $processor->first();
                    $vendor = $matchedProcessor->manufacturer->name;

                    if (!isset($this->processorModels[$vendor])) {
                        $this->processorModels[$vendor] = [];
                    }

                    if ($count > 1 && $vendor != 'IBM') {
                        $issue = 'Multiple results found.';

                    } else {
                        OptimalTarget::firstOrCreate([
                            'processor_id' => $matchedProcessor->id,
                            'processor_model' => $model,
                            'ram' => $ram,
                            'total_server_cost' => $serverCost,
                        ]);

                        /* add model name to processor */
                        $matchedProcessor->model_name = $model;
                        $matchedProcessor->save();

                        $importCount += 1;
                    }

                    /* keep reference to imported processor models */                        
                    if (!empty($model) && !in_array($model, $this->processorModels[$vendor])) {
                        $this->processorModels[$vendor][] = $model;
                    }
                }

            } else {
                $issue = 'Invalid ram/cost.';
            }
            
            if (isset($issue)) {
                $issues[] = sprintf(
                    "%s(Model: %s) @row %s: %s\n",
                    $row['Processor Type'],
                    $model,
                    $counter + 1,
                    $issue
                );
            }

            $rowCount = $counter;
        });

        $this->updateManufacturerModels();
        
        echo sprintf("%s/%s were imported successfully\n", $importCount, $rowCount);

        if (count($issues) > 0) {
            throw new Exception("Invalid results:\n" . json_encode($issues));
        }
    }

    /**
     * Format import status error message
     *
     * @param string $message
     * @param string $environment
     * @param string $error
     * @param string $trace
     *
     * @return string
     */
    private function formatStatusMessage(string $message, string $environment, string $error = null, string $trace = null): string
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
       })->join("\n");
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
