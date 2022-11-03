<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Software;


use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\AzureAds;
use App\Models\Project\Environment;
use App\Models\Software\Software;

class CloudCalculator extends Calculator
{
    /**
     * @param $consolidationMap
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return CloudCalculator
     */
    public function calculateCosts(&$consolidationMap, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        /** @var \stdClass $server */
        foreach ($consolidationMap as &$server) {
            if ($server->instance_type == AzureAds::INSTANCE_TYPE_ADS) {
                // ADS currently supports no software
                // This is more of a safety check in case an instance had software stored on it (before getting turned into ADS)
                // and for whatever reason the software was not properly removed
                continue;
            }
            $processor = (object)['socket_qty' => $server->vcpu_qty, 'core_qty' => $server->vcpu_qty, 'isAWS' => true];
            if (!$server->os_li_name && $server->os_id) {
                $this->calculateSoftwareCost($server, $processor, 'os_id', 'os_license', 'instances', $targetEnvironment, $existingEnvironment, null, true);
            }

            // No License Included option for middleware, will always pay for it
            if ($server->middleware_id && $server->middlewareInstances) {
                $this->calculateSoftwareCost($server, $processor, 'middleware_id', 'middleware_license', 'middlewareInstances', $targetEnvironment, $existingEnvironment);
            }

            // If they are AWS, it's LI, ignore the cost. If the instance is an M4, there's no DB software.
            if ($server->database_id) {
                $this->calculateSoftwareCost($server, $processor, 'database_id', 'database_license', 'databaseInstances', $targetEnvironment, $existingEnvironment, 'database');
            } else {
                $server->database = null;
            }
        }

        return $this;
    }

    /**
     * @param $server
     * @param $processor
     * @param $softwareIdField
     * @param $softwareLicenseField
     * @param $instanceField
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @param null $serverField
     * @param bool $isByol
     * @return $this
     */
    public function calculateSoftwareCost($server, $processor, $softwareIdField, $softwareLicenseField, $instanceField, Environment $targetEnvironment, Environment $existingEnvironment, $serverField = null, $isByol=false)
    {
        $software = Software::find($server->{$softwareIdField});
        if ($serverField) {
            $server->{$serverField} = $software;
        }
        $this->softwareMap()->setScope($software, $targetEnvironment);
        $configSoftwareCost = $this->licenseCost($software, $processor, $targetEnvironment);
        $this->supportCostPerYear($software, $configSoftwareCost, $processor, $targetEnvironment, $server->{$instanceField});
        if ($targetEnvironment->project->isNewVsNew() || !$this->isInEnvironment($software, $existingEnvironment)) {
            $targetEnvironment->{$softwareLicenseField} += $configSoftwareCost * $server->{$instanceField};
        } else if ($software) {
            $this->softwareMap()->addData('ignoreLicense', true);
        }

        if ($isByol) {
            $this->softwareMap()->setData('isByol', true);
        }

        $this->pricingService()->softwareFeatureCalculator()->licenseFeaturesCost($software, $processor, $targetEnvironment, $existingEnvironment);
        $this->pricingService()->softwareFeatureCalculator()->supportFeaturesCostPerYear($software, $processor, $targetEnvironment);

        return $this;
    }
}