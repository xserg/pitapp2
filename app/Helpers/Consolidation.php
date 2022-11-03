<?php
/**
 *
 */
namespace App\Helpers;

use App\Models\Hardware\ServerConfiguration;
use App\Models\Project\Environment;
use App\Models\Hardware\Processor;
use Illuminate\Support\Facades\Log;

class Consolidation
{
    /**
     * @var array
     */
    protected $_constraints = ['environment_detail', 'location', 'workload_type'];

    /**
     * @var array
     */
    protected $_emptyValues = ["", null];

    /**
     * @param array $existing
     * @param $targetConfigs
     * @return mixed
     */
    public function matchTargetConstraints($existing, $targetConfigs)
    {
        $bestMatch = null;
        $bestMatchScore = -1;
        /** @var mixed $target */
        foreach ($targetConfigs as $target) {
            $currentScore = 0;
            /** @var string $constraint */
            foreach ($this->_constraints as $constraint) {
                $result = $this->_checkConstraint($target->{$constraint}, $existing->{$constraint});
                if ($result === false) {
                    // If the servers can't be consolidated, skip to the next target
                    // hence the `2`
                    continue 2;
                }

                $currentScore += $result;
            }

            if ($currentScore > $bestMatchScore) {
                $bestMatchScore = $currentScore;
                $bestMatch = $target;
            }
        }

        return $bestMatch;
    }

    /**
     * @param $obj
     * @return bool
     */
    protected function _emptyConstraint($obj)
    {
        foreach ($this->_emptyValues as $emptyValue) {
            if ($emptyValue === $obj) {
                return true;
            }
        }

        return false;
    }

    protected function _checkConstraint($existing, $target)
    {
        //Check if either constraint is a wild card. If it is, it is a success, but don't increment the matches
        if ($this->_emptyConstraint($target) || $this->_emptyConstraint($existing)) {
            return 0;
        }

        if ($target === $existing) {
            //If they match, we want to increment the counter
            return 1;
        }

        //If the don't match, we can't consolidate to this target
        return false;
    }

    /**
     * @param $sockets
     * @return int
     */
    public function sumSockets($sockets)
    {
        if(gettype($sockets) == "string") {
            $socketArray = explode(',', $sockets);
            $totalSockets = 0;
            foreach($socketArray as $socket) {
                $totalSockets += (int)$socket;
            }
            return $totalSockets;
        } else {
            return (int)$sockets;
        }
    }

    /**
     * Combine converged nodes
     * @param $configs
     * @return array
     */
    public function combineConverged(&$configs)
    {
        $cache = [];
        $withCombinedConverged = [];
        $parentIds = [];
        foreach ($configs as $config) {
            if (!$config->is_converged && $config->parent_configuration_id == null) {
                $withCombinedConverged[] = $config;
            } else if ($config->is_converged && !$config->parent_configuration_id) {
                $parentIds[] = $config->id;
            }
        }
        foreach ($parentIds as $id) {
            $rpm = 0;
            $ram = 0;
            $childConfigs = [];
            $socket_qty = 0;
            $core_qty = [];
            $iops = 0;
            $total_cores = 0;
            $useable_storage = 0;
            $raw_storage = 0;
            $ghz = [];
            $processors = [];
            $parent = false;
            $isHyperThreaded = true;
            $manufacturer = false;
            /** @var \stdClass|ServerConfiguration $config */
            foreach ($configs as $config) {
                if ($config->parent_configuration_id == $id) {
                    $config->setRealProcessor($cache);
                    $ram += $config->ram * $config->qty;
                    $rpm += $config->processor->rpm * $config->qty;
                    $socket_qty += $config->processor->socket_qty * $config->qty;
                    $useable_storage += $config->useable_storage * $config->qty;
                    $iops += $config->iops * $config->qty;
                    $raw_storage += $config->raw_storage * $config->qty;
                    $core_qty[] = $config->processor->core_qty;
                    $ghz[] = $config->processor->ghz;
                    $processors[] = $config->processor->name;
                    $childConfigs[] = $config;
                    $total_cores += $config->processor->socket_qty * $config->processor->core_qty * $config->qty;
                    $isHyperThreaded = $config->isHyperThreadingSupported();
                    $manufacturer = $config->processor->manufacturer;
                }
                if ($config->id == $id) {
                    $cc = json_encode($config);
                    $parent = json_decode($cc);
                    unset($cc);
                    $parent->processor = new Processor();
                }
            }
            if ($parent) {
                $parent->processor->rpm = $rpm;
                $parent->processor->ghz = implode(', ', $ghz);
                $parent->processor->socket_qty = $socket_qty;
                $parent->processor->total_cores = $total_cores;
                $parent->processor->name = implode(', ', $processors);
                if ($manufacturer) {
                    $parent->processor->manufacturer = $manufacturer;
                    $parent->processor->manufacturer_id = $manufacturer->id;
                }
                $parent->ram = $ram;
                $parent->useable_storage = $useable_storage;
                $parent->iops = $iops;
                $parent->raw_storage = $raw_storage;
                $parent->configs = $childConfigs;
                $parent->is_hyperthreading_supported = $isHyperThreaded;
                $withCombinedConverged[] = $parent;
            }
        }
        return $withCombinedConverged;
    }
}
