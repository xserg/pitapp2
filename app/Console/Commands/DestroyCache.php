<?php namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;


class DestroyCache extends Command {
    
    protected $path;
    
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct($path) {
        $this->path = substr($path, strlen($path) - 1) === "/" ? $path : $path . "/";
	}

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle() {
        if(file_exists($this->path)) {
            foreach(scandir($this->path) as $file) {
                if($file !== "." && $file !== "..") {
                    unlink($this->path . $file);
                }
            }
            
            rmdir($this->path);
        }
	}

}
