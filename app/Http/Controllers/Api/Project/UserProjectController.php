<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Software\SoftwareCost;
use App\Services\Currency\CurrencyConverter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use SVGGraph;
use App\Models\Project\Project;
use App\Models\Project\PrecisionUser;
use App\Models\Project\Log;
use App\Models\UserManagement\User;
use App\Models\Project\EnvironmentType;
use App\Models\Hardware\Manufacturer;
use App\Models\Software\Software;

class UserProjectController extends Controller {

    protected $model = "App\Models\Project\Project";
    protected $activity = 'Project Management';
    protected $table = 'projects';


    protected function index() {
        $projects = Project::all();

        return response()->json($projects);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $cache_key = sprintf('pit-user-projects-%s', $id);

        $memcached = $this->getMemcached();

        if ($result = $memcached->get($cache_key)) {
          return $result;
        }
        else {
          $project = Project::where(['user_id' => $id])
              ->get()
              ->each
              ->setAppends([]);

          $result = $project->toArray();
          $memcached->set($cache_key, $result, 300);

          return response()->json($result);
        }
    }

    protected function customers($id) {
        $customerList = DB::table('users')
            ->join('projects', 'users.id', '=', 'projects.user_id')
            ->where('users.id', '=', $id)
            ->select('projects.customer_name')
            ->distinct()
            ->get();

        //$customers = DB::table('users')->get();
        return $customerList;
    }

    protected function destroy($id) {
        if ($project = Project::find($id)) {
          $ckey = sprintf('pit-user-projects-%s', $project->user_id);
          $memcached = $this->getMemcached();
          $memcached->delete($ckey);
        }
        
        Project::destroy($id);
        return response()->json("Destory Successful");
    }

    private function softwareCostWithFeatures($software_id, $processor, $environment) {
        if(!$software_id) {
            return 0;
        }

        $modifier = (object) array('license_cost_discount' => 0);
        $softwareCost = null;
        foreach($environment->softwareCosts as $sc) {
            if($sc->software_type_id == $software_id) {
                $modifier->license_cost_discount = $sc->license_cost_modifier;
                $softwareCost = $sc;
                $software = $sc->software;
                break;
            }
        }
        //Otherwise, use the list price
        $license = $software->license_cost * ((100 - $modifier->license_cost_discount) / 100);
        $costPerMultiplier = 1;
        switch($software->cost_per) {
            case 'NUP':
                $costPerMultiplier *= $software->nup;
            case 'Core/vCPU':
                $costPerMultiplier *= $processor->core_qty;
                break;
            case 'Processor':
                $costPerMultiplier *= $processor->socket_qty;
                break;
            case 'Server':
                $costPerMultiplier *= $processor->servers;
            default:
                break;
        }
        $license *= $costPerMultiplier;
        if($software->softwareType->name == 'Database' ||
            $software->softwareType->name == 'Middleware' &&
            $software->multiplier != null)
            $license *= $software->multiplier;

        foreach($sc->featureCosts as $fc) {
            $feature = $fc->feature;
            $featureLicense = $feature->license_cost * ((100 - $fc->license_cost_discount) / 100);
            $costPerMultiplierF = 1;
            switch($feature->cost_per) {
                case 'NUP':
                    $costPerMultiplierF *= $feature->nup;
                case 'Core/vCPU':
                      $costPerMultiplier *= $processor->core_qty;
                      break;
                case 'Processor':
                    $costPerMultiplierF *= $processor->socket_qty;
                default:
                    break;
            }

            $featureLicense *= $costPerMultiplierF;
            $license += $featureLicense;
        }

        return $license;
    }

    private function softwareCostWithFeaturesOld($software_id, $processor, $env) {
        if(!$software_id)
            return 0;
        $cost = 0;
        foreach($env->softwareCosts as $sc) {
            if($sc->software_type_id == $software_id) {
                $cost += $sc->software->license_cost;
                foreach($sc->featureCosts as $fc) {
                    $cost += $fc->feature->license_cost;
                }
            }
        }
        return $cost;
    }

