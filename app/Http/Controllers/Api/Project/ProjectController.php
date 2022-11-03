<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use App\Services\Currency\RatesServiceInterface;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Project;
use App\Models\Project\Environment;

class ProjectController extends Controller {

    protected $model = "App\Models\Project\Project";

    protected $activity = 'Project Management';
    protected $table = 'projects';

    // Private Method to set the data
    private function setData(&$project) {
        $validator = Validator::make(
            Request::all(),
            array(
                'title' => 'required|string|max:255',
                'customer_name' => 'required|string',
                'provider' => 'required|string|max:50',
                'user_id' => 'required|exists:users,id'
        ));

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return false;
        }

        // Set all the data here (fill in all the fields)
        $project->title = Request::input('title');
        $project->user_id = Request::input('user_id');
        $tempCagr = Request::input('cagr');
        $invalidateAnalysis = $tempCagr != $project->cagr;
        $project->cagr = Request::input('cagr');
        $project->customer_name = Request::input('customer_name');
        $project->provider = Request::input('provider');
        $project->description = Request::input('description');
        $project->support_years = Request::input('support_years');
        $project->comparison_type = Request::input('comparison_type');

        if(Request::has('analysis_name')){
          $project->analysis_name = Request::input('analysis_name');
        }
        if($invalidateAnalysis && $project->id) {
            $environments = Environment::where('project_id', '=', $project->id)->get();
            foreach($environments as $env) {
                $env->is_dirty = true;
                $env->save();
            }
        }

        $project->save();

        return $project;
    }

    protected function index() {
        $projects = Project::all();

        return response()->json($projects);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $project = Project::where('id', '=', $id)->with(['environments', 'user'])->first();
//        $project->logo = $project->local_logo;
        $project->user->defaultCompany;
        $project->owner_name = $project->user->firstName . ' ' . $project->user->lastName;
        foreach($project->environments as &$e) {
            global $targetAnalysisOnlyBool;
            $targetAnalysisOnlyBool = true;
        }
        if($project->logo && !starts_with($project->logo, 'api/'))
            $project->logo = 'api/'.$project->logo;
        //$project->environments;
        return response()->json($project->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        //return "store()";

        // Create item and set the data
        $project = new Project;
        if(!$this->setData($project)) {
            return response()->json($this->messages, 500);
        }
        // Purge cache
        else {
          $ckey = sprintf('pit-user-projects-%s', $project->user_id);
          $memcached = $this->getMemcached();
          $memcached->delete($ckey);
        }

        $comparison_type = Request::input('comparison_type');

          if($comparison_type == 0){
            $existing_environment = new Environment([
                'name' => 'Existing Environment',
                'is_existing' => 1,
                'cost_per_kwh' => 0.11
            ]);

            $project->environments()->save($existing_environment);
          }

        for ($x = 1; $x <= Request::input('num_targets'); ++$x)
        {
            $target_environment = new Environment([
                'name' => "Target $x",
                'is_existing' => 0
            ]);
            $project->environments()->save($target_environment);
        }
        $project->environments;
        return response()->json($project->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        //return "update($id)";

        // Retrieve the item and set the data
        $project = Project::find($id);
        if(!$this->setData($project)) {
            return response()->json($this->messages, 500);
        }
        $num_environments = count($project->environments);

        if($num_environments > 0){
          //If comparison type has been changed update names and isExisting
          if($project->comparison_type == 1 && $project->environments[0]->is_existing){
              $project->environments[0]->is_existing = 0;
              $project->environments[0]->save();

              for($i = 0; $i < $num_environments; ++$i){
                $project->environments[$i]->name = "Target " . ($i + 1);
                $project->environments[$i]->save();
              }
          }else if($project->comparison_type == 0 && !($project->environments[0]->is_existing)){


          for($i = 0; $i < $num_environments; ++$i){
            $project->environments[$i]->name = "Target $i";
            $project->environments[$i]->save();
          }

          $project->environments[0]->name = "Existing Environment";
          $project->environments[0]->is_existing = 1;
          $project->environments[0]->save();
         }
        }

        $num_targets = $project->comparison_type == 0 ? Request::input('num_targets') : Request::input('num_targets') - 1;

        //Add target environments until we have the desired number
        if ($num_targets + 1 > $num_environments) {
            $num_to_create = $num_targets + 1 - $num_environments;

            for ($x = 0; $x < $num_to_create; ++$x) {
                //$random_number = rand() % 10000;
                $target_num = $project->comparison_type == 0 ? $num_environments + $x : $num_environments + $x + 1;
                $target_environment = new Environment([
                    'name' => "Target $target_num",
                    'is_existing' => 0
                ]);
                $project->environments()->save($target_environment);
            }
        }

        //Delete target environments until we have the desired number
        else if ($num_targets + 1 < $num_environments) {
            $num_to_delete = $num_environments - ($num_targets + 1);
            $num_deleted = 0;
            $index = $num_environments - 1;

            while ($num_deleted < $num_to_delete) {
                if ($project->environments[$index]->is_existing == 0) {
                    $project->environments[$index]->delete();
                    $num_deleted += 1;
                }
                $index -= 1;
            }
        }


        return response()->json($project->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * @param RatesServiceInterface $currencyService
     * @param $projectId
     * @param $currencyCode
     */
    public function updateCurrency(RatesServiceInterface $currencyService, $projectId, $currencyCode)
    {
        if (key_exists($currencyCode, $currencyService->getSymbols())) {
            $project = Project::findOrFail($projectId);

            $project->update(['analysis_currency' => $currencyCode]);
        }
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {

        // Make the deletion
        Project::destroy($id);

        // return a success
        return response()->json("Destory Successful");
    }
}
