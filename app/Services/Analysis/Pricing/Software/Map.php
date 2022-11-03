<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Software;
use App\Exceptions\AnalysisException;
use App\Models\Project\Environment;
use App\Models\Software\Software;
use App\Models\Software\SoftwareCost;
use App\Services\Analysis\Pricing\Software\Map\Calculator as MapCalculator;

/**
 * @class \App\Services\Analysis\Pricing\Software\Map
 * Think of this class as a global singleton that holds aggregate information
 * about all the software in an ongoing analysis. It's not a great design pattern,
 * but it's our way of handling the OldAnalysisController::analysisSoftwareMap variable.
 * The idea is that other classes implement the \App\Services\Analysis\Pricing\Software\MapAccessTrait
 * This trait then lets them access a Map singleton that will be the same object during different parts of the analysis
 */
class Map
{
    /**
     * @var array
     */
    protected $_map = [];

    /**
     * @var int|object
     */
    protected $_defaultSoftwareId;

    /**
     * @var int|object
     */
    protected $_defaultEnvironmentId;

    /**
     * Allow direct access to thsi
     * @var array
     */
    public $mappedSoftware = [];

    /**
     * @return $this
     */
    public function reset()
    {
        $this->_map = [];
        $this->_defaultSoftwareId = null;
        $this->_defaultEnvironmentId = null;
        $this->mappedSoftware = [];
        return $this;
    }

    /**
     * @return $this
     */
    public function startAnalysis()
    {
        $this->reset();
        return $this;
    }

    /**
     * @param $softwareId
     * @param $environmentId
     * @return $this
     */
    public function setScope($softwareId, $environmentId)
    {
        $this->_defaultSoftwareId = is_object($softwareId) ? $softwareId->id : $softwareId;
        $this->_defaultEnvironmentId = is_object($environmentId) ? $environmentId->id : $environmentId;

        return $this;
    }

    /**
     * @param Environment $environment
     * @return Map
     */
    public function calculateEnvironmentCosts(Environment $environment)
    {
        $this->calculator()->calculateCosts($environment);

        return $this;
    }

    /**
     * @param null $softwareId
     * @param null $environmentId
     * @return Map
     */
    public function setDefaultSoftware($softwareId = null, $environmentId = null)
    {
        return $this->setSoftware([
            "ignoreLicense" => false,
            "cores" => 0,
            "processors" => 0,
            "servers" => 0,
            "totalCost" => 0,
            'drive_qty' => 0,
            "featureCosts" => []
        ], $softwareId, $environmentId);
    }

