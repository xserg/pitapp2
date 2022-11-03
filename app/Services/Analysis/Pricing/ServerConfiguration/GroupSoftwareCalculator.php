<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\ServerConfiguration;


use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Models\Software\Software;
use Illuminate\Support\Collection;

class GroupSoftwareCalculator
{
    /**
     * @var array
     */
    protected $_vmGroupFamilyCache = [];

    /**
     * @var array
     */
    protected $_softwareCache = [];

    /**
     * @param ServerConfiguration $serverConfiguration
     * @param Software|null $software
     * @param $softwareIdField
     * @param Environment $environment
     * @param Environment|null $existingEnvironment
     * @return bool
     */
    public function shouldSkipPhysicalSoftwareInVmGroup(ServerConfiguration $serverConfiguration, ?Software $software, $softwareIdField, Environment $environment, Environment $existingEnvironment = null)
    {
        if (!$software || !$software->id) {
            // No actual software applied
            return false;
        }

        $softwareId = $software->id;


        if ($environment->project->isNewVsNew()) {
            return false;
        }

        if (!$existingEnvironment && $environment->isExisting()) {
            $existingEnvironment = $environment;
        }

        if (!$existingEnvironment->isPhysicalVm()) {
            // We don't care about any environment types except physical_vm
            return false;
        }

        if (!$software->appliesToPhysicalServer()) {
            // License individually
            return false;
        }

        if (!$environment->isExisting() && !$environment->isTreatAsExisting() && $existingEnvironment) {
            /** @var array|Collection $vmGroup */
            $vmGroup = $serverConfiguration->facade_vm_group ?? [];

            if (is_array($vmGroup)) {
                $vmGroup = collect($vmGroup);
            }

            if ($serverConfiguration->isVm()) {
                $physicalServerConfiguration = $environment->serverConfigurations->first(function (ServerConfiguration $envConfiguration) use ($serverConfiguration) {
                    return $envConfiguration->isPhysical() && $envConfiguration->id == $serverConfiguration->parent_facade_configuration_id;
                });

                if ($physicalServerConfiguration->isConverged()) {
                    foreach ($physicalServerConfiguration->nodes as $node) {
                        $vmGroup->prepend($node);
                    }
                } else {
                    $vmGroup->prepend($physicalServerConfiguration);
                }
            } else {
                $vmGroup->push($serverConfiguration);
                $environment->getAdditionalServerConfigurations()->each(function(ServerConfiguration $envConfiguration) use ($serverConfiguration, &$vmGroup, $environment) {
                    if ($envConfiguration->isVm() &&
                        ($envConfiguration->parent_facade_configuration_id == $serverConfiguration->id
                        || $envConfiguration->parent_facade_configuration_id == $serverConfiguration->parent_configuration_id)) {
                        $vmGroup->push($envConfiguration);
                    }
                });
            }

        } else {
            // Sort by physicals first
            if ($serverConfiguration->isVm()) {
                // Get the physical matching this parent id
                // Get the VMs matching this parent id
                $vmGroup = $environment->serverConfigurations->sortBy('type')->filter(function (ServerConfiguration $envConfiguration) use ($serverConfiguration) {
                    return $envConfiguration->isPhysical()
                        ? $envConfiguration->id == $serverConfiguration->physical_configuration_id
                        : $envConfiguration->physical_configuration_id == $serverConfiguration->physical_configuration_id;
                });
            } else {
                // Get all VMs matching this physical id
                $vmGroup = $environment->serverConfigurations->sortBy('type')->filter(function (ServerConfiguration $envConfiguration) use ($serverConfiguration) {
                    return $envConfiguration->isPhysical()
                        ? $envConfiguration->id == $serverConfiguration->id
                        : $envConfiguration->physical_configuration_id == $serverConfiguration->id;
                });
            }
        }

        return $this->prefersPhysicalSoftwareFamilyMember($vmGroup, $serverConfiguration, $software, $softwareId, $softwareIdField, $environment)
            || $this->isPhysicalSoftwareAlreadyAppliedToVmGroup($vmGroup, $serverConfiguration, $software, $softwareId, $softwareIdField);
    }

