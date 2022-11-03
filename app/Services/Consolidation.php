<?php
/**
 *
 */

namespace App\Services;


use App\Exceptions\ConsolidationException;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Services\Consolidation\Cloud;
use App\Services\Consolidation\CloudConsolidator;
use App\Services\Consolidation\DefaultConsolidator;
use Illuminate\Support\Facades\Auth;
use App\Models\Project\Log;
use App\Models\Hardware\AmazonServer;
use App\Models\Project\Cloud\PaymentOption;
use App\Models\Project\Provider;

class Consolidation
{
    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return string
     * @throws ConsolidationException
     */
    public function runConsolidation(Environment $existingEnvironment, Environment $targetEnvironment)
    {
        $t0 = microtime(true);
        ini_set('memory_limit', '8000M');
        set_time_limit(900);

        $this->validateAccess($existingEnvironment, $targetEnvironment)
            ->validateEnvironments($existingEnvironment, $targetEnvironment);

        \Illuminate\Support\Facades\Log::info("          Time to Consolidation::runConsolidation (1): " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);

        if ($targetEnvironment->isCloud()) {
            // Currently cloud has separate consolidation logic
            $analysis = $this->cloudConsolidator()->consolidate($existingEnvironment, $targetEnvironment);
            \Illuminate\Support\Facades\Log::info("          Time to Consolidation::runConsolidation (2.A): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
        } else {
            // Converged, Compute, Compute + Storage all use the same process
            $analysis = $this->defaultConsolidator()->consolidate($existingEnvironment, $targetEnvironment);
            \Illuminate\Support\Facades\Log::info("          Time to Consolidation::runConsolidation (2.B): " . (microtime(true) - $t0) * 1000.0 . "ms");
            $t0 = microtime(true);
        }

        // This is a fix for the case when TCO Analysis returns "No matching server was found." value as a result
        // with the following parameters:
        // URL: /project/2111/targetEnvironment/6009 (Staging DB state 10/16/2020
        // Cloud provider: "Azure"
        // Payment Option: "One Year Reserved With Azure Hybrid Benefit"
        // Database Server Type : "Single Instance" || "Single Database" || "Elastic Pools"
        // Service Tier: "General Purpose" || "Business Critical"
        //
        // The wrong "payment option" value is replaced with the default one.
        // This replacement DOES touch the DB: "environments.payment_option_id" value
        if($analysis->totals == null && $targetEnvironment->getProvider()->name == Provider::AZURE) {
            $oneYearReserved = AmazonServer::getAzurePaymentOptionByName(
                PaymentOption::AZURE_ONE_YEAR_RESERVED
            );
            $oneYearReservedWithAHB = AmazonServer::getAzurePaymentOptionByName(
                PaymentOption::AZURE_ONE_YEAR_RESERVED_AHB
            );

            if($oneYearReserved && $oneYearReservedWithAHB && $targetEnvironment->payment_option_id == $oneYearReservedWithAHB['id']) {
                $targetEnvironment->payment_option_id = $oneYearReserved['id'];

                return $this->runConsolidation($existingEnvironment, $targetEnvironment);
            }
        }

        \Illuminate\Support\Facades\Log::info("          Time to Consolidation::runConsolidation (3): " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);

        if($analysis->totals == null) {
            $err = (object)["message" => "No matching server was found."];

            throw $this->consolidationException($err->message, $err);
        }

        $targetEnvironment->saveTargetAnalysis(json_encode($analysis));
        $targetEnvironment->is_dirty = false;
        $targetEnvironment->save();

        $this->_logQuery();

        $rval = $targetEnvironment->target_analysis;

        \Illuminate\Support\Facades\Log::info("          Time to Consolidation::runConsolidation (4): " . (microtime(true) - $t0) * 1000.0 . "ms");

        return $rval;
    }

    /**
     * @param $existingId
     * @param $targetId
     * @return string
     * @throws ConsolidationException
     */
    public function consolidate($existingId, $targetId)
    {
        $t0 = microtime(true);
        $existing = $this->getExistingEnvironment($existingId);
        \Illuminate\Support\Facades\Log::info("        Time to Consolidation::getExistingEnvironment: " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);
        $target = $this->getTargetEnvironment($targetId);
        \Illuminate\Support\Facades\Log::info("        Time to Consolidation::getTargetEnvironment: " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);
        $rval = $this->runConsolidation($existing, $target);
        \Illuminate\Support\Facades\Log::info("        Time to Consolidation::runConsolidation: " . (microtime(true) - $t0) * 1000.0 . "ms");
        return $rval;
    }

    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @param null $profile
     * @return $this
     */
    public function validateAccess(Environment $existingEnvironment, Environment $targetEnvironment, $profile = null)
    {
        $profile = $profile ?? \Auth::user();

        if ($profile->isAdmin()) {
            return $this;
        }

        if ($existingEnvironment->project->user_id != $profile->user_id || $targetEnvironment->project->user_id != $profile->user_id) {
            // User is non admin and doesn't have access to this
            abort(401);
        }

        return $this;
    }


    /**
     * @param $existingEnvironment
     * @param $targetEnvironment
     * @return Consolidation
     */
    public function validateEnvironments(Environment $existingEnvironment, Environment $targetEnvironment)
    {
        if ($existingEnvironment->isIncomplete() || $targetEnvironment->isIncomplete()) {
            $err = (object)["message" => "An environment has insufficient data to run the analysis"];
            $err->environments = (object)[
                'existing' => ($existingEnvironment->isIncomplete() ? $existingEnvironment->id : null),
                'target' => ($targetEnvironment->isIncomplete() ? $targetEnvironment->id : null),
                'existingName' => $existingEnvironment->name,
                'targetName' => $targetEnvironment->name,
                'existingIsExisting' => $existingEnvironment->isExisting()
            ];

            throw $this->consolidationException($err->message, $err);
        }

        if((!$existingEnvironment->getExistingEnvironmentType() && !$existingEnvironment->getEnvironmentType()) || !$targetEnvironment->getEnvironmentType()) {
            $err = (object)["message" => "Insufficient data to run analysis"];
            $err->environments = (object)[
                'existing' => (!$existingEnvironment->getExistingEnvironmentType() && !$existingEnvironment->getExistingEnvironmentType() ? $existingEnvironment->id : null),
                'target' => (!$targetEnvironment->getEnvironmentType() ? $targetEnvironment->id : null),
                'existingName' => $existingEnvironment->name,
                'targetName' => $targetEnvironment->name,
                'existingIsExisting' => $existingEnvironment->isExisting()
            ];
            throw $this->consolidationException($err->message, $err);
        }

        foreach($existingEnvironment->serverConfigurations as $config) {
            if (!$config->is_converged && $config->type == ServerConfiguration::TYPE_PHYSICAL && !$config->processor_id) {
                $err = (object)["message" => "Insufficient data to run analysis. Your existing environment has one or more server configurations without a processor definition."];
                $err->environments = (object)[
                    'existing' => (!$existingEnvironment->getExistingEnvironmentType() && !$existingEnvironment->getExistingEnvironmentType() ? $existingEnvironment->id : null),
                    'target' => (!$targetEnvironment->getEnvironmentType() ? $targetEnvironment->id : null),
                    'existingName' => $existingEnvironment->name,
                    'targetName' => $targetEnvironment->name,
                    'existingIsExisting' => $existingEnvironment->isExisting()
                ];
                throw $this->consolidationException($err->message, $err);
            }
        }

        if (!$targetEnvironment->isCloud()) {
            foreach ($targetEnvironment->serverConfigurations as $config) {
                if (!$config->is_converged && $config->type == ServerConfiguration::TYPE_PHYSICAL && !$config->processor_id) {
                    $err = (object)["message" => "Insufficient data to run analysis. Target environment {$targetEnvironment->name} has one or more server configurations without a processor definition."];
                    $err->environments = (object)[
                        'existing' => (!$existingEnvironment->getExistingEnvironmentType() && !$existingEnvironment->getExistingEnvironmentType() ? $existingEnvironment->id : null),
                        'target' => (!$targetEnvironment->getEnvironmentType() ? $targetEnvironment->id : null),
                        'existingName' => $existingEnvironment->name,
                        'targetName' => $targetEnvironment->name,
                        'existingIsExisting' => $existingEnvironment->isExisting()
                    ];
                    throw $this->consolidationException($err->message, $err);
                }
            }
        }

        return $this;
    }

    /**
     * @param $msg
     * @param array $data
     */
    public function consolidationException($msg, $data = null)
    {
        return (new ConsolidationException($msg))->setData($data);
    }

    /**
     * @param $existingId
     * @return Environment
     */
    public function getExistingEnvironment($existingId)
    {
        return Environment::with(array(
            'serverConfigurations.processor',
            'project',
            "serverConfigurations.server",
            "serverConfigurations.manufacturer",
            "serverConfigurations.chassis",
            "serverConfigurations.chassis.manufacturer",
            "serverConfigurations.chassis.model",
            "serverConfigurations.interconnect",
            "serverConfigurations.interconnect.manufacturer",
            "serverConfigurations.interconnect.model"))->find($existingId);
    }

    /**
     * @param $targetId
     * @return Environment
     */
    public function getTargetEnvironment($targetId)
    {
        return Environment::with(array(
        'serverConfigurations.processor',
        'project',
        'region',
        "serverConfigurations.server",
        "serverConfigurations.manufacturer",
        "serverConfigurations.chassis",
        "serverConfigurations.chassis.manufacturer",
        "serverConfigurations.chassis.model",
        "serverConfigurations.interconnect",
        "serverConfigurations.interconnect.manufacturer",
        "serverConfigurations.interconnect.model"))->find($targetId);
    }

    /**
     * @return CloudConsolidator
     */
    public function cloudConsolidator()
    {
        return resolve(CloudConsolidator::class);
    }

    /**
     * @return DefaultConsolidator
     */
    public function defaultConsolidator()
    {
        return resolve(DefaultConsolidator::class);
    }

    /**
     * @return $this
     */
    protected function _logQuery()
    {
        /** @var Revenue $revenueService */
        $revenueService = resolve(Revenue::class);
        if(!$revenueService->isReportMode() && Auth::user()->user->view_cpm == true) {
            Auth::user()->user->ytd_queries++;
            Auth::user()->user->save();

            $log = new Log();
            $log->user_id = Auth::user()->user->id;
            $log->log_type = "cpm_query";
            $log->save();
        }

        return $this;
    }
}