    private function checkSoftwareInEnvironment($software, $environment) {
        if(!$environment || !$software)
            return true;
        foreach($environment->softwareCosts as $cost) {
            if($cost->software->name == $software->name)
                return true;
        }
        return false;
    }

    private function updateMap($processor, $softwareId, $m, &$softwareMap, $qty, $existing) {
        if(!$softwareId)
            return;
        //Temporarily removed due to performance
        /*$s = Software::find($softwareId);
        //We don't care about the license cost if we already own the license
        if($this->checkSoftwareInEnvironment($s, $existing)) {
            return;
        }*/
        if (!$m) {
            $m = (object)["name" => "None"];
        }
        if (!Arr::exists($softwareMap, $m->name)) {
            $softwareMap[$m->name] = [];
        }

        if (!Arr::exists($softwareMap[$m->name], $softwareId)) {
            $core_qty = isset($processor->core_qty) ? $processor->core_qty : ($processor->total_cores / $processor->socket_qty);
            $softwareMap[$m->name][$softwareId] = (object)[
                "core_qty" => $core_qty * $processor->socket_qty * $qty,
                "socket_qty" => $processor->socket_qty * $qty,
                "servers" => $qty
            ];
        } else {
            $core_qty = isset($processor->core_qty) ? $processor->core_qty : ($processor->total_cores / $processor->socket_qty);
            $softwareMap[$m->name][$softwareId]->core_qty += $core_qty * $processor->socket_qty * $qty;
            $softwareMap[$m->name][$softwareId]->socket_qty += $processor->socket_qty * $qty;
            $softwareMap[$m->name][$softwareId]->servers += $qty;
        }

    }

