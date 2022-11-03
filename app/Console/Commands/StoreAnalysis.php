<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Project\TargetAnalysisController;
use App\Services\Revenue;
use Illuminate\Console\Command;

class StoreAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:analysis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        /** @var Revenue $revenueService */
        $revenueService = resolve(Revenue::class);
        $revenueService->regenerate($this);
    }
}
