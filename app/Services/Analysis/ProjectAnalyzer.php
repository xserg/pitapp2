<?php
/**
 *
 */

namespace App\Services\Analysis;


use App\Exceptions\ConsolidationException;
use App\Models\Project\AnalysisResult;
use App\Models\Project\Environment;
use App\Models\Project\Project;
use App\Services\Analysis\Environment\AbstractAnalyzer;
use App\Services\Analysis\Environment\Existing\AbstractExistingAnalyzer;
use App\Services\Analysis\Environment\Existing\DefaultExistingAnalyzer;
use App\Services\Analysis\Environment\Existing\NewVsNewExistingAnalyzer;
use App\Services\Analysis\Environment\Target\AbstractTargetAnalyzer;
use App\Services\Analysis\Environment\Target\CloudTargetAnalyzer;
use App\Services\Analysis\Environment\Target\DefaultTargetAnalyzer;
use App\Services\Analysis\Pricing\Software\MapAccessTrait;
use App\Services\Consolidation;
use App\Services\ProjectLicensingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProjectAnalyzer
{
    use MapAccessTrait;
    use PricingAccessTrait;

    /**
     * @param Project $project
     * @return AnalysisResult
     */
    public function analyze(Project $project, $targetId)
    {
        try {
            $t0 = microtime(true);
            $licensing_service = new ProjectLicensingService($project);
            $licensing_service->resetCounters()
                ->licensingCheck();

            $this->softwareMap()->startAnalysis();

            $this->setProjectDefaults($project);

            $existingEnvironment = $this->getExistingEnvironment($project);

            Log::info("    ProjectAnalyzer::analyze (1): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);

            // Analyze the existing separately
            $this->analyzeExistingEnvironment($project, $existingEnvironment);

            Log::info("    ProjectAnalyzer::analyze (2): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);

            // Get list of target environments
            /** @var Collection $targetEnvironments */
            if ($targetId) {
                $targetEnvironments = collect([$project->getTargetEnvironmentById($targetId)]);
            } else {
                $targetEnvironments = collect($project->getTargetEnvironments());
            }

            // Start a collection for all environments
            /** @var Collection $allEnvironments */
            $allEnvironments = collect([$existingEnvironment])->merge($targetEnvironments);

            if (config('app.debug')) {
                logger('File: ' . __FILE__ . PHP_EOL . 'Function: ' . __FUNCTION__ . PHP_EOL . 'Line:' . __LINE__);
                logger($allEnvironments->count());
            }

            Log::info("    ProjectAnalyzer::analyze (3): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);

            $this->consolidateTargetEnvironments($project, $existingEnvironment, $targetEnvironments);
            Log::info("    ProjectAnalyzer::analyze (consolidateTargetEnvironments): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->analyzeTargetEnvironments($project, $existingEnvironment, $targetEnvironments);
            Log::info("    ProjectAnalyzer::analyze (analyzeTargetEnvironments): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->findBestCloudTargetSummary($project, $allEnvironments);
            Log::info("    ProjectAnalyzer::analyze (findBestCloudTargetSummary): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->updateExistingEnvironment($project, $existingEnvironment, $targetEnvironments);
            Log::info("    ProjectAnalyzer::analyze (updateExistingEnvironment): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->copyExistingData($project, $existingEnvironment, $targetEnvironments);
            Log::info("    ProjectAnalyzer::analyze (copyExistingData): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->mapSoftwareCosts($project, $allEnvironments);
            Log::info("    ProjectAnalyzer::analyze (mapSoftwareCosts): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->calculateTotalCosts($project, $allEnvironments);
            Log::info("    ProjectAnalyzer::analyze (calculateTotalCosts): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->findBestTargetEnvironment($project, $existingEnvironment, $targetEnvironments);
            Log::info("    ProjectAnalyzer::analyze (findBestTargetEnvironment): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->calculateROI($project);
            Log::info("    ProjectAnalyzer::analyze (calculateROI): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
            $this->cleanup($project, $allEnvironments);
            Log::info("    ProjectAnalyzer::analyze (cleanup): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);

            /** @var AnalysisResult $analysisResult */
            $analysisResult = resolve(AnalysisResult::class);

            $analysisResult->setProject($project)
                ->setBestTargetEnvironment($project->getBestTargetEnvironment())
                ->setExistingEnvironment($existingEnvironment);

            $licensing_service->updateAnalysisChecksum()
                ->updateUserLicensingCounters()
                ->licensingCheck();

            Log::info("    ProjectAnalyzer::analyze (5): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);

        } catch (ConsolidationException $exception) {
            response()->json(['message' => $exception->getMessage()], 400)->send();
            exit;
        } catch (\Throwable $exception) {
            $exMsg = "Encountered Exception Generating Analysis: " . $exception->getMessage();

            if (env('APP_ENV') != 'production') {
                $exMsg .= "\n" . $exception->getTraceAsString();
            }

            response()->json(['message' => $exMsg], 400)->send();
            exit;
        }


        return $analysisResult;
    }

    /**
     * This will find the cheapest cloud summary available
     * @param Project $project
     * @param Collection $allEnvironments
     * @return $this
     */
    public function findBestCloudTargetSummary(Project $project, Collection $allEnvironments)
    {
        /** @var Environment $bestCloudEnvironment */
        $bestCloudEnvironment = $allEnvironments->filter(function(Environment $environment){
            return $environment->isCloud();
        })->each(function(Environment $environment){
            // This is required by the frontend
            // NOT the ideal way of doing it
            $environment->cheapestEnv = $environment->getCloudSummary();
        })->sort(function (Environment $a, Environment $b){
            return $a->getCloudSummaryTotal() <=> $b->getCloudSummaryTotal();
        })->first();

        if ($bestCloudEnvironment) {
            $project->setBestCloudTargetSummary($bestCloudEnvironment->getCloudSummary());
        }

        return $this;
    }

    /**
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Collection $targetEnvironments
     * @return $this
     */
    public function updateExistingEnvironment(Project $project, Environment $existingEnvironment, Collection $targetEnvironments)
    {
        $this->pricingService()->environmentNetworkCalculator()->updateExistingEnvironment($project, $existingEnvironment, $targetEnvironments);

        return $this;
    }

    /**
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Collection $targetEnvironments
     * @return $this
     */
    public function copyExistingData(Project $project, Environment $existingEnvironment, Collection $targetEnvironments)
    {
        $this->pricingService()->environmentNetworkCalculator()->copyExistingData($project, $existingEnvironment, $targetEnvironments);

        return $this;
    }

    /**
     * @param Project $project
     * @param Collection $allEnvironments
     * @return $this
     */
    public function calculateTotalCosts(Project $project, Collection $allEnvironments)
    {
        $allEnvironments->each((function(Environment $environment) use ($project) {
            $this->pricingService()->environmentTotalsCalculator()->calculateCosts($project, $environment);
        }));

        return $this;
    }

    /**
     * Get the best target environment
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Collection $targetEnvironments
     * @return mixed
     */
    public function findBestTargetEnvironment(Project $project, Environment $existingEnvironment, Collection $targetEnvironments)
    {
        /*
         * Get the cheapest environment that has a target_analysis (consolidation analysis)
         * which resulted in >= 1 server nodes / consolidations
         */
        $bestTarget = $targetEnvironments->filter(function(Environment $targetEnvironment){
            return $targetEnvironment->target_analysis && count($targetEnvironment->serverConfigurations);
        })->sortBy('total_cost')->first();

        if ($bestTarget) {
          $bestTarget->setSoftwareByNames($project->softwareByNames);
          $project->setBestTargetEnvironment($bestTarget);
        }

        return $this;
    }

    /**
     * @param Project $project
     * @param Collection $allEnvironments
     * @return $this
     */
    public function cleanup(Project $project, Collection $allEnvironments)
    {
        // Update this property with all the analyzed environments
        $project->environments = $allEnvironments;

         // Remove recursive references
        $allEnvironments->each(function(Environment $environment){
            unset($environment->project);
        });

        return $this;
    }

    /**
     * @param Project $project
     * @return Environment
     */
    public function getExistingEnvironment(Project $project)
    {
        return $project->getExistingEnvironment([
            "environmentType",
            "serverConfigurations",
            "serverConfigurations.os",
            "serverConfigurations.os.softwareType",
            "serverConfigurations.os.features",
            "serverConfigurations.hypervisor",
            "serverConfigurations.hypervisor.softwareType",
            "serverConfigurations.hypervisor.features",
            "serverConfigurations.middleware",
            "serverConfigurations.middleware.softwareType",
            "serverConfigurations.middleware.features",
            "serverConfigurations.database",
            "serverConfigurations.database.softwareType",
            "serverConfigurations.database.features",
            "serverConfigurations.processor",
            "softwareCosts",
            "softwareCosts.featureCosts",
            "softwareCosts.featureCosts.feature",
        ])->setTreatAsExisting(true);
    }

    /**
     * @param Project $project
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function analyzeExistingEnvironment(Project $project, Environment $existingEnvironment)
    {
        // Ensure project defaults carry over
        $existingEnvironment->project = $project;

        $analyzer = $this->getExistingEnvironmentAnalyzer($existingEnvironment);
        $analyzer->analyze($existingEnvironment);

        $project->existingCount = $existingEnvironment->getExistingCount();

        return $this;
    }

    /**
     * Rerun the consolidation analysis in case something changed.
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Collection $targetEnvironments
     * @return $this
     */
    public function consolidateTargetEnvironments(Project $project, Environment $existingEnvironment, Collection $targetEnvironments)
    {
        /** @var Consolidation $consolidationService */
        $consolidationService = resolve(Consolidation::class);

        $targetEnvironments->filter(function(Environment $targetEnvironment) use ($existingEnvironment, $project) {
            /*
             * We need to return if:
             * No analysis json is present
             * It's "dirty" (target environment or existing changed)
             * We have deployed an update since the project was last updated
             */
            return $targetEnvironment->target_analysis == null || $targetEnvironment->is_dirty || $project->isStale();
        })->each(function(Environment $targetEnvironment) use ($existingEnvironment, $consolidationService) {
            try {
                $t0 = microtime(true);
                $targetEnvironment->target_analysis = $consolidationService->consolidate($existingEnvironment->id, $targetEnvironment->id);
                $targetEnvironment->reset_analysis = true;
                Log::info("      Time to consolidate: " . (microtime(true) - $t0) * 1000.0 . "ms");
            } catch (ConsolidationException $e) {
                response()->json($e->getData(), 400)->send();
                exit;
            } catch (\Throwable $e) {
                $exMsg = "Encountered Exception Generating Analysis: " . $e->getMessage();
                if (env('APP_ENV') != 'production') {
                    $exMsg .= "\n" . $e->getTraceAsString();
                }
                response()->json(['message' => $exMsg], 400)->send();
                exit;
            }
        });

        // Touch a new version of the model
        Project::findOrFail($project->id)->touch();

        return $this;
    }

    /**
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Collection $targetEnvironments
     * @return $this
     */
    public function analyzeTargetEnvironments(Project $project, Environment $existingEnvironment, Collection $targetEnvironments)
    {
        if (config('app.debug')) {
            logger('File: ' . __FILE__ . PHP_EOL . 'Function: ' . __FUNCTION__ . PHP_EOL . 'Line:' . __LINE__);
            logger($targetEnvironments);
        }
        $targetEnvironments->each(function(Environment $targetEnvironment) use ($project, $existingEnvironment){
            // Ensure project defaults carry over
            $targetEnvironment->project = $project;

            // Analyze
            $this->analyzeTargetEnvironment($targetEnvironment, $existingEnvironment);
        });

        return $this;
    }

    /**
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function analyzeTargetEnvironment(Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $analyzer = $this->getTargetEnvironmentAnalyzer($targetEnvironment);
        $analyzer->analyze($targetEnvironment, $existingEnvironment);

        return $this;
    }

    /**
     * @param Environment $environment
     * @return AbstractExistingAnalyzer
     */
    public function getExistingEnvironmentAnalyzer(Environment $environment)
    {
        if ($environment->isExisting()) {
            return resolve(DefaultExistingAnalyzer::class);
        }

        return resolve(NewVsNewExistingAnalyzer::class);
    }

    /**
     * @param Environment $environment
     * @return AbstractTargetAnalyzer
     */
    public function getTargetEnvironmentAnalyzer(Environment $environment)
    {
        if ($environment->isCloud()) {
            return resolve(CloudTargetAnalyzer::class);
        }

        return resolve(DefaultTargetAnalyzer::class);
    }

    /**
     * @param Project $project
     * @return $this
     */
    public function setProjectDefaults(Project $project)
    {
        $project->support_years = $project->support_years ?: 0;

        return $this;
    }

    /**
     * @param Project $project
     * @param Collection $allEnvironments
     * @return $this
     */
    public function mapSoftwareCosts(Project $project, Collection $allEnvironments)
    {
        $allEnvironments->each(function(Environment $environment){
            $this->softwareMap()->calculateEnvironmentCosts($environment);
        });

        $project->softwares = $this->softwareMap()->mappedSoftware;
        $project->softwareByNames = $this->softwareMap()->getCostsByName();
        $project->softwareMap = $this->softwareMap()->getAllSoftware();

        return $this;
    }

    /**
     * @param Project $project
     * @return $this
     */
    private function calculateROI(Project $project)
    {
        $existingEnvironment = $project->getExistingEnvironment();
        $bestTargetEnvironment = $project->getBestTargetEnvironment();

        $roi = 0;

        $investment = $bestTargetEnvironment->investment > 0 ? $bestTargetEnvironment->investment : 1;

        if (!empty($bestTargetEnvironment->investment) && !$project->isNewVsNew()) {
            $roi = ($existingEnvironment->total_cost - $bestTargetEnvironment->total_cost) / $investment;
        }

        $bestTargetEnvironment->roi = $roi > 0 ? number_format(round($roi * 100, 0), 0) . '%' : 'N/A';

        return $this;
    }
}
