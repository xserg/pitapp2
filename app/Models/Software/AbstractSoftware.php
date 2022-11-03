<?php
/**
 *
 */

namespace App\Models\Software;


use App\Models\Hardware\Processor;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AbstractSoftware
 * @package App\Models\Software
 * @property int $id
 * @property string $full_name
 * @property int $support_type
 * @property string $name
 * @property string $architecture
 * @property string $cost_per
 * @property float $support_cost_percent
 * @property string $formula
 */
class AbstractSoftware extends Model
{
    const COST_PER_NUP = 'NUP';
    const COST_PER_CORE = 'Core/vCPU';
    const COST_PER_PROCESSOR = 'Processor';
    const COST_PER_DISK = 'Disk';

    const SUPPORT_TYPE_PERCENT_LICENSE = 0;
    const SUPPORT_TYPE_LIST_PRICE = 1;

    const ARCHITECTURE_VM = 'VM';
    const ARCHITECTURE_IBM = 'IBM';
    const ARCHITECTURE_ORACLE_X86 = 'x86/Oracle';
    const ARCHITECTURE_VM_IBM = 'VM/IBM';


    static $_softwareMapCache = [];

    public function getFullNameAttribute()
    {
        return $this->architecture ? $this->name . ' (' . $this->architecture . ')' : $this->name;
    }

    /**
     * @return bool
     */
    public function isVMArchitecture()
    {
        return preg_match('/^' . self::ARCHITECTURE_VM . '/i', $this->architecture);
    }

    /**
     * @param $vmString
     * @param string $default
     * @return string
     */
    public function ifVmArchitecture($vmString, $default = '')
    {
        return $this->isVMArchitecture() ? $vmString : $default;
    }

    /**
     * @param Processor $processor
     * @return string
     */
    public function getCoreQty($processor)
    {
        $default = $processor->core_qty * ($processor->isAWS ? 1 : ($processor->socket_qty ?: 1));

        if (!$processor instanceof Processor) {
            return $default;
        }

        if (isset($processor->is_converged) && $processor->is_converged) {
            $default = $processor->total_cores;
        }

        return $this->ifVmArchitecture($processor->getVmInfo('cores'), $default);
    }

    /**
     * @return bool
     */
    public function isCostPerCore()
    {
        return $this->cost_per == self::COST_PER_CORE;
    }

    /**
     * @return bool
     */
    public function isCostPerProcessor()
    {
        return $this->cost_per == self::COST_PER_PROCESSOR;
    }

    /**
     * @return bool
     */
    public function isCostPerSocket()
    {
        return $this->isCostPerProcessor();
    }

    /**
     * @return bool
     */
    public function isSupportPercentLicense()
    {
        return $this->support_type == self::SUPPORT_TYPE_PERCENT_LICENSE;
    }

    /**
     * @return bool
     */
    public function isSupportListPrice()
    {
        return $this->support_type == self::SUPPORT_TYPE_LIST_PRICE;
    }

    /**
     * @return bool
     */
    public function requiresVmAggregate()
    {
        /*
         * Vm Aggregate is only used on existing environments.
         * So we only care about how the support cost is determined
         * If the support cost has its own calculations, we can ignore
         * whatever is on the license since the license cost is NEVER added to existing
         */
        $checkKey = 'cost_per';
        if ($this->isSupportListPrice()) {
            $checkKey = 'annual_cost_per';
        }

        switch ($this->{$checkKey}) {
            case Software::COST_PER_PROCESSOR:
                return true;
                break;
            case Software::COST_PER_CORE:
                if (!$this->isVMArchitecture()) {
                    return true;
                }
                break;
        }

        return false;
    }

    /**
     * The name of this function makes more sense when we're checking for software
     * that applies to physical hosts on a physical/vm environment
     * @return bool
     */
    public function appliesToPhysicalServer()
    {
        return $this->requiresVmAggregate();
    }

    /**
     * @return bool
     */
    public function isIbmArchitecture()
    {
        return in_array($this->architecture, [self::ARCHITECTURE_IBM, self::ARCHITECTURE_VM_IBM]);
    }

    /**
     * @return bool
     */
    public function isOracleX86Architecture()
    {
        return in_array($this->architecture, [self::ARCHITECTURE_ORACLE_X86, self::ARCHITECTURE_VM]);
    }

    /**
     * @return bool|$this
     */
    public function getIbmCounterpart()
    {
        if (!isset(static::$_softwareMapCache[$this->id])) {

            switch($this->architecture) {
                case self::ARCHITECTURE_VM:
                    $architecture = self::ARCHITECTURE_VM_IBM;
                    break;
                default:
                    $architecture = self::ARCHITECTURE_IBM;
                    break;
            }

            /** @var AbstractSoftware $firstSoftware */
            $firstSoftware = static::where('name', $this->name)
                ->where('architecture', $architecture)
                ->first();

            static::$_softwareMapCache[$this->id] = $firstSoftware ? $firstSoftware : false;
        }

        return static::$_softwareMapCache[$this->id];
    }

    /**
     * @return bool|$this
     */
    public function getOracleX86Counterpart()
    {
        if (!isset(static::$_softwareMapCache[$this->id])) {

            switch($this->architecture) {
                case self::ARCHITECTURE_VM_IBM:
                    $architecture = self::ARCHITECTURE_VM;
                    break;
                default:
                    $architecture = self::ARCHITECTURE_ORACLE_X86;
                    break;
            }

            /** @var AbstractSoftware $firstSoftware */
            $firstSoftware = static::where('name', $this->name)
                ->where('architecture', $architecture)
                ->first();

            static::$_softwareMapCache[$this->id] = $firstSoftware ? $firstSoftware : false;
        }

        return static::$_softwareMapCache[$this->id];
    }
}