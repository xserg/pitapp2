<?php


namespace App\Http\Controllers\Api\OptimalTarget;


use App\Models\Hardware\Manufacturer;
use App\Models\Hardware\OptimalTarget;
use App\Models\Hardware\Server;
use App\Models\Project\Project;
use App\Models\Software\Feature;
use App\Models\Software\Software;
use App\Models\Software\SoftwareFeature;
use App\Models\Software\SoftwareType;
use App\Services\OptimalTarget\OptimalTargetAlgorithm;
use App\Services\OptimalTarget\OptimalTargetConfiguration;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComputeOptimalTarget
{

    public function computeOptimalTarget(OptimalTargetAlgorithm $algorithm, Request $request, int $projectId) {
        $errorMessage = null;

        /** @var Project $project */
        $project = Project::findOrFail($projectId);
        if ($project == null) {
            $errorMessage = "Project with id " . $projectId . " not found";
        }
        $existing = $project->getExistingEnvironment();
        if ($existing == null) {
           $errorMessage = "Existing environment not found for project with id " . $projectId . " not found";
        }

        if ($errorMessage != null) {
            Log::warning($errorMessage);
            return response()->json(["error" => $errorMessage], 400);
        }

        $computeRequest = $request->all();

        foreach ($computeRequest["server_configs"] as $index => $requestTarget) {
            try {
                $targetConfig = new OptimalTargetConfiguration();
                $targetConfig->manufacturer = $computeRequest["environment"]["processor_type_constraint"];
                $targetConfig->environment_name = array_get($requestTarget, "environment_name");
                $targetConfig->environment_detail = array_get($requestTarget, "environment_detail");
                $targetConfig->workload_type = array_get($requestTarget, "workload_type");
                $targetConfig->location = array_get($requestTarget, "location");
                $targetConfig->cpu_utilization = $computeRequest["environment"]["cpu_utilization"];
                $targetConfig->ram_utilization = $computeRequest["environment"]["ram_utilization"];
                $targetConfig->processor_type_constraint = $computeRequest["environment"]["processor_type_constraint"];
                $targetConfig->processor_models_constraint = $computeRequest["environment"]["processor_models_constraint"];
                $targetConfig->os = $this->requestObjectToSoftware(array_get($requestTarget, "os"));
                $targetConfig->database = $this->requestObjectToSoftware(array_get($requestTarget, "database"));
                $targetConfig->hypervisor = $this->requestObjectToSoftware(array_get($requestTarget, "hypervisor"));
                $targetConfig->middleware = $this->requestObjectToSoftware(array_get($requestTarget, "middleware"));
                $targetConfig->cagrMult = $existing->getCagrMultiplier();

                $optimalTarget = $algorithm->computeOptimalServerConfiguration($existing, $targetConfig);
                
            } catch (Exception $e) {
                Log::warning($e);
                return response()->json(["error" => $e->getMessage()], 500);
            }

            // Set target in $requestTarget
            $requestTarget["processor"] = $optimalTarget->processor->toArray();
            $requestTarget["processor_id"] = $optimalTarget->processor->id;
            $requestTarget["ram"] = $optimalTarget->ram;
            $requestTarget["acquisition_cost"] = $optimalTarget->total_server_cost;

            // Lookup or create server/manufacturer if none exists
            $optimalManufacturer = Manufacturer::firstOrCreate(["name" => "N/A"]);

            $optimalServer = Server::firstOrCreate([
                "name" => "N/A",
                "manufacturer_id" => $optimalManufacturer->id
            ]);
            $requestTarget["model_id"] = $optimalServer->id;
            $requestTarget["manufacturer"] = $optimalManufacturer->toArray();
            
            //* we need to return the computed target in the server_config
            $computeRequest['server_configs'][$index] = $requestTarget;            
        }

        return $computeRequest;
    }

    protected function requestObjectToSoftware($requestObject): ?Software
    {
        if ($requestObject == null) {
            return null;
        }

        $software = new Software();
        $software->forceFill($requestObject);
        if (array_has($requestObject, "software_features")) {
            $softwareFeatures = [];
            foreach ($requestObject["software_features"] as $software_feature) {
                $softwareFeature = new SoftwareFeature();
                $softwareFeature->forceFill($software_feature);
                if ($software_feature["feature"] != null) {
                    $feature = new Feature();
                    $feature->forceFill($software_feature["feature"]);
                    $softwareFeature->feature = $feature;
                }
                array_push($softwareFeatures, $softwareFeature);
            }
            $software->features = $softwareFeatures;
        }

        if (array_has($requestObject, "software_type")) {
            $softwareType = new SoftwareType();
            $softwareType->forceFill($requestObject["software_type"]);
            $software->softwareType = $softwareType;
        }
        return $software;
    }
}