    /**
     * Determine if the given VM group has a piece of software in the same family that should take precedence over
     * this software. This helps us ensure that in a situation like the below:
     *
     * Physical Server:
     * - MS Windows 2008 Standard
     * - MS Windows 2012 Data Center
     *
     * We pick the most expensive software (2012 Data Center) to apply to this VM group, and don't apply *both* pieces of software.
     *
     * Currently this method only looks for families that match the below regexes. We may add others in the future depending on demand.
     *
     * /(MS Windows|Microsoft Windows)/i
     * /(RHEL|Red Hat| RedHat)/i
     * /VMware/i
     *
     *
     * @warning @todo This function will not work correctly if the underlying piece of software has a processor counterpart.
     *                 Currently processor counterparts are only supported for x86/Oracle and IBM versions of software,
     *                 which at this time only applies to Oracle Software. If that changes in the future, this function
     *                 needs to be updated to accommodate for that
     *
     *
     * @param Collection $vmGroup
     * @param ServerConfiguration $serverConfiguration
     * @param Software $software
     * @param $softwareId
     * @param $softwareIdField
     * @param Environment $environment
     * @return bool
     */
    public function prefersPhysicalSoftwareFamilyMember(Collection $vmGroup,  ServerConfiguration $serverConfiguration, Software $software, $softwareId, $softwareIdField, Environment $environment)
    {
        $softwareGroupType = $software->getGroup();
        if ($softwareGroupType == Software::GROUP_NONE) {
            // Not in a group
            return false;
        }


        $vmGroupCacheKey = $this->getVmGroupCacheKey($vmGroup, $softwareIdField, $softwareGroupType);

        if (!isset($this->_vmGroupFamilyCache[$vmGroupCacheKey])) {
            $groupSoftwares = collect([]);

            $vmGroup->each(function ($groupConfiguration) use ($softwareIdField, $softwareId, $groupSoftwares, $softwareGroupType, $environment) {

                $groupSoftwareId = $groupConfiguration->{$softwareIdField} ?? null;


                if (!$groupSoftwareId) {
                    return;
                }

                /** @var Software $groupSoftware */
                $groupSoftware = $this->getSoftwareFromCache($groupConfiguration->{$softwareIdField});
                $key = 'software_' . $groupSoftwareId;

                // Only allow those which have an annual support cost-per-processor
                // Or are a percent of the license cost which is based off per-processor
                if ((($groupSoftware->annual_cost_per == Software::COST_PER_PROCESSOR && $groupSoftware->support_type == Software::SUPPORT_TYPE_LIST_PRICE)
                        ||
                        ($groupSoftware->cost_per == Software::COST_PER_PROCESSOR && $groupSoftware->support_type == Software::SUPPORT_TYPE_PERCENT_LICENSE))
                    // Must be in the same group
                    && $groupSoftware->isGroup($softwareGroupType)
                    // Must not already be in the unique list
                    && !$groupSoftwares->has($key)
                ) {
                    $groupSoftwares->push($groupSoftware);
                }
            });

            $mostExpensiveSoftware = $groupSoftwares->sortByDesc(function(Software $groupSoftware) use ($environment) {
                $discount = $groupSoftware->support_multiplier;
                $licenseDiscount = 0;
                foreach ($environment->softwareCosts as $cost) {
                    if ($cost->software_type_id == $groupSoftware->id) {
                        $discount = $cost->support_cost_modifier;
                        $licenseDiscount = $cost->license_cost_modifier;
                        break;
                    }
                }

                if ($groupSoftware->support_type == Software::SUPPORT_TYPE_PERCENT_LICENSE) {
                    return (floatval($groupSoftware->support_cost_percent) / 100) * floatval($groupSoftware->license_cost) * (1 - ($licenseDiscount / 100)) * (1 - $discount / 100);
                }

                return floatval($groupSoftware->support_cost * (1 - ($discount / 100)));
            })->first();

            $this->_vmGroupFamilyCache[$vmGroupCacheKey] = $mostExpensiveSoftware;
        } else {
            // We have already determined the most expensive software for this group
            $mostExpensiveSoftware = $this->_vmGroupFamilyCache[$vmGroupCacheKey];
        }

        // Prefer the most expensive family member if it is NOT
        // the exact piece of software we're examining within this VM group
        // Also, ensure we actually have most expensive software
        return $mostExpensiveSoftware && $mostExpensiveSoftware->id != $softwareId;
    }

    /**
     * Determine if the given VM group already has physical software applied
     * @param Collection $vmGroup
     * @param ServerConfiguration $serverConfiguration
     * @param Software $software
     * @param $softwareId
     * @param $softwareIdField
     * @return bool
     */
    public function isPhysicalSoftwareAlreadyAppliedToVmGroup(Collection $vmGroup,  ServerConfiguration $serverConfiguration, Software $software, $softwareId, $softwareIdField)
    {
        /** @var ServerConfiguration $groupConfiguration */
        foreach($vmGroup as $groupConfiguration) {
            if ($serverConfiguration->id == $groupConfiguration->id) {
                // Stop processing once we hit this server configuration
                // We only care about checking servers before ours
                break;
            }

            $groupSoftwareId = $groupConfiguration->{$softwareIdField};

            if ($realSoftwareId = $serverConfiguration->getMappedSoftware($softwareIdField, $groupSoftwareId)) {
                // If we mapped the underlying VM software to a counterpart
                // We should check that counterpart instead of this piece of software
                $groupSoftwareId = $realSoftwareId;
            }

            if ($groupSoftwareId && $groupSoftwareId == $softwareId) {
                // Return from the function and prevent the software from getting applied
                return true;
            }
        }

        return false;
    }

    /**
     * @param Collection $vmGroup
     * @param $softwareIdField
     * @param $groupTypeCode
     * @return string
     */
    public function getVmGroupCacheKey(Collection $vmGroup, $softwareIdField, $groupTypeCode)
    {
        $cacheKeyComponents = [$softwareIdField, $groupTypeCode];
        foreach($vmGroup as $vmConfiguration) {
            $cacheKeyComponents[] = 'server_' . $vmConfiguration->id;
        }

        return md5(implode("|", $cacheKeyComponents));
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getSoftwareFromCache($id)
    {
        $key = 'software_' . $id;
        if (!isset($this->_softwareCache[$key])) {
            $this->_softwareCache[$key] = Software::findOrFail($id);
        }

        return $this->_softwareCache[$key];
    }
}