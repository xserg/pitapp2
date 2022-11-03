<?php

use App\Console\Commands\Import\OptimalTargetCommand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class SeedOptimalTargets extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        try {
            $command = new OptimalTargetCommand();
            $command->import(__DIR__ . '/../data/Optimized_Target_Pricing_CSV.csv');
        } catch (Exception $e) {
            Log::warning($e->getMessage());
        }
    }
}
