<?php namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use App\Http\Controllers\Controller;
use App\Models\StandardModule\Activity;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\Api\AuthController as Auth;

abstract class SmartController extends Controller {

	use DispatchesJobs, ValidatesRequests;
    
    protected $activity = 'test';
    protected $table = 'testTable';
    protected $cached = false;
    protected $model = "";


    protected function isAllowed() {
        return Auth::staticAuthorize(Activity::where('name', '=', $this->activity)->firstOrFail()->id);
    }

    // Default is to use the JSON array
    protected function _index() {
        if($this->cached) {
            
            if($this->model == "") {
                abort(500, "No model is defined for controller " . get_class($this));
            }
            
            $model = new $this->model();
            
            $query = json_decode(Request::input('query'));
                     
            
            $hash = $model->getHash($query);
            $manifest = public_path() . "/cache/" . $this->table . "/" . $hash . "/manifest.json";
            
            $pageNo = Request::input('pageNo') !== null ? Request::input('pageNo') : 1;
            
            exec(sprintf('mkdir -p cache/%s/%s', $this->table, $hash));
            
            if(file_exists($manifest) && $pageNo === 1) {
                return response(file_get_contents($manifest), 307);
            } else {
                return response($model->runQuery($query, $pageNo), 307);
            }
        }
        $data = DB::table('index_caching')->where('entity_type', '=', $this->table)->lists('json');
        
        return response()->json($data);
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
        
        return $this->_index();
    }
    
    public function store() {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        // run the controller menthod
        return $this->_store();
    }
    
    public function show($id) {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        return $this->_show($id);
    }
    
    public function update($id) {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        return $this->_update($id);
    }
    
    public function destroy($id) {
        // Auth
        if(!$this->isAllowed()) {
            return response()->json("You are not authorized to perform this function", 403);
        }
        
        return $this->_destroy($id);
    }
    
}