    protected function buildProjectGraphs($ids) {
        $params = array();
        /*if($id) {
            $params['user_id'] = $id;
        }*/
        $numAnalysisResults = [];
        $revenueByType = [];
        $numAnalysis = 0;
        $totalRevenue = 0;
        $types = EnvironmentType::all();
        foreach($types as $type) {
            $numAnalysisResults[$type->name] = 0;
            $revenueByType[$type->name] = 0;
        }
        $manufacturerResults = [];
        $manufacturers = Manufacturer::all();
        foreach($manufacturers as $man) {
            $manufacturerResults[$man->name] = [];
            foreach($types as $type) {
                $manufacturerResults[$man->name][$type->name] = (object)['revenue' => 0, 'analyses' => 0];
            }
        }
        $query = Project::with('environments.environmentType', 'environments.softwareCosts.software');
        if($ids) {
            $query = $query->whereIn('user_id', $ids);
        }
        $projects = $query->get();
        foreach($projects as $project) {
            $existing = null;
            foreach($project->environments as $env) {
                if($env->is_existing)
                    $existing = $env;
                if($env->environmentType && !$env->is_existing) {
                    if($env->target_analysis) {
                        if($env->environmentType->name !== "Cloud") {
                            $numAnalysisResults[$env->environmentType->name] = $numAnalysisResults[$env->environmentType->name] ?
                                                                  $numAnalysisResults[$env->environmentType->name] + 1 : 1;
                            $numAnalysis++;
                            $analysis = json_decode($env->target_analysis);
                            $m = null;
                            $softwareMap = [];
                            foreach($analysis->consolidations as $con) {
                                foreach($con->targets as $tar) {
                                    if (!isset($tar->manufacturer)) continue;
                                    
                                    $cost = 0;

                                    $cost += isset($tar->acquisition_cost) ? $tar->acquisition_cost : 0;
                                    $m = $tar->manufacturer;
                                    if($tar->is_converged) {
                                        foreach($tar->configs as $conf) {
                                            $this->updateMap($conf->processor, $conf->os_id, $m, $softwareMap, $conf->qty, $existing);
                                            $this->updateMap($conf->processor, $conf->hypervisor_id, $m, $softwareMap, $conf->qty, $existing);
                                            $this->updateMap($conf->processor, $conf->middleware_id, $m, $softwareMap, $conf->qty, $existing);
                                            $this->updateMap($conf->processor, $conf->database_id, $m, $softwareMap, $conf->qty, $existing);
                                            /*$cost += $this->softwareCostWithFeatures($conf->os_id, $conf->processor, $env) * $conf->qty;
                                            $cost += $this->softwareCostWithFeatures($conf->hypervisor_id, $conf->processor, $env) * $conf->qty;
                                            $cost += $this->softwareCostWithFeatures($conf->middleware_id, $conf->processor, $env) * $conf->qty;
                                            $cost += $this->softwareCostWithFeatures($conf->database_id, $conf->processor, $env) * $conf->qty;*/
                                        }
                                    } else {
                                        $this->updateMap($tar->processor, $tar->os_id, $m, $softwareMap, 1, $existing);
                                        $this->updateMap($tar->processor, $tar->hypervisor_id, $m, $softwareMap, 1, $existing);
                                        $this->updateMap($tar->processor, $tar->middleware_id, $m, $softwareMap, 1, $existing);
                                        $this->updateMap($tar->processor, $tar->database_id, $m, $softwareMap, 1, $existing);
                                        /*$cost += $this->softwareCostWithFeatures($tar->os_id, $tar->processor, $env);
                                        $cost += $this->softwareCostWithFeatures($tar->hypervisor_id, $tar->processor, $env);
                                        $cost += $this->softwareCostWithFeatures($tar->middleware_id, $tar->processor, $env);
                                        $cost += $this->softwareCostWithFeatures($tar->database_id, $tar->processor, $env);*/
                                    }

                                    $revenueByType[$env->environmentType->name] += $cost;
                                    $totalRevenue += $cost;
                                    $manufacturerResults[$m->name][$env->environmentType->name]->revenue += $cost;
                                }
                            }
                            unset($analysis);

                            foreach($softwareMap as $manu => $map) {
                                $manuCost = 0;
//                                foreach($map as $software_id => $processor) {
//                                    $manuCost += $this->softwareCostWithFeatures($software_id, $processor, $env);
//                                }
                                $revenueByType[$env->environmentType->name] += $manuCost;
                                $totalRevenue += $manuCost;
                                $manufacturerResults[$manu][$env->environmentType->name]->revenue += $manuCost;
                            }

                            if($m)
                                $manufacturerResults[$m->name][$env->environmentType->name]->analyses++;
                        } else {
                            //AWS Stuff
                            $numAnalysisResults[$env->environmentType->name] = $numAnalysisResults[$env->environmentType->name] ?
                                                                  $numAnalysisResults[$env->environmentType->name] + 1 : 1;
                            $numAnalysis++;
                        }
                    }
                }
            }
        }
        $legend = [];
        foreach($numAnalysisResults as $name => $type) {
            $n = str_replace("+", "+\n", $name);
            $legend[] = $n;
        }
        $settings = array(
          "graph_title" => "Analyses by Target Type",
          //"legend_title" => $numAnalysis . " Total Analyses",
          "label" => array(200, 250, $numAnalysis . " Total Analyses", "font_size" => 20, "position" => 'top'),
          //"legend_title" => "$" . $totalRevenue,
          "legend_title_font_size" => 20,
          "graph_title_font_size" => 20,
          "back_stroke_width" => 0,
          "back_colour" => "none",
          "pad_bottom" => 30,
          "legend_entries" => $legend,
          "stroke_width" => 0,
          "legend_position" => "outer right 0 100",
          "legend_stroke_width" => 0,
          "legend_back_colour" => "none",
          "legend_shadow_opacity" => 0,
          "show_label_key" => false//,
          //"grid_division_v" => array(200000, .005)
        );
        $colors = array(
            'rgb(69,172,228)',
            'rgb(242,182,88)',
            'rgb(122,182,123)',
            'rgb(204,85,107)'
        );
        $graph = new SVGGraph(400, 250, $settings);
        $total = 0;
        foreach($numAnalysisResults as $res) {
            $total += $res;
        }
        if($total == 0) {
            $numAnalysisResults[""] = 1;
        }
        //$graph->Values($numAnalysisResults);
        $graph->Values($numAnalysisResults);
        $graph->Colours($colors);
        $analysesGraph = $graph->fetch('DonutGraph');

        $settings['graph_title'] = "Revenue by Target Type";
        $settings['label'] =  array(200, 250, "$" . number_format(round($totalRevenue/1000),0).'k', "font_size" => 20, "position" => 'top');
        //$settings['legend_title'] = "$" . round($totalRevenue/1000).'k';
        //$settings['pad_right'] = 100;
        $graph = new SVGGraph(400, 250, $settings);

        $total = 0;
        foreach($revenueByType as $res) {
            $total += $res;
        }
        if($total == 0) {
            $revenueByType[""] = 1;
        }

        $graph->Values($revenueByType);
        $graph->Colours($colors);
        $revenueGraph = $graph->fetch('DonutGraph');


        $values = array(
            array(),
            array()
        );
        foreach($manufacturerResults as $manName => $man) {
            foreach($man as $typeName => $type) {
                if($type->analyses > 0) {
                    /*$values[0][$manName . "\n" . $typeName] = $type->revenue;
                    $values[1][$manName . "\n" . $typeName] = $type->analyses;*/
                    if(isset($values[0][$manName]))
                        $values[0][$manName] += $type->revenue;
                    else
                        $values[0][$manName] = $type->revenue;
                    if(isset($values[1][$manName]))
                        $values[1][$manName] += $type->analyses;
                    else
                        $values[1][$manName] = $type->analyses;
                }
            }
        }
        $max = 0;
        foreach($values[1] as $analyses) {
            if($max < $analyses)
                $max = $analyses;
        }
        $division = $max == 0 ? 1 : ceil($max/10.0);

        $settings = array(
          "graph_title" => "Revenue by Manufacturer",
          "graph_title_font_size" => 20,
          "line_dataset" => 1,
          "dataset_axis" => array(0, 1),
          "back_stroke_width" => 0,
          "back_colour" => "none",
          "grid_division_v" => array(null, $division),
          //"show_axis_v" => false,
          //"show_axis_h" => false,
          "axis_text_callback_y" => array(function ($v) {
            $string = $v < 0 ? '-$' : '$';
            $val = abs(round($v/1000));
            if($val < 10000) {
                return $string . $val . 'k';
            } else {
                return $string . abs(round($val/1000)) . 'm';
            }
            //return $v < 0 ? '-$' . abs(round($v/1000)) . 'k' : '$'.round($v/1000) . 'k';
          }, function($v){ return $v;}),
          "show_grid_v" => false,
          //"show_axis_text_h" => false,
          "pad_bottom" => 60,
          //"axis_min_v" => array(0, min($savingPercent)),
          //"axis_max_v" => array(max($savingDollars), max($savingPercent)),
          "bar_width" => 20,
          "stroke_width" => 0,
          "legend_position" => "outer bottom 370 0",
          "legend_entries" => array('Revenue', 'Analyses'),
          "legend_stroke_width" => 0,
          "legend_back_color" => "none",
          "legend_shadow_opacity" => 0,
          "marker_size" => 0,
          "line_stroke_width" => 3//,
          //"grid_division_v" => array(200000, .005)
        );
        if($max == 0)
            $settings["axis_max_v"] = array(null, 1);
        $maxRev = 0;
        if(count($values[0]) == 0 && count($values[1]) == 0) {
            $values[0][""] = 0;
            $values[1][""] = 0;
            $settings["axis_max_v"] = array(100000, $settings["axis_max_v"][1]);
        }
        $graph = new SVGGraph(800, 300, $settings);



        $graph->Values($values);
        $graph->Colours($colors);
        $byManufacturerGraph = $graph->fetch('BarAndLineGraph');
        $graphs = (object)['analysesGraph' => $analysesGraph, 'revenueGraph' => $revenueGraph, 'byManufacturerGraph' => $byManufacturerGraph];
        return $graphs;
        //return response()->json($project->toArray());
    }


