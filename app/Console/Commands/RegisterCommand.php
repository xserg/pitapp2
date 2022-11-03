<?php namespace App\Console\Commands;

use App\Console\Commands\Command;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * Register Command
 * Created By: rsegura 5/9/2015
 * Command that will register another command to the task list,
 * that will be used the the cron scheduler.
 */

class RegisterCommand extends Command implements ShouldQueue {

	use DispatchesJobs, SerializesModels;
    
    private $name = "";
    private $path = "";
    private $jobs = [];
    private $freq = 0;
    
    // Private method to open the json object and store it in jobs
    private function openFile() {
        if(!file_exists(dirname($this->path))) {
            mkdir(dirname($this->path), 0775, true);
        }
        if(file_exists($this->path)) {
            $file = file_get_contents($this->path);
            $data = json_decode($file);
        
            $this->jobs = $data->jobs;
            $this->freq = $data->tickCounter;
        }
    }
    
    // Private method to close and save the json object
    private function closeFile() {
        $string = json_encode(['tickCounter' => $this->freq, 'jobs' => $this->jobs]);
        file_put_contents($this->path, $string);
    }
        
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct($taskName) {
        $this->name = $taskName;
        $this->path = base_path() . "/tasks/tasks.json";
	}

    /**
     * Register - Method to allow various commands register themselves with the cron job.
     * @param string $task
     */
    public function register($task) {
        
        // if we don't find the job in the list then add it
        $collection = collect($this->jobs);
        if ($collection->search($task) === FALSE) {
            $this->jobs[] = $task;
        }
    }

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle() {
        // Open the file
        $this->openFile();
        
        // Register the Command
        $this->register($this->name);
        
        // Close and Save the file.
        $this->closeFile();
        
        return;
	}

}
