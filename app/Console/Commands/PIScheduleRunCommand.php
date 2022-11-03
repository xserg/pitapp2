<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use App\Console\Commands\CronFlag;

class PIScheduleRunCommand extends ScheduleRunCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pi:schedule:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run PI Scheduled commands';


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        
        // Check if other instance are running cron
        $commandFlag = new CronFlag();
        if (!$commandFlag->setFlag()) {
            return false;
        }

        $eventsRan = false;

        foreach ($this->schedule->dueEvents($this->laravel) as $event) {
            if (! $event->filtersPass($this->laravel)) {
                continue;
            }

            $this->line('<info>Running scheduled command:</info> '.$event->getSummaryForDisplay());

            $event->run($this->laravel);

            $eventsRan = true;
        }

        if (! $eventsRan) {
            $this->info('No scheduled commands are ready to run.');
        }
    }
}