    protected function projectGraphs($id) {
        $ckey = sprintf('pit-user-project-graphs-%s', $id);
        $memcached = $this->getMemcached();
        if ($result = $memcached->get($ckey)) {
          return $result;
        }
        else {
          $ids = [$id];
          $graphs = $this->buildProjectGraphs($ids);
          $result = response()->json($graphs);
          $memcached->set($ckey, $result, 300);
          return $result;
        }
    }

    protected function dashboardInfo() {
        $groups = Auth::user()->user->groups;
        $isAdmin = false;
        foreach($groups as $g) {
            if($g->name == "Admin") {
                $isAdmin = true;
                break;
            }
        }
        if(!$isAdmin)
            return response()->json("Unauthorized", 401);
        $company = Request::input('company');
        if(!$company || $company == "All") {
            $graphs = $this->buildProjectGraphs(null);
        } else {
            $users = User::select('id')->where('company_id', '=', $company)->get();
            $ids = [];
            foreach($users as $u) {
                $ids[] = $u->id;
            }
            $graphs = $this->buildProjectGraphs($ids);
        }
        $active = User::whereNull('deleted_at')->where('suspended', '=', false)->count();
        $online = User::join('projects', 'projects.user_id', '=', 'users.id')
                      ->join('environments', 'environments.project_id', '=', 'projects.id')
                      ->where('environments.updated_at', '>=', \Carbon\Carbon::now()->subMinute(10))
                      ->orWhere('projects.updated_at', '>=', \Carbon\Carbon::now()->subMinute(10))
                      ->select('users.id')
                      ->distinct('users.id')
                      ->count('users.id');
        $info = (object)['graphs' => $graphs, 'activeUsers' => $active, 'onlineUsers' => $online];
        return response()->json($info);
    }

