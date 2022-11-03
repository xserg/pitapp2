<?php

namespace App\Console\Commands\Deployment;

use App\Services\Deployment;
use Illuminate\Console\Command;

class Register extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deployment:register';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register a new deployment';

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
     * @param Deployment $deploymentService
     * @return mixed
     */
    public function handle(Deployment $deploymentService)
    {
        $deploymentService->setLastDeployment();
        $this->info("Set new deployment: " . $deploymentService->getLastDeployment());
    }
}
