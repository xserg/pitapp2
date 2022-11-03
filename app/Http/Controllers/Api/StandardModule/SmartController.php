<?php namespace App\Http\Controllers\Api\StandardModule;

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use App\Http\Controllers\Controller;
use App\Models\StandardModule\Activity;
use Illuminate\Support\Facades\Request;

abstract class SmartController extends Controller {

	use DispatchesJobs, ValidatesRequests;

    protected $activity = 'test';
    protected $table = 'testTable';
    protected $cached = false;
    protected $model = "";
    
    protected function AuthFactory() {
        if(class_exists('\App\Http\Controllers\Api\AuthController')) {
            return '\App\Http\Controllers\Api\AuthController';
        } else {
            throw new \Exception('No auth implemented');
        }
    }


    protected function isAllowed() {
        $auth = $this->AuthFactory();
        if(!count(Activity::where('name', '=', $this->activity)->get())) {
            return response()->json("The requested activity does not exist. Please try again later.", 403);
        }
        return $auth::staticAuthorize(Activity::where('name', '=', $this->activity)->first()->id);
    }

    // Default is to use the JSON array
    protected function _index() {
        if($this->cached) {
            
            if($this->model == "") {
                abort(500, "No model is defined for controller " . get_class($this));
            }
            
            $model = new $this->model();
            
            if(env('ELASTIC_HOST') != null) {
                $params = array(
                    'host' => env('ELASTIC_HOST'),
                    'port' => env('ELASTIC_PORT', '9200'),
                    'index' => strtolower(env('DB_DATABASE')),
                    'type' => strtolower($model->getTable())
                );
                return response(array($params), 200);
            }
            
            $query = json_decode(Request::input('query'));
                     
            
            $hash = $model->getHash($query);
            $manifest = public_path() . "/cache/" . $this->table . "/" . $hash . "/manifest.json";
            
            $pageNo = Request::input('pageNo') !== null ? Request::input('pageNo') : 1;
            
            if(file_exists($manifest) && $pageNo === 1) {
                return response(array(file_get_contents($manifest)), 200);
            } else {
                return response(array($model->runQuery($query, $pageNo)), 200);
            }
        }
        $data = DB::table('index_caching')->where('entity_type', '=', $this->table)->lists('json');
        
        foreach($data as &$datum) {
            $datum = json_decode($datum);
        }
        
        return response()->json($data, 200);
    }
    
    /**
     * Abstract Methods - to be overridden in the child classes.
     */
    abstract protected function _store();
    abstract protected function _show($id);
    abstract protected function _update($id);
    abstract protected function _destroy($id);
    
    public function index() {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        //Uses func_get_args instead of default values due to index and _index
        //possibly being overloaded.
        if(func_num_args() < 1) {
            return $this->_index();
        } else {
            return $this->_index(func_get_arg(0));
        }
    }
    //Note: The default null IDs are there as extra arguments if a path requires them
    //Ex: Page versions: content/page/{page_id}/version/{version_id}
    //In the child class, they must still be declared the same as the abstract
    //but you can use func_get_arg to access the extra ID.
    public function store($id = null) {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        return $this->_store($id);
    }
    
    public function show($id) {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        if(func_num_args() < 2) {
            return $this->_show($id);
        } else {
            return $this->_show($id, func_get_arg(1));
        }
    }
    
    public function update($id, $id2 = null) {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        return $this->_update($id, $id2);
    }
    
    public function destroy($id, $id2 = null) {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        return $this->_destroy($id, $id2);
    }
    
}