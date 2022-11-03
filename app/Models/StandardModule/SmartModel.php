<?php namespace App\Models\StandardModule;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Extensions\ActivityLogHandler;
USE App\Extensions\ElasticHandler;
use App\Console\Commands\BuildCache;
use App\Console\Commands\DestroyCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * Created By - rsegura - 6/3/2015
 * -- Resource Model --
 * This is the abstract class to be implemented by any SmartStack model.
 * This class will be used to observe various events for creating and
 * updating to handle the json caching.
 */
abstract class SmartModel extends Model {
    use DispatchesJobs;
    
    /**
     * A list of columns that are indexed for filtering on the view list page, and how to compare the column. The list should be ordered, such that the most restrictive columns
     * are at the beginning of the array. I.e. if category returns 1/100 of the results, and site returns 1/3, the order of the array should
     * be [['category', '='], ['site', '=']]
     */
    protected $index = [];
    
    /**
     * A list of joins to be made. Each enty is an array following the form [table1table, table1column, operator, table2table, table2column]
     */
    protected $joins = [];
    
    /**
     * A list of columns to return to the viewList page.
     */
    protected $viewColumns = [];
    
    /**
     * An array of [$column, $order] tuples that are used to order the results that are returned from the server.
     */
    protected $orderBy = [];
    
    /**
     * Determines whether the model's cache files are destroyed with the database table is
     * written to. For write-intensive models, set this variable to false
     */
    protected $destroyOnWrite = true;
    
    /**
     * Sets the number of minutes a query is cached on the server
     */
    protected $cacheTimeout = 15;
    
    protected $numRecords;
    
    protected static $RECORDS_PER_PAGE = 20;
    
    /**
     * Returns true if the class uses soft deletes
     */
    protected function usesSoftDeletes() {
        return in_array('SoftDeletes', class_uses($this));
    }
    
    /**
     * -- Boot --
     * This method is called when the application comes online,
     * it registers the observer method.
     */
    public static function boot() {
        parent::boot();
        
        // Resource Model Events.
        self::observe(new ActivityLogHandler);
        if(env('ELASTIC_HOST') != null) {
            self::observe(new ElasticHandler);
        }
    }
    
    /**
     * Filters the model results according to query parameters
     */
    public static function filter($query, $size = null, $start = null) {
        
        $results = self::query();
        
        if(isset($query)) {
            foreach($query as $key => $value) {
                $results = $results->where($key, '=', $value);
            }
        }
        
        return $results->get();
    }
    
    // Default method to handle preparing JSON
    protected function prepareJson() {
        // Do nothing as default.
        return;
    }
    
    public function getDestroy() {
        return $this->destroyOnWrite;
    }
    
    // Override method to inject a prepare method.
    public function toJson($options = 0) {
        $this->prepareJson();
        return parent::toJson($options);
    }
    
    public function reload() {
        return $this->fresh();
    }
    
    public function viewList($query) {
        if(count($this->viewColumns) < 1) {
            throw new \Exception("Fatal Error: cannot run raw query with no columns selected.");
        }
        return DB::select(DB::raw($this->getQueryString($query)));
    }
    
    /**
     * Returns the query string generated from $query that has the fastest processing time on the database
     */
    protected function getQueryString($query) {
        $query = $this->prepare($query);
        
        return 'SELECT ' . $this->prepareColumns() . " FROM " . $this->getTable() . " " . $this->getJoins($this->prepareJoins()) . $this->getWheres($query) . $this->getOrder($query);
    }
    
    protected final function prepareColumns() {
        $dbs = env('DATABASE_DRIVER', 'mysql');
        if($dbs === 'postgres') {
            if(strpos($this->viewColumns[0], ".") <= 0) {
                return '"' . implode('","', $this->viewColumns) . '"';
            } else {
                //Now we have to iterate over each view column and format them properly
                $columns = array();
                foreach($this->viewColumns as $column) {
                    $dot = strpos($column, '.');
                    $space = strpos($column, " ");
                    $columns[] = substr($column, 0, $dot + 1) . '"' . substr($column, $dot + 1, $space - ($dot + 1)) . '"' . substr($column, $space);  
                }
                
                return implode(",", $columns);
            }
        }
        return implode(",", $this->viewColumns);
    }
    
    protected final function prepareJoins() {
        $dbs = env('DATABASE_DRIVER', 'mysql');
        if($dbs === 'postgres') {
            $joins = array();
            foreach($this->joins as $join) {
                $new = $join;
                $new[1] = '"' . $join[1] . '"';
                $new[4] = '"' . $join[4] . '"';
                $joins[] = $new;
            }
            return $joins;
        } else {
            return $this->joins;
        }
    }
    
    /**
     * Updates each query key by appending the correct table name to each
     * key. The default functionality simply returns the query as is
     */
    protected function prepare($query) {
        return (array) $query;
    }
    
