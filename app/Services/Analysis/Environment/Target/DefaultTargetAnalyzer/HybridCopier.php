<?php
/**
 *
 */
namespace App\Services\Analysis\Environment\Target\DefaultTargetAnalyzer;

use App\Models\Hardware\Processor;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Models\Software\Feature;
use App\Models\Software\FeatureCost;
use App\Models\Software\Software;
use App\Models\Software\SoftwareCost;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class HybridCopier
{
    /**
     * @var array
     */
    protected $_copyFields = ['baseRam', 'ram', 'computedRam', 'baseRpm', 'computedRpm', 'vm_cores'];

    /**
     * @var array
     */
    protected $_softwareFields = ['os_id', 'middleware_id', 'database_id', 'hypervisor_id'];

    /**
     * @var array
     */
    protected $_softwareModelFields = ['os', 'middleware', 'database', 'hypervisor'];

    /**
     * @var array
     */
    protected $_softwareMap = [];

    /**
     * @var array
     */
    protected $_usedSoftware = [
        'default' => [],
        'copy' => []
    ];

    /**
     * @param Environment $environment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function copyVmServers(Environment $environment, Environment $existingEnvironment)
    {
        $consolidations = [];
        $existingServers = [];
        foreach($environment->analysis->consolidations as $cons) {
            /** @var \stdClass $existingServer */
            foreach ($cons->servers as $existingServer) {
                if (!array_key_exists($existingServer->id, $consolidations)) {
                    $consolidations[$existingServer->id] = [];
                }
                array_push($consolidations[$existingServer->id], $cons);

                if (!array_key_exists($existingServer->id, $existingServers)) {
                    $existingServers[$existingServer->id] = [];
                }
                array_push($existingServers[$existingServer->id], $existingServer);
            }
        }

        /** @var ServerConfiguration $vm */
        foreach($existingEnvironment->serverConfigurations as $vm) {
            // Get all servers we're treating like VMs
            if (!$vm->isVm() && $vm->hasChildrenVMs()) {
                continue;
            }
            $config = clone $vm;
            // Force all of these to be virtual machines so no hardware costs
            // get factored into them on the target analysis
            if ($config->isPhysical()) {
                $config->makeVmCompatible();
            }
            $config->type = ServerConfiguration::TYPE_VM;

            // Default quantity
            if (!$config->total_qty) {
                $config->total_qty = 0;
            }

            if (array_key_exists($config->id, $existingServers)) {
                $loaded = false;
                $configServers = $existingServers[$config->id];
                foreach($configServers as &$existingServer) {
                    if (!$loaded) {
                        $loaded = true;
                        $cons = $consolidations[$existingServer->id][0];
                        $this->_loadTargetVm($config, $existingServer, $cons->targets[0], $environment, $cons->servers);
                    }
                    // The extra qty can be spread throughout the instances
                    // The "initial" quantity should be pulled from the server_configuration entry and only 1 time
                    $config->total_qty += $existingServer->comparison_server->extra_qty ?? 0;
                }
            }

            // Push the VM onto the server configuration pile
            $environment->addAdditionalServerConfiguration($config);
        }

        $copySoftwareCosts = collect($this->_usedSoftware['default']);
        $copySoftwareCosts = $copySoftwareCosts->merge($this->_usedSoftware['copy'])->unique();

        $targetSoftwares = $environment->softwareCosts->pluck('software_type_id')->all();

        $existingEnvironment->softwareCosts->whereIn('software_type_id', $copySoftwareCosts)->each(function(SoftwareCost $softwareCost) use ($environment, $targetSoftwares){

            if (in_array($softwareCost->software_type_id, $this->_usedSoftware['default'])
                && !in_array($softwareCost->software_type_id, $targetSoftwares)) {
                // Only copy if
                // Software is not already on the target environment
                $this->_copySoftware($environment, $softwareCost);
            }

            if (in_array($softwareCost->software_type_id, $this->_usedSoftware['copy'])
                && Arr::exists($this->_softwareMap, $softwareCost->software_type_id)
                && !in_array($this->_softwareMap[$softwareCost->software_type_id]['map_software_id'], $targetSoftwares)) {
                // Only copy counterpart if:
                // We know we need to copy this software cost
                // The counterpart software type is not already on the target environment
                $this->_copyCounterpartSoftware($environment, $softwareCost);
            }
        });

        return $this;
    }

    /**
     * Combine data from the target physical machine with the comparison server of the existing VM
     * to produce a ServerConfiguration object that can be appropriately used to load
     * @param ServerConfiguration $config
     * @param \stdClass $existingServer
     * @param \stdClass $targetServer
     * @param Environment $environment
     * @param null $otherVms
     * @return $this
     */
    protected function _loadTargetVm(ServerConfiguration & $config, \stdClass $existingServer, \stdClass $targetServer, Environment $environment, $otherVms = null)
    {
        /** @var Processor $origProcessor */
        $origProcessor = clone $config->processor;
        $config->total_qty += ($config->qty ?: 1);
        // Update the processor to match the processor of the new target server (it is currently set to the old server)
        $config->processor_id = $targetServer->processor_id;
        $config->processor;
        foreach((array)$targetServer->processor as $key => $value) {
            // Ensure any modified / overridden values are copied into the server configuration
            $config->processor->{$key} = $value;
        }

        if (!isset($targetServer->processor->core_qty)) {
            $config->processor->is_converged = true;
        }

        // Copy the utilization values of the comparison server
        foreach($this->_copyFields as $field) {
            $config->{$field} = $existingServer->comparison_server->{$field};
        }

        foreach($this->_softwareModelFields as $softwareModelField) {
            if (!$environment->copyVmSoftware($softwareModelField)) {
                unset($config->{$softwareModelField});
                $softwareIdField = $softwareModelField . "_id";
                $config->{$softwareIdField} = null;
            }
        }

        $this->_mapTargetVmSoftware($config, $origProcessor);


        $config->processor->setVmInfo('cores', $config->vm_cores);
        $config->parent_facade_configuration_id = $targetServer->id;
        $config->facade_vm_group = $otherVms;

        return $this;
    }

    /**
     * @param ServerConfiguration $vm
     * @param Processor $origProcessor
     * @return $this
     */
    protected function _mapTargetVmSoftware(ServerConfiguration $vm, Processor $origProcessor)
    {
        $hasSoftware = false;
        foreach($this->_softwareFields as $softwareField) {
            if (!$vm->{$softwareField}) {
                continue;
            }
            $hasSoftware = true;
            break;
        }

        if (!$hasSoftware) {
            return $this;
        }

        /** @var Processor $newProcessor */
        $newProcessor = $vm->processor;

        $transfer = false;

        if ($origProcessor->isOracleX86LicenseModel() && $newProcessor->isIbmLicenseModel()) {
            $transfer = 'to_ibm';
        } else if ($origProcessor->isIbmLicenseModel() && $newProcessor->isOracleX86LicenseModel()) {
            $transfer = 'to_oracleX86';
        }

        if (!$transfer) {
            foreach($this->_softwareFields as $softwareField) {
                $this->_addUsedSoftware($vm->{$softwareField});
            }
            return $this;
        } else {
            foreach ($this->_softwareFields as $softwareField) {
                if (!$vm->{$softwareField}) {
                    continue;
                }
                $softwareModelField = str_replace("_id", "", $softwareField);

                /** @var Software $software */
                $software = $vm->{$softwareModelField};
                switch ($transfer) {
                    case 'to_ibm';
                        if ($software->isOracleX86Architecture() && ($mapSoftware = $software->getIbmCounterpart())) {
                            $this->_addUsedSoftware($software->id, 'copy');
                            $this->_softwareMap[$software->id] = [
                                'map_software_id' => $mapSoftware->id,
                                'type' => 'to_ibm'
                            ];

                            // To cross reference later
                            $vm->mapSoftware($softwareField, $software->id, $mapSoftware->id);

                            unset($vm->{$softwareModelField});
                            $vm->{$softwareField} = $mapSoftware->id;
                            $vm->{$softwareModelField} = $mapSoftware;
                        } else {
                            $this->_addUsedSoftware($software->id);
                        }
                        break;
                    case 'to_oracleX86':
                        if ($software->isIbmArchitecture() && ($mapSoftware = $software->getOracleX86Counterpart())) {
                            $this->_addUsedSoftware($software->id, 'copy');
                            $this->_softwareMap[$software->id] = [
                                'map_software_id' => $mapSoftware->id,
                                'type' => 'to_oracleX86'
                            ];

                            // To cross reference later
                            $vm->mapSoftware($softwareField, $software->id, $mapSoftware->id);

                            unset($vm->{$softwareModelField});
                            $vm->{$softwareField} = $mapSoftware->id;
                            $vm->{$softwareModelField} = $mapSoftware;
                        } else {
                            $this->_addUsedSoftware($software->id);
                        }
                        break;
                }
            }
        }

        return $this;
    }

    /**
     * @param $softwareId
     * @param string $type
     * @return $this
     */
    protected function _addUsedSoftware($softwareId, $type = 'default')
    {
        if ($softwareId) {
            $this->_usedSoftware[$type][] = $softwareId;
            $collection = collect($this->_usedSoftware[$type]);
            $unique = $collection->unique();
            $this->_usedSoftware[$type] = $unique->all();
        }

        return $this;
    }

    /**
     * Create software and feature costs for the given software
     * @param Environment $environment
     * @param SoftwareCost $softwareCost
     * @return $this
     */
    protected function _copySoftware(Environment $environment, SoftwareCost $softwareCost)
    {
        // Only clone if the target will actually be using this software (it's not just for copying purposes)
        $targetSoftwareCost = clone $softwareCost;
        $targetSoftwareCost->featureCosts;
        $targetSoftwareCost->features;
        $targetSoftwareCost->software;
        $targetSoftwareCost->id = null;
        // !important, don't actually load the environment, causes infinite recursion on serialization -__-
        $targetSoftwareCost->environment_id = $environment->id;
        $environment->softwareCosts->push($targetSoftwareCost);

        return $this;
    }

    /**
     * Create software and feature costs for the appropriate counterpart of the given software
     * @param Environment $environment
     * @param SoftwareCost $softwareCost
     * @return $this
     */
    protected function _copyCounterpartSoftware(Environment $environment, SoftwareCost $softwareCost)
    {
        // Clone if we're using a different version of the same software on the target
        $targetSoftwareCost = clone $softwareCost;

        // Pick the counterpart if we mapped the software
        $mapSoftwareId = $this->_softwareMap[$softwareCost->software_type_id]['map_software_id'];
        $mapType = $this->_softwareMap[$softwareCost->software_type_id]['type'];
        $origSoftwareId = $targetSoftwareCost->software_type_id;
        $targetSoftwareCost->software_type_id = $mapSoftwareId;


        // Now we need to map all the features to their counterpart in the other architecture
        $targetSoftwareCost->featureCosts;
        $removeFeatures = [];
        $featureMap = [];

        $targetSoftwareCost->featureCosts = clone $targetSoftwareCost->featureCosts;

        $targetSoftwareCost->featureCosts->transform(function(FeatureCost $featureCost){
            return clone $featureCost;
        });

        /** @var FeatureCost $featureCost */
        foreach($targetSoftwareCost->featureCosts as $featureCost) {
            $featureCost->feature = clone $featureCost->feature;
            /** @var Feature $feature */
            $feature = $featureCost->feature;
            switch($mapType) {
                case 'to_ibm';
                    if ($ibmFeature = $feature->getIbmCounterpart()) {
                        $featureMap[$feature->id] = $ibmFeature;
                        $featureCost->feature = $ibmFeature;
                    } else {
                        $removeFeatures[] = $feature->id;
                    }
                    break;
                case 'to_oracleX86':
                    if ($oracleX86Feature = $feature->getOracleX86Counterpart()) {
                        $featureMap[$feature->id] = $oracleX86Feature;
                        $featureCost->feature = $oracleX86Feature;
                    } else {
                        $removeFeatures[] = $feature->id;
                    }
                    break;
            }
        }

        $targetSoftwareCost->features = clone $targetSoftwareCost->features;

        $targetSoftwareCost->features->transform(function(Feature $feature){
            return clone $feature;
        });

        foreach($removeFeatures as $removeFeatureId) {
            $targetSoftwareCost->featureCosts = $targetSoftwareCost->featureCosts->reject(function(FeatureCost $featureCost) use ($removeFeatureId) {
                return $featureCost->feature_id == $removeFeatureId;
            });
            $targetSoftwareCost->features = $targetSoftwareCost->features->reject(function(Feature $feature) use ($removeFeatureId) {
                return $feature->id == $removeFeatureId;
            });
        }

        foreach($featureMap as $origFeatureId => $newFeature) {
            $targetSoftwareCost->features = $targetSoftwareCost->features->transform(function(Feature $feature) use ($origFeatureId, $newFeature){
                if ($feature->id == $origFeatureId) {
                    return $newFeature;
                }

                return $feature;
            });
        }

        unset($targetSoftwareCost->software);
        $targetSoftwareCost->software;
        $targetSoftwareCost->id = null;
        // !important, don't actually load the environment, causes infinite recursion on serialization -__-
        $targetSoftwareCost->environment_id = $environment->id;
        $environment->softwareCosts->push($targetSoftwareCost);

        return $this;
    }
}