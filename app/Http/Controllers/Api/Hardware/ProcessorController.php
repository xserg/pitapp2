<?php

namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Hardware\Processor;
use App\Models\Hardware\Manufacturer;
use Illuminate\Support\Facades\DB;


class ProcessorController extends Controller {

    protected $model = "App\Models\Hardware\Processor";

    protected $activity = 'Processor Management';
    protected $table = 'processors';

    public function index() {
        $requestPage = Request::header('referer');
        $requestPage = strtolower($requestPage);
        $isAdminRequest = strpos($requestPage, 'admin') !== false;
        if(Request::input('query')) {
            $filter = Request::input('query');
            $filter = json_decode($filter);
            $name = isset($filter->name) ? $filter->name : '';
            $man = isset($filter->manufacturer_name) ? $filter->manufacturer_name : '';
            $processors = Processor::join('manufacturers', 'processors.manufacturer_id', '=', 'manufacturers.id')
                      ->select('processors.*', 'manufacturers.name as manufacturer_name')
                      ->where('processors.name', 'like', '%' . $name .'%')
                      ->where('manufacturers.name', 'like', '%' . $man .'%');
        } else {
            $processors = Processor::whereNotNull('id');
            if(Request::exists('manufacturer_id')){
                  $processors->where('manufacturer_id', '=', Request::input('manufacturer_id'));
            }
            if(Request::exists('distinct')){
               $processors->select('name', 'id')->distinct()->groupBy('name');

                if (!$isAdminRequest) {
                    $processors->where('model_name', '');
                }
               return response()->json($processors->get());
            }
            if(Request::exists('name')){
              $processors = DB::table('processors')->select('*')->where('name', '=', Request::input('name'))->get();

              if (!$isAdminRequest) {
                  $processors->where('model_name', '');
              }

              return response()->json($processors);
            }
        }


        //$processors = Processor::all();
        if(!$isAdminRequest) {
            $processors->where('model_name', '');
            $upload_path = '/core/hardware_cache/processors.json';
            if(!file_exists(public_path() . $upload_path)) {
                $this->cacheProcessors();
            } else {
                $mtime = filemtime(public_path() . $upload_path);
                $mDate = date('Y-m-d H:i:s', $mtime);
                $recache = Processor::where('updated_at', '>', $mDate)->first();
                if($recache) {
                    $this->cacheProcessors();
                }
            }
            $fp = fopen(public_path() . $upload_path, 'r');
            if(!headers_sent($filename, $linenum))
                header("Content-Type: text/json");
            fpassthru($fp);
            return;
            //return file_get_contents(public_path() . $upload_path);
            //$procs = $processors->select('id', 'name', 'ghz', 'core_qty', 'manufacturer_id', 'socket_qty')->with('manufacturer')->with('servers')->get();
        } else {
            $procs = $processors->with('manufacturer')->with('servers')->get();
        }
        /*for($i=0; $i<count($processors) ; $i++)
        {
            $processors[$i]->manufacturer;
        }*/
         return response()->json($procs->toArray());
    }


    protected function show($id){
        $processor = Processor::find($id);
        $processor->manufacturer;
        return response()->json($processor->toArray());
    }

    protected function store(){
         $this->validateData();

        $processor = new Processor;
        $id = $this->setData($processor);

        return response()->json($processor->toArray());
        //return response()->json(['id' => $id]);
    }

    protected function update($id) {
         $this->validateData();

        $processor = Processor::find($id);
        $this->setData($processor);

        return response()->json($processor->toArray());
        //return response()->json("Update Successful");
    }

    protected function destroy($id){
        Processor::destroy($id);
        //Since caching happens when we detect an update since the last time we cached during the index,
        //deletes won't register. Manually cache here.
        $this->cacheProcessors();
        return response()->json("Destroy Successful");
    }

    private function setData(&$processor){
        $processor->name = Request::input('name');
        $processor->rpm = Request::input('rpm');
        $processor->ghz = Request::input('ghz');
        $processor->core_qty = Request::input('core_qty');
        $processor->socket_qty = Request::input('socket_qty');
        $processor->announced_date = Request::input('announced_date');
        $processor->manufacturer_id = Request::input('manufacturer_id');
        $processor->pending_review = Request::exists('pending_review') ? Request::input('pending_review') : 0;
        $processor->architecture = Request::input('architecture');

        $processor->save();
        return $processor->id;
    }

     private function validateData(){
          $validator = Validator::make(
            Request::all(),
            array(
            'name' => 'required | string | max:50',
            'rpm' => 'required | integer',
            'ghz' => 'required | numeric',
            'core_qty' => 'required | integer',
            'socket_qty' => 'required | integer',
            'manufacturer_id' => 'required | integer',
            'announced_date' => 'date',
            'pending_review' => 'integer',
            'architecture' => 'string | max:50'
        ));

        if ($validator->fails()) {
            abort(400, 'Invalid input ' . json_encode($validator->errors()));
        }
    }

    private function cacheProcessors() {
        $procs = Processor::select('id', 'name', 'ghz', 'core_qty', 'manufacturer_id', 'socket_qty')
            ->where('model_name', '')
            ->with('manufacturer')
            ->with('servers')
            ->get();
        $procs = $procs->toArray();
        $procs = json_encode($procs);
        $upload_path = '/core/hardware_cache/';
        if(!is_dir(public_path() . $upload_path)) {
            mkdir(public_path() . $upload_path, 0755);
        }
        $fp = fopen(public_path() . $upload_path . 'processors.json', 'w');
        fwrite($fp, $procs);
        fclose($fp);
    }
}