    protected function getJoins($joins) {
        $query = "";
        
        foreach($joins as $join) {
            $query .= " LEFT JOIN " . $join[0] . " on " . $join[0] . "." . $join[1] . $join[2] . $join[3] . "." . $join[4];
        }
        
        return $query;
    }
    
    protected function getWheres($query) {

        Log::info('getWheres: ', [$query]);
        Log::info(gettype($query));

        $string = "";
        $first = true;
        
        //make sure to leave out deleted items
        $string .= " WHERE " . $this->getTable() . ".deleted_at IS NULL";
        
        foreach($this->index as $column) {
            if(isset($query[$column[0]])) {
                if(!$first) {
                    $caluse = (isset($column[2])) ? $column[2] : "AND";
                    $string .= " " . $caluse . " " . $column[0] . " " . $column[1] . " " . $query[$column[0]];
                } else {
                    $string .= " AND (" . $column[0] . " "  . $column[1] . " "  . $query[$column[0]];
                    $first = false;
                    $closeParens = true;
                }
            }
        }
        
        if( isset($closeParens) ) {
            $string .= " )";
        }
        
        return $string;
    }
    
    protected function getOrder($userSort = null) {
        $query = "";
        $first = true;
        // Look over and sort by whatever columns the user requests.
        // Any column on any table join to the model is potentially viable.
        // It also accepts the aliases present in the Model->viewColumns.
        if (isset($userSort['sort']) && $userSort['sort'] !== null) {
            // Alteration swheeler for #600. Change this loop to look over the new format,
            // which is designed to be dual with elastic's search format.
            foreach ($userSort['sort'] as $sorting) {
                foreach ($sorting as $column => $direction) {
                    if ($first) {
                        $first = false;
                        $query .= " ORDER BY " . $column . " " . $direction;
                    } else {
                        $query .= ", " . $column . " " . $direction;
                    }
                }
            }
        }
        // Sub order by the default ordering columns. If the user requested columns
        // are also default ordering, then MySQL uses only the first (user) sort ordering.
        foreach ($this->orderBy as $order) {
            if ($first) {
                $first = false;
                $query .= " ORDER BY " . $order[0] . " " . $order[1];
            } else {
                $query .= ", " . $order[0] . " " . $order[1];
            }
        }
        
        return $query;
    }
    
    /**
     * Returns the hash generated from the query string that is created from $query
     */
    public function getHash($query) {
        return hash('sha256', $this->getQueryString($query));
    }
    
    public function runQuery($query, $pageNo = 1) {

        Log::info('Run Query: ', [$query]);
        Log::info(gettype($query));
        
        $hash = $this->getHash($query);
        $path = public_path() . "/cache/" . $this->getTable() . "/" . $hash . "/";
        
        $raw = $this->getQueryString($query);
        
        $this->dispatch(new BuildCache($path, self::$RECORDS_PER_PAGE, $raw, $pageNo));
        
        if(!file_exists($path . "manifest.json")) {
            $pathDir = $path;
            if (substr($path, -1) == '/') {
                $pathDir = substr($path, 0, -1);
            }
            if (!is_dir($pathDir)) {
                mkdir($pathDir, 0755, true);
            }
            
            //Queue the job to delete the cache for time out classes when we create the manifest
            if(!$this->destroyOnWrite) {
                $date = Carbon::now()->addMinutes($this->cacheTimeout);
                Queue::later($date, new DestroyCache($path));
            }
            
            $numRecords = $this->getNumRecords($raw);
        
            $manifestData = [
                'path'          => "cache/" . $this->getTable() . "/" . $hash . "/",
                'numPages'      => $numRecords % self::$RECORDS_PER_PAGE === 0 ? intval($numRecords / self::$RECORDS_PER_PAGE) : intval(($numRecords / self::$RECORDS_PER_PAGE) +1),
                'lastQueried'   => date('Y-m-d H:i:s')
            ];
            
            exec(sprintf('mkdir -p %s', $path));
            file_put_contents($path . "manifest.json", json_encode($manifestData));
            
            return json_encode($manifestData);
        } else {
            return file_get_contents($path . "manifest.json");
        }
    }
    
    protected function getNumRecords($query) {
        if(strpos($query, "ORDER") > 0) {
            $new = "SELECT count(" . $this->getTable() . ".id) as numRecords " . substr($query, strpos($query, "FROM"), (strpos($query, "ORDER") - strpos($query, "FROM")));
        } else {
            $new = "SELECT count(" . $this->getTable() . ".id) as numRecords " . substr($query, strpos($query, "FROM"));
        }
        
        $results = DB::select(DB::raw($new . ";"));
        
        return isset($results[0]->numrecords) ? $results[0]->numrecords : $results[0]->numRecords;
    }
    
//    protected function paginate($query, $offset, $count) {
//        return DB::select(DB::raw($query . " LIMIT " . $offset . ", " . $count . ";"));
//    }
    
    /**
     * Returns the model attribute to use in logging function.
     *
     */
    public abstract function logName();
    
    /**
     * Returns the id of the model's component
     *
     */
    public abstract function component();
}
