<?php

namespace App\Console\Commands;

use App\Services\ConfigData;
use App\Services\Deployment;
use Illuminate\Console\Command;

class Heartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cron heartbeat';

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
     * @param ConfigData $configService
     * @return mixed
     */
    public function handle(ConfigData $configService)
    {
        $heartbeatDate = date("Y-m-d H:i:s");
        $configService->setConfig('cron_heartbeat', $heartbeatDate);
        $this->info("Set cron heartbeat to>: {$heartbeatDate}");
    }
}
