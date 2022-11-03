<?php namespace App\Extensions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use App\Models\StandardModule\ActivityLog;
use App\Models\StandardModule\ActivityLogType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Created By - rsegura - 6/3/2015
 * -- Resource Observer --
 * This Class is used to observe the resource model that
 * all database models will inherit. It will be used for
 * updating all rows in a database.
 */
class ActivityLogHandler {

    private function rrmdir($dir) {
       if (is_dir($dir)) {
         $objects = scandir($dir);
         foreach ($objects as $object) {
           if ($object != "." && $object != "..") {
             if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
           }
         }
         reset($objects);
         rmdir($dir);
       }
    }

    private function checkCacheAge($path) {
        $cacheTimeout = env('CACHE_KEEPALIVE', 10);
        if(file_exists($path) && is_dir($path)) {
            foreach(scandir($path) as $dir) {
                $manifest = $path . '/' . $dir . "/manifest.json";
                if(file_exists($manifest)) {
                    $json = json_decode(file_get_contents($manifest));
                    if(strtotime($json->lastQueried) < strtotime("-" . $cacheTimeout . " seconds")) {
                        $this->rrmdir($path . '/' . $dir);
                    }
                }
            }
        }

    }

    /**
     * Updating
     * Method called when the mdoel is being updated.
     *
     * Updated the entity_collection to be dirty.
     *
     * This should not use a model but raw sql.
     */
    public function updating($model) {
        // Update entity_collection
        DB::table('entity_collection')
            ->where('entity_name', '=', $model->getTable())
            ->update(['is_dirty' => true]);
    }

    /**
     * Updated
     * Method called when the model is saved. Grabs the json row
     * then encodes the model in JSON and stores it.
     *
     * This should not use a model but raw sql.
     */
    public function updated($model) {
        if(get_class($model) !== "App\Models\StandardModule\ActivityLog") {
//            $model = $model->reload();

            // Get the ID, Table, and JSON
            $id = $model->getKey();
            $table = $model->getTable();
            $json = $model->toJson();

            DB::table('index_caching')->where('entity_type', '=', $table)
                    ->where('entity_id', '=', $id)->update(['json' => $json]);
        }
    }

    /**
     * Creating
     * Method called when the model is being created.
     *
     * Create a new database entry in the entities collection
     * if one does not already exist.
     *
     * This should not use a model but raw sql.
     */
    public function creating($model) {
        // Query for the entity_collection
        $entity = DB::table('entity_collection')->where('entity_name', '=', $model->getTable())->first();

        // Check if the entity in the collection has been created
        if($entity === NULL) {
            // If is hasn't, then create it.
            DB::table('entity_collection')->insert(['entity_name' => $model->getTable(), 'is_dirty' => true]);
        }

        if($model->getDestroy()) {
            // Get the path
            $path = public_path() . '/cache/' . $model->getTable();

            // Recursively delete a directory
            $this->checkCacheAge($path);
        }
    }

    /**
     * Created
     * Method called when the model is created. Makes a new row
     * then encodes the model in JSON and stores it.
     *
     * This should not use a model but raw sql.
     */
    public function created($model) {
        if(get_class($model) !== "App\Models\StandardModule\ActivityLog") {
            $model = $model->reload();

            // Get the ID, Table, and JSON
            $id = $model->getKey();
            $table = $model->getTable();
            $json = $model->toJson();

            // We effectively want a replace into, so update if it already exists or insert new row.
            $existing = DB::table('index_caching')
                    ->where('entity_type','=',$table)
                    ->where('entity_id','=',$id)
                    ->get();

            if (is_array($existing) && count($existing) > 0) {
                DB::table('index_caching')
                    ->where('entity_type','=',$table)
                    ->where('entity_id','=',$id)
                    ->update(
                        ['entity_type' => $table, 'entity_id' => $id, 'json' => $json]
                );
            }
            else {
                DB::table('index_caching')->insert([
                    ['entity_type' => $table, 'entity_id' => $id, 'json' => $json]
                ]);
            }

        }
    }

