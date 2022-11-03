<?php namespace App\Console\Commands;

use App\Console\Commands\Command;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;

class BuildCache extends Command {
    
    use DispatchesJobs;
    
    protected $path, $perPage, $query, $page, $maxPages, $records;
    
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct($path, $perPage, $query, $page = 1, $maxPages = 5) {
        $this->path = $path;
        
        $this->perPage = $perPage;
        
        $this->page = $page;
        
        $this->maxPages = $maxPages;
         
        $this->records = DB::select(DB::raw($query . " LIMIT " . $perPage * $maxPages . " OFFSET " . ($page - 1) * $perPage . ";")); 
	}
    
    protected function write($page, $data) {
        file_put_contents($this->path . $page . ".json", json_encode($data));
    }

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle() {
        if($this->maxPages < 1) {
            return;
        }
        
        if(!file_exists($this->path)) {
            mkdir($this->path, 0775, true);
        }
        
        for($i = 0; $i < $this->maxPages; $i++) {
            if (count($this->records) > $i * $this->perPage) {
                $collection = collect($this->records);
                $this->write($this->page + $i, $collection->slice($i * $this->perPage, $this->perPage));
            } else {
                break;
            }
        }
        
	}

}