    protected function userCompanies() {
        $userCompanies = User::select('company')->distinct()->get();
        $companies = [];
        foreach($userCompanies as $uc) {
            if($uc->company) {
                $companies[] = $uc->company;
            }
        }
        return response()->json($companies);
    }

    protected function cloneProject($id) {
        $project = Project::find($id);

        $newProject = $project->replicate();
        $newProject->analysis_name = Request::input('name');
        
        // Copying to another user
        if (Request::input('email')) {
          $email = Request::input('email');

          // Translate email to user_id
          $found = FALSE;
          if ($copyTo = User::select('id')->where('email', 'like', $email)->orderBy('ytd_logins', 'desc')->get()) {
            foreach($copyTo as $u) {
              $newProject->user_id = $u->id;
              $found = TRUE;
              break;
            }
          }

          if (!$found) return response()->json("Bad Request", 400);
        }
        
        $newProject->save();
        
        // purge project list cache
        $ckey = sprintf('pit-user-projects-%s', $newProject->user_id);
        $memcached = $this->getMemcached();
        $memcached->delete($ckey);
        
        foreach($project->environments as $env) {
            $newEnv = $env->replicate();
            $newEnv->project_id = $newProject->id;
            $newEnv->target_analysis = null;
            $newEnv->save();
            $convIDMap = [];
            $physicalIDMap = [];
            /** @var ServerConfiguration $conf */
            foreach($env->serverConfigurations as $conf) {
                /** @var ServerConfiguration $newConf */
                $newConf = $conf->replicate();
                $newConf->environment_id = $newEnv->id;
                if($newConf->parent_configuration_id) {
                    $newConf->parent_configuration_id = $convIDMap[$newConf->parent_configuration_id];
                }
                if ($newConf->physical_configuration_id) {
                    $newConf->physical_configuration_id = $convIDMap[$newConf->physical_configuration_id];
                }
                $newConf->save();
                $convIDMap[$conf->id] = $newConf->id;
                $physicalIDMap[$conf->id] = $newConf->id;
            }
            /** @var SoftwareCost $cost */
            foreach($env->softwareCosts as $cost) {
                $newCost = $cost->replicate();
                $newCost->environment_id = $newEnv->id;
                $newCost->save();
                foreach($cost->featureCosts as $fc) {
                    $newFc = $fc->replicate();
                    $newFc->software_cost_id = $newCost->id;
                    $newFc->save();
                }
            }
        }
    }