    /**
     * Saving
     * Method called when the model is being saved
     *
     * Used for cleaning the cache files if they exsist
     */
    public function saving($model) {
        Session::put('logData', $model->getOriginal());

        if($model->getDestroy()) {

            // Get the path
            $path = public_path() . '/cache/' . $model->getTable();

            // Recursively delete a directory, if the cache is more than two minutes old
            $this->checkCacheAge($path);
        }
    }

    /**
     * Saved
     * Method called when the model is saved.
     *
     * Used for updating the activity log.
     */
    public function saved($model) {
        if(get_class($model) !== "App\Models\StandardModule\ActivityLog") {
            if(!isset($model->id)) {
                $model = $model->reload();
            }
            $type = ActivityLogType::where('key', '=', 'record-saved')->first();
            $log = $this->generateLog($model, $type);
            $log->message = count(Session::get('logData')) ? $model->logName() . " updated." : $model->logName() . " created";

            // Alteration swheeler 2016-03-29 for [656]. Use more accurate method array_diff_assoc.
            // Previous method would have failed if a new value matched an old value in a different column.
            // Also, break the method up into convinient chunks, using implode over multiple concats to save expensive concats.
            //Log the old data, the differences (two array_diff_assoc calls and then merge) and then the new data
            $detailed = ["Original values: "];
            // The original values.
            $detailed[] = json_encode(Session::get('logData'));
            $detailed[] = ". Changed values: ";
            // Generate the differences between the new and the old arrays.
            // Fetches anything that was deleted or changed.
            $collection1 = collect(Session::get('logData'));
            $diff = $collection1->diffAssoc($model->getAttributes());
            $deleted = $diff->all();
            // Fetches anything that is new or changed. By pulling, we delete the info from the session.
            $collection2 = collect($model->getAttributes());
            $diffAdded = $collection2->diffAssoc(Session::pull('logData'));
            $added = $diffAdded->all();
            // Merges the arrays, with changes showing their new value.
            $changes = collect($deleted)->merge(collect($added))->toArray();
            $detailed[] = json_encode($changes);
            $detailed[] = ". New attributes: ";
            // The new values.
            $detailed[] = json_encode($model->getAttributes());

            // Alteration swheeler 2016-09-16. Since it's being cut off anyways, truncate to column length.
            // This will allow us to behave better on databases with STRICT_TRANS_TABLES enabled.
            // Currently nothing actually uses this ENV variable, but it's around if needed.
            $maxLen = env('MAX_LOG_LENTH', 7000);
            $log->detailed = substr(implode('', $detailed), 0, $maxLen);

            $log->save();
        }
    }

    /**
     * Deleting
     * Method called when the model is being deleted
     *
     * Used for updating the activity log.
     */
    public function deleting($model) {
        if(get_class($model) !== "App\Models\StandardModule\ActivityLog") {
            $type = ActivityLogType::where('key', '=', 'record-deleted')->first();
            $log = $this->generateLog($model, $type);
            $log->message = $model->logName() . " deleted.";
            $log->detailed = $log->message . " " . date('Y-m-d H:i:s');
            $log->save();
        }

        if($model->getDestroy()) {
            // Get the path
            $path = public_path() . '/cache/' . $model->getTable();

            // Recursively delete a directory
            $this->checkCacheAge($path);
        }
    }

    /**
     * Restored
     * Method called when the model is restored. Used for updating the
     * activity log.
     */
    public function restored($model) {
        if(get_class($model) !== "App\Models\StandardModule\ActivityLog") {
            $type = ActivityLogType::where('key', '=', 'record-undeleted')->first();
            $log = $this->generateLog($model, $type);
            $log->message = $model->logName() . " undeleted.";
            $log->detailed = $log->message . " " . date('Y-m-d H:i:s');
            $log->save();
        }
    }

    // Private method to assist in generation of a log.
    private function generateLog($model, $type) {
        $log = new ActivityLog();
        //Modification. 7/9/2015 jdobrowolski. Changed to user the user id rather than the profile id
        $log->user_id = Auth::user() !== null ? Auth::user()->user_id : null;

        $log->model_name = get_class($model);
        $log->model_id = $model->id;
        $log->activityLogType_id = $type->id;

        if(method_exists($model, 'component')) {
            $log->component_id = $model->component();
        }

        return $log;
    }
}
