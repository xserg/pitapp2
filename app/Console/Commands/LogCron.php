<?php

namespace App\Console\Commands;

use App\Services\ConfigData;
use App\Services\Deployment;
use Illuminate\Console\Command;

class LogCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Log Cron';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cronDate = date("Y-m-d H:i:s");
        file_put_contents(base_path() . '/storage/flags/cron.log', "This cron ran at {$cronDate}." . PHP_EOL, FILE_APPEND);
    }
}