    public function dashboardStats() {
        $groups = Auth::user()->user->groups;
        $isAdmin = false;
        foreach($groups as $g) {
            if($g->name == "Admin") {
                $isAdmin = true;
                break;
            }
        }
        if(!$isAdmin)
          return response()->json("Unauthorized", 401);

        $company = Request::input('company_id');
        $user = Request::input('user_id');
        $year = Request::input('year');
        $year = $year ? $year : date("Y");
        $differentYTDs = $year != date("Y");
        $userRows = PrecisionUser::with(['projects' => function($q) use($year) {
            $q->whereYear('updated_at', '=', $year);
        }]);
        /*$projectsToAnalyze = DB::table('users')
                                ->join('projects', 'users.id', '=', 'projects.user_id');*/
        /*if($year) {
            $projectsToAnalyze = $projectsToAnalyze->whereYear('projects.updated_at', $year);
        }*/
        if($user) {
            $userRows = $userRows->where('id', '=', $user);
        }
        if($company) {
            $userRows = $userRows->where('company_id', '=', $company);
        }

        $users = $userRows->get();
        $types = EnvironmentType::all();
        $manufacturers = Manufacturer::all();
        $mans = [];
        foreach($manufacturers as $man) {
            //$mans[$man->name] = (object)['revenue' => 0, 'analyses' => 0];
            if(!in_array($man->name, $mans)) {
                $mans[] = $man->name;
            }
        }
        $mans[] = 'AWS';
        foreach($users as $user) {
            $manufacturerResults = [];
            foreach($manufacturers as $man) {
                $manufacturerResults[$man->name] = (object)['revenue' => 0, 'analyses' => 0];
            }
            $manufacturerResults["AWS"] = (object)['revenue' => 0, 'analyses' => 0];
            $manufacturerResults["None"] = (object)['revenue' => 0, 'analyses' => 0];
            $user->numAnalysis = 0;
            $user->totalRevenue = 0;
            foreach($user->projects as $project) {
                $existing = null;
                foreach($project->environments as $env) {
                    if($env->is_existing)
                        $existing = $env;
                    if($env->environmentType && !$env->is_existing) {
                        if($env->target_analysis) {
                            $user->numAnalysis++;
                            if($env->environmentType->name !== "Cloud") {
                                $analysis = json_decode($env->target_analysis);
                                $m = null;
                                $softwareMap = [];
                                foreach($analysis->consolidations as $con) {
                                    foreach($con->targets as $tar) {
                                        $cost = 0;
                                        $cost += $tar->acquisition_cost;
                                        $m = $tar->manufacturer;
                                        if($tar->is_converged) {
                                            foreach($tar->configs as $conf) {
                                                $this->updateMap($conf->processor, $conf->os_id, $m, $softwareMap, $conf->qty, $existing);
                                                $this->updateMap($conf->processor, $conf->hypervisor_id, $m, $softwareMap, $conf->qty, $existing);
                                                $this->updateMap($conf->processor, $conf->middleware_id, $m, $softwareMap, $conf->qty, $existing);
                                                $this->updateMap($conf->processor, $conf->database_id, $m, $softwareMap, $conf->qty, $existing);
                                                /*$cost += $this->softwareCostWithFeatures($conf->os_id, $tar->processor, $env) * $conf->qty;
                                                $cost += $this->softwareCostWithFeatures($conf->hypervisor_id, $tar->processor, $env) * $conf->qty;
                                                $cost += $this->softwareCostWithFeatures($conf->middleware_id, $tar->processor, $env) * $conf->qty;
                                                $cost += $this->softwareCostWithFeatures($conf->database_id, $tar->processor, $env) * $conf->qty;*/
                                            }
                                        } else {
                                            $this->updateMap($tar->processor, $tar->os_id, $m, $softwareMap, 1, $existing);
                                            $this->updateMap($tar->processor, $tar->hypervisor_id, $m, $softwareMap, 1, $existing);
                                            $this->updateMap($tar->processor, $tar->middleware_id, $m, $softwareMap, 1, $existing);
                                            $this->updateMap($tar->processor, $tar->database_id, $m, $softwareMap, 1, $existing);
                                            /*$cost += $this->softwareCostWithFeatures($tar->os_id, $tar->processor, $env);
                                            $cost += $this->softwareCostWithFeatures($tar->hypervisor_id, $tar->processor, $env);
                                            $cost += $this->softwareCostWithFeatures($tar->middleware_id, $tar->processor, $env);
                                            $cost += $this->softwareCostWithFeatures($tar->database_id, $tar->processor, $env);*/
                                        }
                                        $user->totalRevenue += $cost;
                                        $manufacturerResults[$m->name]->revenue += $cost;
                                    }
                                }
                                unset($analysis);
                                foreach($softwareMap as $manu => $map) {
                                    $manuCost = 0;
                                    foreach($map as $software_id => $processor) {
                                        $manuCost += $this->softwareCostWithFeatures($software_id, $processor, $env);
                                    }
                                    $user->totalRevenue += $manuCost;
                                    $manufacturerResults[$manu]->revenue += $manuCost;
                                }
                                if($m)
                                    $manufacturerResults[$m->name]->analyses++;
                            } else {
                                //AWS Stuff
                                $m = (object)["name" => "AWS"];
                                if($m)
                                    $manufacturerResults[$m->name]->analyses++;
                            }
                        }
                    }
                }
            }
            $user->manufacturerResults = $manufacturerResults;
            $company = $user->companyObj ? $user->companyObj->name : '';
            $user->companyName = $company;
            unset($user->projects);
            if($differentYTDs) {
                $user->ytd_queries = Log::whereYear('created_at', '=', $year)->where('user_id', '=', $user->id)->where('log_type', '=', 'cpm_query')->count();
                $user->ytd_logins = Log::whereYear('created_at', '=', $year)->where('user_id', '=', $user->id)->where('log_type', '=', 'login')->count();
            }
            /*foreach($user->projects as $project) {
                foreach($project->environments as $env) {
                    unset($env->target_analysis);
                }
            }*/
            //print_r($company . ' ' . $user->firstName . ' ' . $user->lastName . ' '. $user->ytd_logins . ' ' . $user->ytd_queries . ' '. count($user->projects) .  '<br/>');
        }
        $existingMans = [];
        foreach($mans as $man) {
            if($this->totalAnalysesForManufacturer($users, $man) > 0)
                $existingMans[] = $man;
        }
        $maxYear = Project::orderBy('updated_at', 'desc')->first();
        $minYear = Project::orderBy('created_at', 'asc')->first();
        $year = date_parse($minYear->created_at);
        $minYear = $year['year'];
        $year = date_parse($maxYear->updated_at);
        $maxYear = $year['year'];
        $result = ['manufacturers' => $existingMans, 'users' => $users, 'minYear' => $minYear, 'maxYear' => $maxYear];
        return response()->json($result);
        //print_r($users);
    }

    private function totalAnalysesForManufacturer($users, $manufacturer) {
        $total = 0;
        foreach($users as $user) {
            $total += $user->manufacturerResults[$manufacturer]->analyses;
        }
        return $total;
    }

    public static function resetUserYTD() {
      $users = PrecisionUser::all();
      //Grab the current year
      $year = date("Y");
      //For each user
      foreach($users as $u) {
          //Set their YTD counts equal to the number of logs we have for the current year.
          $u->ytd_queries = Log::whereYear('created_at', '=', $year)->where('user_id', '=', $u->id)->where('log_type', '=', 'cpm_query')->count();
          $u->ytd_logins = Log::whereYear('created_at', '=', $year)->where('user_id', '=', $u->id)->where('log_type', '=', 'login')->count();
          $u->save();
      }
    }
}