    /**
     * @param $key
     * @param $value
     * @param $softwareId
     * @param $environmentId
     * @return $this
     */
    public function setData($key, $value, $softwareId = null, $environmentId = null)
    {
        $softwareId = $this->getSoftwareId($softwareId);
        $environmentId = $this->getEnvironmentId($environmentId);

        if (!$this->hasSoftware($softwareId, $environmentId)) {
            $this->setDefaultSoftware($softwareId, $environmentId);
        }

        $this->_map[$environmentId] = $this->_map[$environmentId] ?? [];
        $this->_map[$environmentId][$softwareId] = $this->_map[$environmentId][$softwareId] ?? [];
        $this->_map[$environmentId][$softwareId][$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @param $softwareId
     * @param $environmentId
     * @return $this
     */
    public function addData($key, $value, $softwareId = null, $environmentId = null)
    {
        $softwareId = $this->getSoftwareId($softwareId);
        $environmentId = $this->getEnvironmentId($environmentId);

        $this->_map[$environmentId] = $this->_map[$environmentId] ?? [];
        $this->_map[$environmentId][$softwareId] = $this->_map[$environmentId][$softwareId] ?? [];
        $this->_map[$environmentId][$softwareId][$key] = $this->_map[$environmentId][$softwareId][$key] ?? 0.00;
        $this->_map[$environmentId][$softwareId][$key] += $value;

        return $this;
    }

    /**
     * @param $data
     * @param $softwareId
     * @param $environmentId
     * @return $this
     */
    public function setSoftware($data, $softwareId = null, $environmentId = null)
    {
        if (!is_array($data)) {
            return $this;
        }

        $softwareId = $this->getSoftwareId($softwareId);
        $environmentId = $this->getEnvironmentId($environmentId);

        $this->_map[$environmentId] = $this->_map[$environmentId] ?? [];
        $this->_map[$environmentId][$softwareId] = $this->_map[$environmentId][$softwareId] ?? [];
        $this->_map[$environmentId][$softwareId] = $data;

        return $this;
    }

    /**
     * @param $key
     * @param $softwareId
     * @param $environmentId
     * @param null $default
     * @return null
     */
    public function getData($key, $softwareId = null, $environmentId = null, $default = null)
    {
        $softwareId = $this->getSoftwareId($softwareId);
        $environmentId = $this->getEnvironmentId($environmentId);

        if (!isset($this->_map[$environmentId])) {
            return $default;
        }

        if (!isset($this->_map[$environmentId][$softwareId])) {
            return $default;
        }

        return $this->_map[$environmentId][$softwareId][$key] ?? $default;
    }

    /**
     * @param null $environmentId
     * @return bool|mixed
     * @throws AnalysisException
     */
    public function getEnvironment($environmentId = null)
    {
        $environmentId = $this->getEnvironmentId($environmentId);

        return $this->_map[$environmentId] ?? false;
    }

    /**
     * @param $softwareId
     * @param $environmentId
     * @return bool
     * @throws AnalysisException
     */
    public function getSoftware($softwareId = null, $environmentId = null)
    {
        if (!$this->hasSoftware($softwareId, $environmentId)) {
            return false;
        }

        $softwareId = $this->getSoftwareId($softwareId);
        $environmentId = $this->getEnvironmentId($environmentId);

        return $this->_map[$environmentId][$softwareId];
    }

    /**
     * @param $softwareId
     * @param $environmentId
     * @return bool
     * @throws AnalysisException
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function hasSoftware($softwareId = null, $environmentId = null)
    {
        $softwareId = $this->getSoftwareId($softwareId);
        $environmentId = $this->getEnvironmentId($environmentId);

        if (!isset($this->_map[$environmentId])) {
            return false;
        }

        return isset($this->_map[$environmentId][$softwareId]);
    }

    /**
     * @param $key
     * @param $softwareId
     * @param $environmentId
     * @return bool
     * @throws AnalysisException
     */
    public function hasData($key, $softwareId = null, $environmentId = null)
    {
        $softwareId = $this->getSoftwareId($softwareId);
        $environmentId = $this->getEnvironmentId($environmentId);

        if (!isset($this->_map[$environmentId])) {
            return false;
        }

        if (!isset($this->_map[$environmentId][$softwareId])) {
            return false;
        }

        return isset($this->_map[$environmentId][$softwareId][$key]);
    }

    /**
     * @return array
     */
    public function getAllSoftware()
    {
        return $this->_map;
    }

    /**
     * @param $mixed
     * @return mixed
     * @throws AnalysisException
     */
    public function getSoftwareId($mixed)
    {
        if (is_null($mixed)) {
            $mixed = $this->_defaultSoftwareId;
        }

        return $this->getEntityId($mixed);
    }

    /**
     * @param $mixed
     * @return mixed
     * @throws AnalysisException
     */
    public function getEnvironmentId($mixed)
    {
        if (is_null($mixed)) {
            $mixed = $this->_defaultEnvironmentId;
        }

        return $this->getEntityId($mixed);
    }

    /**
     * @param $mixed
     * @return mixed
     * @throws AnalysisException
     */
    public function getEntityId($mixed)
    {
        if (!is_object($mixed) && is_scalar($mixed)) {
            return $mixed;
        } else if (is_object($mixed) && isset($mixed->id)) {
            return $mixed->id;
        }

        throw new AnalysisException("Invalid entity passed to: " . __METHOD__);
    }

    /**
     * @return MapCalculator
     */
    public function calculator()
    {
        return resolve(MapCalculator::class);
    }

    /**
     * @param null $softwareId
     * @param null $environmentId
     * @return bool|null
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function alreadyHasVmAggregateSoftware($softwareId = null, $environmentId = null)
    {
        $softwareId = $this->getSoftwareId($softwareId);
        $environmentId = $this->getEnvironmentId($environmentId);

        if (!$this->hasSoftware($softwareId, $environmentId)) {
            return false;
        }

        return $this->isVmAggregate($softwareId, $environmentId);
    }

    /**
     * @param null $softwareId
     * @param null $environmentId
     * @return null
     */
    public function isVmAggregate($softwareId = null, $environmentId = null)
    {
        return $this->getData('requires_vm_aggregate', $softwareId, $environmentId, false);
    }

    /**
     * @param null $softwareId
     * @param null $environmentId
     * @return Map
     */
    public function setIsVmAggregate($softwareId = null, $environmentId = null)
    {
        return $this->setData('requires_vm_aggregate', true, $softwareId, $environmentId);
    }

    /**
     * @param $vmKey
     * @param $default
     * @param null $softwareId
     * @param null $environmentId
     * @return null
     */
    public function getVmAggregateValue($vmKey, $default, $softwareId = null, $environmentId = null)
    {
        if (!$this->isVmAggregate($softwareId, $environmentId)) {
            return $default;
        }

        return $this->getData($vmKey);
    }

    /**
     * @return array
     */
    public function getCostsByName()
    {
        $softwares = & $this->mappedSoftware;

        $softwareByNames = [];
        foreach($softwares as $key=>$software) {
            $found = false;
            foreach($softwareByNames as &$s) {
                //search for matching software names
                if($s->name == $software->name) {
                    $found = true;
                    $temp = collect($s->envs)->merge(collect($software->envs));
                    $s->envs = $temp->toArray();
                    foreach($software->features as $feature) {
                        $featureFound = false;
                        foreach($s->features as &$f) {
                            if($f->name == $feature->name) {
                                $featureFound = true;
                                $f->envs = collect($f->envs)->merge(collect($feature->envs))->toArray();
                                break;
                            }
                        }
                        if(!$featureFound) {
                            $tempFeature = clone($feature);
                            $tempFeature->envs = [];
                            foreach($feature->envs as $en) {
                                $tempFeature->envs[] = clone($en);
                            }
                            $s->features[] = $tempFeature;
                        }
                    }
                    break;
                }
            }
            if(!$found) {
                $temp = clone($software);
                $temp->features = [];
                $temp->envs = [];
                foreach($software->features as $feat) {
                    $featureFound = false;
                    foreach($temp->features as $f) {
                        if($feat->name == $f->name) {
                            $featureFound = true;
                            $f->envs = collect($f->envs)->merge(collect($feat->envs))->toArray();
                            break;
                        }
                    }
                    if(!$featureFound)
                        $temp->features[] = clone($feat);
                }
                foreach($software->envs as $en) {
                    $temp->envs[] = clone($en);
                }
                $softwareByNames[] = $temp;
            }
        }
        return $softwareByNames;
    }
}