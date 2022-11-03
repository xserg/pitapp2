<?php

namespace App\Console;

use App\Console\Commands\{CompanyEmails,
    Deployment\Register,
    Import\Cpm,
    Import\OptimalTargetCommand,
    Pricing\AWS,
    Pricing\AWSInitialImportCommand,
    Pricing\Azure,
    Pricing\AzureAdsCommand,
    Pricing\Google,
    Pricing\IBMPVS,
    CompanyEmailsTest,
    GenerateRevenueReport,
    Heartbeat,
    LogCron,
    PIScheduleRunCommand,
    StoreAnalysis};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Register::class,
        CompanyEmails::class,
        CompanyEmailsTest::class,
        PIScheduleRunCommand::class,
        LogCron::class,
        StoreAnalysis::class,
        GenerateRevenueReport::class,
        Heartbeat::class,
        Google::class,
        IBMPVS::class,
        AWS::class,
        AWSInitialImportCommand::class,
        Azure::class,
        AzureAdsCommand::class,
        Cpm::class,
        OptimalTargetCommand::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (!boolval(env("DISABLE_SCHEDULER"))) {
            //* schedule initial aws import
            // $schedule->command('pricing:aws-initial-import')
            //     ->everyThirtyMinutes()
            //     ->withoutOverlapping();

            $schedule->command('pricing:aws')
                ->cron('0 7 * * 0');

            $schedule->command('heartbeat')
                ->cron('* * * * *');

            $schedule->command('email:companies')
                ->cron('0 6 * * *');

            $schedule->command('pricing:azure')
                ->cron('* * * * *')
                ->withoutOverlapping();

            $schedule->command('pricing:google')
                ->cron('* * * * *');

            $schedule->command('pricing:ibmpvs')
                ->cron('* * * * *');

            $schedule->command('pricing:azure-ads')
                ->cron('* * * * *');

            $schedule->command('import:cpm')
                ->cron('* * * * *');

            $schedule->command('import:optimal-target')
                ->cron('* * * * *');                
        }
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
//        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
