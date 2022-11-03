<?php namespace App\Console\Commands\Scheduled;

use App\Console\Commands\Command;

interface ScheduledCommand {
    
    /**
     * Abstract method to be implemented. Called for the given domain
     *
     * Returns - The frequency the event should be run
     */
    public function getFrequency();
    
    /**
	 * Execute the command.
	 *
	 * @return void
	 */
    public function handle();

}
