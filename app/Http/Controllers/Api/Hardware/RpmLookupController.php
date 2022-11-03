<?php


namespace App\Http\Controllers\Api\Hardware;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use App\Models\Hardware\Server;
use App\Models\Hardware\Manufacturer;
use App\Models\Hardware\Processor;
use App\Models\Project\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RpmLookupController extends Controller
{
    /**
     * Send back some meaningful data about the CPM values
     * of the processor, and optionally of the servers associated to it.
     * The server bus / architecture can affect the CPM
     * @return \Illuminate\Http\JsonResponse
     */
  public function index()
  {
      $payload = [];

      $query = Processor::leftJoin('manufacturers', 'manufacturers.id', '=', 'processors.manufacturer_id')
          ->leftJoin('server_processors', 'server_processors.processor_id', '=', 'processors.id')
          ->whereNull('processors.user_id')
          // Exclude the explicit "default" entries from being returned in this lookup tool
          ->select('processors.*', DB::raw('(`processors`.`socket_qty` * `processors`.`core_qty`) as `total_cores`'));

      if (Request::has('filter')){
          /*
           * This block is for the multi-field "keyword" field
           */
          $filter = Request::input('filter');
          $filter = str_ireplace("power ", "power", $filter);
          $words = explode(' ', $filter);

          foreach ($words as $word) {
              $query->where(function ($query) use ($word) {
                  $query->where('processors.name', 'like', '%' . $word . '%')
                      ->orWhere('processors.model_name', 'like', '%' . $word . '%')
                      ->orWhere('manufacturers.name', 'like', '%' . $word . '%');
                  if (is_numeric($word))
                      $query->orWhere('processors.socket_qty', '=', $word)
                          ->orWhere('processors.core_qty', '=', $word)
                          ->orWhere('processors.ghz', '=', $word);
              });
          }
      }

      if (Request::exists('processor')) {
          /**
           * This block is for specific processor queries
           */
          $processor = Request::input('processor');
          $processor = str_ireplace("power ", "power", $processor);
          $manufacturer = Request::input('manufacturer');
          $model = Request::input('model');
          $ghz = Request::input('ghz');
          $socket_qty = Request::input('socket_qty');
          $core_qty = Request::input('core_qty');
          $total_cores = Request::input('total_cores');

          // Strip out defaults on front
          //$query->where('processors.is_default', 0);

          if ($processor) {
              $query->where('processors.name', 'like', '%' . $processor . '%');
          }
          if ($manufacturer) {
              $query->where('manufacturers.name', 'like', '%' . $manufacturer . '%');
          }
          if ($model) {
              $query->where('processors.model_name', 'like', '%' . $model . '%');
          }
          if ($socket_qty) {
              $query->where('processors.socket_qty', '=', $socket_qty);
          }
          if ($core_qty) {
              $query->where('processors.core_qty', '=', $core_qty);
          }
          if ($total_cores) {
              $query->having('total_cores', '=', $total_cores);
          }
          if ($ghz) {
              $query->where('processors.ghz', '=', $ghz);
          }
      }

      $query->orderBy('processors.name')->orderBy('processors.ghz')->orderBy('processors.socket_qty');
      $queryResult = $query->orderBy('processors.name')->limit(10)->get();
      foreach ($queryResult as $proc)
      {
          $proc->processor = clone $proc;
          $proc->manufacturer;
          if($proc->model_name){
              // We have to find the model by looking up based on name and
              // manufacturer id
              $proc->server = Server::where('name', $proc->model_name)
                  ->where('manufacturer_id', $proc->manufacturer_id)
                  ->whereNull('user_id')
                  ->first();
          }
      }
      $payload['query'] = $queryResult;
      if(Request::input('library') == true) {
          Auth::user()->user->ytd_queries++;
          Auth::user()->user->save();

          $log = new Log();
          $log->user_id = Auth::user()->user->id;
          $log->log_type = "cpm_query";
          $log->save();
      }


      return response()->json($payload);
  }

    protected function oldIndex(DB $db)
    {
      $payload = [];
      $query = Processor::leftJoin('manufacturers', 'manufacturers.id', '=', 'processors.manufacturer_id')
              ->leftJoin('server_processors', 'server_processors.processor_id', '=', 'processors.id')
              ->leftJoin('servers', 'server_processors.server_id', '=', 'servers.id')
              ->whereNull('processors.user_id')

              ->select('processors.*', DB::raw('(`processors`.`socket_qty` * `processors`.`core_qty`) as `total_cores`'), 'servers.id as server_id');


      if (Request::has('filter')){
        $filter = Request::input('filter');
        $filter = str_ireplace("power 5", "power5", $filter);
        $filter = str_ireplace("power 6", "power6", $filter);
        $filter = str_ireplace("power 7", "power7", $filter);
        $filter = str_ireplace("power 8", "power8", $filter);
          $filter = str_ireplace("power 9", "power8", $filter);
        $words = explode(' ', $filter);

        foreach($words as $word){
            $query->where(function($query) use($word){
              $query->where('processors.name', 'like', '%' . $word .'%' )
                  ->orWhere('servers.name', 'like', '%' . $word .'%' )
                  ->orWhere('manufacturers.name', 'like', '%' . $word .'%' );
              if(is_numeric($word))
                  $query->orWhere('processors.socket_qty', '=', $word)
                      ->orWhere('processors.core_qty', '=', $word)
                      ->orWhere('processors.ghz', '=', $word);
            });
        }
      }
      if(Request::exists('processor')) {
        $processor = Request::input('processor');
        $processor = str_ireplace("power ", "power", $processor);
        $model = Request::input('model');
        $ghz = Request::input('ghz');
        $socket_qty = Request::input('socket_qty');
        $core_qty = Request::input('core_qty');
        $total_cores = Request::input('total_cores');
        if($processor)
            $query->where('processors.name', 'like', '%' . $processor .'%' );
        if($model)
            $query->where('servers.name', 'like', '%' . $model .'%' );
        /*if($processor)
            $query->where('manufacturers.name', 'like', '%' . $processor .'%' );*/
        if($socket_qty)
            $query->where('processors.socket_qty', '=', $socket_qty );
        if($core_qty)
            $query->where('processors.core_qty', '=', $core_qty );
        if($total_cores)
            $query->having('total_cores', '=', $total_cores );
        if($ghz)
            $query->where('processors.ghz', '=', $ghz );
      }
    //$query = ServerConfiguration::with(['manufacturer', 'processor', 'server', 'environment']);
    //  $queryResult = $table->orderBy('processors.name')->limit(10)->get();

      $query->orderBy('processors.name')->orderBy('processors.ghz')->orderBy('processors.socket_qty');
      $queryResult = $query->orderBy('processors.name')->limit(10)->get();
      foreach ($queryResult as $proc)
      {
          $proc->processor = clone $proc;
          $proc->manufacturer;// = App\Models\Hardware\Manufacturer::find($scs[$i]->manufacturer_id);
          if($proc->server_id){
            $proc->server = Server::find($proc->server_id);
          }
      }
      $payload['query'] = $queryResult;
      if(Request::input('library') == true) {
          Auth::user()->user->ytd_queries++;
          Auth::user()->user->save();

          $log = new Log();
          $log->user_id = Auth::user()->user->id;
          $log->log_type = "cpm_query";
          $log->save();
      }


      return response()->json($payload);
  }

}
