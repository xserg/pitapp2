<?php namespace App\Extensions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Console\Commands\Queued\ElasticIndex;
use App\Writers\Bucket;
use App\Writers\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Created By - rsegura - 6/3/2015
 * -- Resource Observer --
 * This Class is used to observe the resource model that
 * all database models will inherit. It will be used for
 * updating all rows in a database.
 */
class ElasticHandler {

    use DispatchesJobs;

    private $index;

    private static $table_name = 'elastic_search_queued';

    public function __construct($index = null) {
        $this->index = $index ?: strtolower(env('DB_DATABASE', "SmartStack"));

        $this->logger = new Logger(new Bucket('elasticIndex', new Bucket('foundation')));
    }

    /**
     * @param String $type Name of the table in the database.
     * @param int $id ID that is desired to be placed into elastic.
     * @param String $json JSON string of the object to be placed, literally.
     * @return boolean True if attempted to queue the object, false otherwise.
     */
    private function queue($type, $id, $json) {
        // Alteration swheeler 2016-04-20. Don't queue things if the table doesn't exist.
        if ($this->checkTable()) {
            DB::table(self::$table_name)->insert([
                   ['index' => $this->index, 'type' => $type, 'id' => $id, 'body' => $json]
               ]);

            // Alteration swheeler 2016-04-20 for #692. Check here, no need to pass it in.
            if($this->checkQueued()) {
                $this->dispatch(new ElasticIndex());
            }

            return true;
        }
        return false;
    }

    /**
     * @return boolean True if the ElasticIndex job is not already qeueued. False otherwise.
     */
    private function checkQueued() {
        //Check to see if an ElasticIndex job is already queued, and queue on up if there is not
        $job = DB::table('jobs')->where('payload', 'LIKE', "%ElasticIndex%")->count('id');
        return !$job;
    }

    /**
     * @return boolean True if the target table for queueing exists. False otherwise.
     */
    private function checkTable() {
        return Schema::hasTable(self::$table_name);
    }

    /**
     * @param Model $model entity to be updated and placed in elastic.
     * @return boolean True if successfully placed to be updated, false otherwise.
     */
    public function updated($model) {
        // Alteration swheeler 2016-04-20 for #692. Abort out if the table doesn't exist, why waste time?
        if (!$this->checkTable()) {
            return false;
        }

        $json = $model->toJSON();
        $type = $model->getTable();
        $id = $model->id;

        return $this->queue($type, $id, $json);
    }

    /**
     * @param Model $model entity to be updated and placed in elastic.
     * @return boolean True if successfully placed to be updated, false otherwise.
     */
    public function created($model) {
        // Alteration swheeler 2016-04-20 for #692. Abort out if the table doesn't exist, why waste time?
        if (!$this->checkTable()) {
            return false;
        }

        $model = $model->reload();
        $json = $model->toJSON();
        $type = $model->getTable();
        $id = $model->id;

        return $this->queue($type, $id, $json);
    }

    /**
     * @param Model $model entity to be updated and placed in elastic.
     * @return boolean True if successfully placed to be updated, false otherwise.
     */
    public function deleted($model) {
        // Alteration swheeler 2016-04-20 for #692. Abort out if the table doesn't exist, why waste time?
        if (!$this->checkTable()) {
            return false;
        }

        $json = $model->toJson();

        // We need to change the deleted_at column to something elasticsearch will understand
        $json = json_decode($json);

        // Just grab the date and time with no further information, as this is what elasticsearch expects
        $json->deleted_at = isset($json->deleted_at) && is_string($json->deleted_at)
                ? $json->deleted_at
                : (isset($json->deleted_at)
                        ? substr($json->deleted_at->date, 0, strpos($json->deleted_at->date, "."))
                        : null
                    );

        // Re-encode into json so we can queue this
        $json = json_encode($json);
        $type = $model->getTable();
        $id = $model->id;

        return $this->queue($type, $id, $json);
    }
}
