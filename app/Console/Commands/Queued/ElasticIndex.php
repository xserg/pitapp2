<?php namespace App\Console\Commands\Queued;

use App\Console\Commands\Command;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Elasticsearch\ClientBuilder;
use App\Writers\Bucket;
use App\Writers\Logger;
use Illuminate\Support\Facades\DB;


class ElasticIndex extends Command implements ShouldQueue {
    
    private $index;

    private $type;
    
    private $id;
    
    private $body;
    
    private $hosts;
    
    private $client;
    
    private $bucket;
    
    private $logger;
    
    private static $DISCARD = 'discard';
    
    private static $QUEUED_TABLE = 'elastic_search_queued';
    
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
    public function __construct() {
        
        $hosts = env('ELASTIC_HOST');
        $hosts .= ':' . env('ELASTIC_PORT', '9200');
        
        $this->hosts = array(
            $hosts
            );
        
        $this->logger = new Logger(new Bucket('elasticIndex', new Bucket('foundation')));
	}
    
    /**
     * Marks the existing entities in the queue for discard, then adds them to the
     * elastic search index in batches of up to 100 records
     */
    private function chunkQueuedEntities() {
        
        // Mark the existing records for discard. This helps us avoid the edge case in which 
        // a record is inserted into the table during indexing, and prevents it being deleted.
        DB::table(self::$QUEUED_TABLE)->update([self::$DISCARD => 1]);

        // Now grab the records we just marked for deletion, and break them up into chunks of
        // 100 so that we can manage to process all of them in a single command.
        DB::table(self::$QUEUED_TABLE)->where(self::$DISCARD, '=', 1)->orderBy('id')->chunk(100, function($entities) {
            $params = [ 'body' => [] ];
        
            // Format each entity for elastic search
            foreach($entities as $entity) {
                
                $record = [
                        '_index' => $entity->index,
                        '_type' => $entity->type,
                        '_id' => $entity->id
                    ];
                //If we're set to remove this entity, set it as 'delete'
                if($entity->remove == true) {
                    $params['body'][] = [
                        'delete' => $record
                    ];
                    //Don't add any body if we are deleting.
                } else {
                    //If we want to index it, set it as 'index'
                    $params['body'][] = [
                        'index' => $record
                    ];
                    //Add the body since it's relevant for indexing
                    $params['body'][] = $entity->body;
                }
            }

            // Log the number of entities we're indexing. This is useful to make sure we maintain
            // high performance.
            $this->logger->log("Indexing or deleting " . count($entities) . " entities.");
            
            // Call the function that communicates with elastic search
            $this->index($params, $entities);
        });
    }
    
    private function index($params, $entities) {
        $client = ClientBuilder::create()->setHosts($this->hosts)->build();
        
        // Put this in a try catch block we we don't throw needless errors and fill up the error logs
        // on a production server. Also, it's just good practice
        try {
            $responses = $client->bulk($params);

            $this->logger->log("Entities successfully indexed or deleted. \n");
        } catch(\Exception $e) {
            $this->logger->log("There was an error indexing or deleting some models. See the response below for more details.");
            $this->logger->log($e->getMessage());
            
            //Make sure we unmark these entities for discard so that we can try to index them again later.
            foreach($entities as $entity) {
                $entity->discard = 0;
                DB::table(self::$QUEUED_TABLE)
                    ->where('primary', '=', $entity->primary)
                    ->update([self::$DISCARD => 0]);
            }
        }
    }
    
    private function deleteIndexedEntities() {
        DB::table('elastic_search_queued')->where('discard', '=', 1)->delete();
    }

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle() {
            
        $this->chunkQueuedEntities();
        
        $this->deleteIndexedEntities();
        
        return;
        
	}

}
