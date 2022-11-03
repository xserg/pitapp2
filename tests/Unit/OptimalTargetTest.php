<?php

namespace Tests\Unit;

use App\Models\Hardware\Manufacturer;
use App\Models\Hardware\OptimalTarget;
use App\Models\Hardware\Processor;
use App\Models\Hardware\Server;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Models\Project\Project;
use App\Models\Software\Feature;
use App\Models\Software\Software;
use App\Models\Software\SoftwareCost;
use App\Models\Software\SoftwareFeature;
use App\Models\Software\SoftwareType;
use App\Services\CsvImportService;
use App\Services\OptimalTarget\BruteForceAlgorithm;
use App\Services\OptimalTarget\OptimalTargetConfiguration;
use Exception;
use Tests\TestCase;

class OptimalTargetTest extends TestCase
{
    private $algorithm;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $targets = $this->loadAllOptimalTargets();
        $mockDataLoader = function () use ($targets) { return $targets; };

        // Use the brute force method
        $this->algorithm = new BruteForceAlgorithm($mockDataLoader);
    }

    public function setUp(): void
    {
        parent::setUp();
    }

    protected function logOptimal(OptimalTarget $optimal, string $name = "default") {
        echo "\n The $name optimal target is " . $optimal->processor->name . " (" . $optimal->processor->core_qty . " cores/processor and " . $optimal->processor->socket_qty . " processors) with " . $optimal->ram . "GB of RAM\n";
    }

    /**
     * A basic unit test example.
     *
     * @return void
     * @throws Exception
     */
    public function dont_test_ibm_oracle_optimal_target()
    {
        $testEnv = $this->createTestEnvironment("ibm-oracle-test-env.json");

        $optimal = $this->algorithm->computeOptimalServerConfiguration(
            $testEnv,
            $this->createOptimalTargetConfiguration(
                ["location" => "TX", "environment_detail" => "Prod", "workload_type" => "DB"],
                ["database" => $testEnv->softwareCosts[0]->software]
            )
        );
        $this->logOptimal($optimal);
    }

    /**
     * A basic unit test example.
     *
     * @return void
     * @throws Exception
     */
    public function dont_test_wwww_optimal_target()
    {
        $testEnv = $this->createTestEnvironment("wwww.json");

        $optimal_db = $this->algorithm->computeOptimalServerConfiguration(
            $testEnv,
            $this->createOptimalTargetConfiguration(
                ["workload_type" => "DB"],
                ["os" => $testEnv->softwareCosts[0]->software, "database" => $testEnv->softwareCosts[1]->software]
            )
        );

        $optimal_app = $this->algorithm->computeOptimalServerConfiguration(
            $testEnv,
            $this->createOptimalTargetConfiguration(
                ["workload_type" => "App"],
                ["os" => $testEnv->softwareCosts[0]->software, "database" => $testEnv->softwareCosts[1]->software]
            )
        );

        $this->logOptimal($optimal_db, "DB");
        $this->logOptimal($optimal_app, "App");
    }

    protected function loadAllOptimalTargets() {
        $csvImportService = new CsvImportService();
        $targets = [];
        $csvImportService->parseCsv(__DIR__."/../../database/data/Optimized_Target_Pricing_CSV.csv", function ($row) use (&$targets) {
            $target = new OptimalTarget();
            $target->forceFill([
                'ram'               => $row['RAM'],
                'total_server_cost' => $row['Total Server Cost']
            ]);
            $processor = new Processor();
            $processor->forceFill([
                'name'       => $row['Processor Type'],
                'ghz'        => $row['GHz'],
                'socket_qty' => $row['# of Processors'],
                'core_qty'   => $row['Cores/Processor'],
                'rpm'        => $row['CPM Value']
            ]);
            $processor->manufacturer = new Manufacturer();
            $processor->manufacturer->name = "Test";
            $target->processor = $processor;
            array_push($targets, $target);
        });
        return $targets;
    }

    protected function createOptimalTargetConfiguration(array $details = [], array $software = []): OptimalTargetConfiguration {
        $config = new OptimalTargetConfiguration();
        $config->manufacturer = "Test";
        $config->ram_utilization = 100;
        $config->cpu_utilization = 100;
        $config->os = array_get($software, "os");
        $config->database = array_get($software, "database");
        $config->middleware = array_get($software, "middleware");
        $config->hypervisor = array_get($software, "hypervisor");
        $config->location = array_get($details, "location");
        $config->workload_type = array_get($details, "workload_type");
        $config->environment_detail = array_get($details, "environment_detail");
        $config->environment_name = array_get($details, "environment_name");
        return $config;
    }

    /**
     * @param $fileName
     * @return Environment
     * @throws Exception
     */
    protected function createTestEnvironment($fileName): Environment
    {
        $jsonStr = file_get_contents(__DIR__.'/'.$fileName);
        $envJson = json_decode($jsonStr, true);

        // Environment
        $env = new Environment();
        $env->forceFill($envJson);

        // Project
        if ($envJson["project"] != null) {
            $project = new Project();
            $project->forceFill($envJson["project"]);
            $env->project = $project;
        }

        $softwareById = [];

        // Software Costs
        if ($envJson["software_costs"] != null) {
            $softwareCosts = [];
            foreach ($envJson["software_costs"] as $software_cost) {
                $softwareCost = new SoftwareCost();
                $softwareCost->forceFill($software_cost);

                // Software
                if ($software_cost["software"] != null) {
                    $softwareJson = $software_cost["software"];
                    $software = new Software();
                    $software->forceFill($softwareJson);
                    if ($softwareJson["software_features"] != null) {
                        $softwareFeatures = [];
                        foreach ($softwareJson["software_features"] as $software_feature) {
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

                    if ($softwareJson["software_type"] != null) {
                        $softwareType = new SoftwareType();
                        $softwareType->forceFill($softwareJson["software_type"]);
                        $software->softwareType = $softwareType;
                    }

                    $softwareById[$software->id] = $software;
                    $softwareCost->software = $software;
                }

                array_push($softwareCosts, $softwareCost);
            }
            $env->softwareCosts = $softwareCosts;
        }

        // Server Configs
        if ($envJson["server_configurations"] != null) {
            $serverConfigurations = [];
            foreach ($envJson["server_configurations"] as $server_config) {
                $serverConfig = new ServerConfiguration();
                $serverConfig->forceFill($server_config);

                // Processor
                if ($server_config["processor"] != null) {
                    $processor = new Processor();
                    $processor->forceFill($server_config["processor"]);
                    $serverConfig->processor = $processor;
                }

                // Manufacturer
                if ($server_config["manufacturer"] != null) {
                    $manufacturer = new Manufacturer();
                    $manufacturer->forceFill($server_config["manufacturer"]);
                    $serverConfig->manufacturer = $manufacturer;
                }

                // Server
                if ($server_config["server"] != null) {
                    $server = new Server();
                    $server->forceFill($server_config["server"]);
                    $serverConfig->server = $server;
                }

                // OS
                if ($server_config["os"] != null && $server_config["os"]["id"] != null) {
                    $os = $softwareById[$server_config["os"]["id"]];
                    if ($os == null) {
                        throw new Exception("Could not find OS with id " . $server_config["os"]["id"]);
                    }
                    $serverConfig->os = $os;
                }

                // Middleware
                if ($server_config["middleware"] != null && $server_config["middleware"]["id"] != null) {
                    $middleware = $softwareById[$server_config["middleware"]["id"]];
                    if ($middleware == null) {
                        throw new Exception("Could not find Middleware with id " . $server_config["middleware"]["id"]);
                    }
                    $serverConfig->middleware = $middleware;
                }

                // Hypervisor
                if ($server_config["hypervisor"] != null && $server_config["hypervisor"]["id"] != null) {
                    $hypervisor = $softwareById[$server_config["hypervisor"]["id"]];
                    if ($hypervisor == null) {
                        throw new Exception("Could not find Hypervisor with id " . $server_config["hypervisor"]["id"]);
                    }
                    $serverConfig->hypervisor = $hypervisor;
                }

                // Database
                if ($server_config["database"] != null && $server_config["database"]["id"] != null) {
                    $database = $softwareById[$server_config["database"]["id"]];
                    if ($database == null) {
                        throw new Exception("Could not find Database with id " . $server_config["database"]["id"]);
                    }
                    $serverConfig->database = $database;
                }

                array_push($serverConfigurations, $serverConfig);
            }
            $env->serverConfigurations = $serverConfigurations;
        }

        return $env;
    }
}
