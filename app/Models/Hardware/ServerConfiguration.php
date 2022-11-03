<?php
namespace App\Models\Hardware;

use App\Models\Project\Environment;
use App\Models\Software\Software;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserManagement\User;

/**
 * Class ServerConfiguration
 * @package App\Models\Hardware
 * @property string $database_li_name
 * @property string $ads_database_type
 * @property string $ads_service_type
 * @property int $ram
 * @property int $interconnect_id
 * @property int $chassis_id
 * @property int $user_id
 * @property int $id
 * @property string $type
 * @property int $physical_configuration_id
 * @property int $qty
 * @property int $total_qty
 * @property int $is_converged
 * @property Processor $processor
 * @property float $useable_storage
 * @property float $raw_storage
 * @property float $acquisition_cost
 * @property float $system_software_list_price
 * @property float $system_software_discount_rate
 * @property float $hardware_warranty_period
 * @property float $annual_system_software_maintenance_list_price
 * @property float $annual_system_software_maintenance_discount_rate
 * @property float $annual_usage_list_price
 * @property float $annual_usage_discount_rate
 * @property float $discount_rate
 * @property float $annual_maintenance_list_price
 * @property float $annual_maintenance_discount_rate
 * @property float $kilo_watts
 * @property Software $os
 * @property Software $middleware
 * @property Software $hypervisor
 * @property Software $database
 * @property int $parent_configuration_id
 * @property float $computedRpm
 * @property float $computedRam
 * @property mixed $deployment_option
 * @property float $cpu_utilization
 * @property float $ram_utilization
 * @property ServerConfiguration physicalServer
 * @property int $processor_id
 * @property int $vm_cores
 * @property string $vm_id
 * @property string $serial_number
 * @property int $iops
 * @property int $baseRam
 * @property float $baseRpm
 * @property string $environment_name
 * @property string $environment_detail
 * @property string $workload_type
 * @property string $location
 * @property int $additionalExisting
 * @property int $baseCores
 * @property int $extra_qty
 * @property Environment $environment
 * @property int $environment_id
 * @property int $os_id
 * @property int $middleware_id
 * @property int $hypervisor_id
 * @property int $database_id
 * @property int $parent_facade_configuration_id
 * @property array $facade_vm_group
 * @property Manufacturer $manufacturer
 * @property int $model_id
 * @property Server $server
 * @property ServerConfiguration $parentServer
 * @property int $licensed_cores
 * @property string $ads_compute_tier
 * @property boolean $is_include_burastable
 */
class ServerConfiguration extends Model
{

    const TYPE_PHYSICAL = 'physical';
    const TYPE_VM = 'vm';
    const TYPE_OPTIMAL = 'optimal';

    const HYPERTHREADING_SUPPORTED = 'hyperthreading_supported';
    const HYPERTHREADING_UNSUPPORTED = 'hyperthreading_unsupported';
    const PARTIALCORES_SUPPORTED = 'partialcores_supported';
    const PARTIALCORES_UNSUPPORTED = 'partialcores_unsupported';

    /**
     * @var array
     */
    protected static $_unsupportedHyperThreadingManufacturers = ['IBM', 'ORACLE', 'FUJITSU'];

    /**
     * @var array
     */
    protected static $_supportedPartialCoresManufacturers = ['IBM', 'ORACLE', 'FUJITSU'];

    protected $table = 'server_configurations';
    protected $guarded = ['id'];

    /**
     * Append extra fields to the JSON / toArray response
     * @var bool
     */
    protected $_addExtraJson = false;

    /**
     * @var array
     */
    protected $_vmCopyFields = ['processor_id', 'server_id', 'manufacturer_id', 'manufacturer', 'server'];

    /**
     * @var array
     */
    protected $_jsonFields = ['manufacturer', 'processor', 'server'];

    /**
     * @var array
     */
    protected $_vmFallbackFields = ['cpu_utilization', 'ram_utilization', 'serial_number', 'location', 'environment_detail', 'workload_type'];

    /**
     * @var array
     */
    protected $_vmCloneObjects = ['processor'];

    /**
     * @var $_comparisonServer
     */
    protected $_comparisonServer;

    /**
     * @var array
     */
    protected $_vmTotals;

    /**
     * @var bool
     */
    protected $_isRealProcessorSet = false;

    /**
     * @var array
     */
    protected $_softwareMap = [];

    /*
     * Can't be done with the current laravel version, but when they upgrade to 5.5>=, we should manage the parent/child relationship that exists between physical servers + VMs through the retrieved event listener, rather than the specific code we have in the *Analyzer service classes to pull down inherited values

    protected static function boot()
    {
        parent::boot();

        static::retrieved(function($model) {
            //if type='vm' + physical_configuration_id IS not null
                //pull down values like Location/Workload/Etc.
        });
    }
    */

    //region Relationships

    public function processor() {
        return $this->belongsTo(Processor::class);
    }

    public function manufacturer() {
        return $this->belongsTo(Manufacturer::class);
    }

    public function server() {
        return $this->belongsTo(Server::class,'model_id');
    }

    public function chassis() {
        return $this->belongsTo(InterconnectChassis::class,'chassis_id');
    }

    public function nodes() {
        return $this->hasMany(ServerConfiguration::class,'parent_configuration_id', 'id');
    }

    public function environment() {
        return $this->belongsTo(Environment::class,'environment_id');
    }

    public function os() {
        return $this->belongsTo(Software::class,'os_id');
    }

    public function middleware() {
        return $this->belongsTo(Software::class,'middleware_id');
    }

    public function hypervisor() {
        return $this->belongsTo(Software::class,'hypervisor_id');
    }

    public function database() {
        return $this->belongsTo(Software::class,'database_id');
    }

    /*public function databaseLi() {
        return $this->belongsTo(Software::class,'database_li_id');
    }*/

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function interconnect(){
        return $this->belongsTo(InterconnectChassis::class, 'interconnect_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function physicalServer()
    {
        return $this->belongsTo(__CLASS__, 'physical_configuration_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parentServer()
    {
        return $this->belongsTo(__CLASS__, 'parent_configuration_id');
    }

    /*public function osMod() {
        return $this->belongsTo(SoftwareModifier::class, 'os_mod_id');
    }
    public function middlewareMod() {
        return $this->belongsTo(SoftwareModifier::class, 'middleware_mod_id');
    }
    public function hypervisorMod() {
        return $this->belongsTo(SoftwareModifier::class,'hypervisor_mod_id');
    }
    public function databaseMod() {
        return $this->belongsTo(SoftwareModifier::class,'database_mod_id');
    }*/

    //endregion

    /**
     * @return bool
     */
    public function isPhysical()
    {
        return !$this->type || $this->type == self::TYPE_PHYSICAL;
    }

    /**
     * @return bool
     */
    public function isVm()
    {
        return $this->type == self::TYPE_VM;
    }

    /**
     * @return bool
     */
    public function hasPhysicalConfigurationId()
    {
        return $this->physical_configuration_id ? true : false;
    }

    /**
     * @return bool
     */
    public function hasChildrenVMs()
    {
        return self::where('physical_configuration_id', $this->id)->exists();
    }

    /**
     * @return int
     */
    public function getQty()
    {
        return ($this->total_qty || $this->total_qty === 0)
            ? $this->total_qty
            : $this->qty;
    }

    /**
     * @return int
     */
    public function isConverged()
    {
        return (bool)$this->is_converged;
    }

    /**
     * @return float|int
     */
    public function getTotalAllocatedCores()
    {
        if (!$this->environment_id || !$this->id || !$this->isPhysical()) {
            return $this->getTotalCores();
        }

        return $this->getVmTotals()['total_vm_cores'];
    }

    /**
     * @return mixed
     */
    public function getTotalAllocatedRam()
    {
        return $this->getVmTotals()['total_vm_ram'];
    }

    /**
     * @return array|float
     */
    public function getVmTotals()
    {
        if (is_null($this->_vmTotals)) {
            $this->_vmTotals = \DB::table('server_configurations')->where('environment_id', $this->environment_id)
                ->where('physical_configuration_id', $this->id)
                ->where('type', self::TYPE_VM)
                ->select([
                    \DB::raw('SUM(vm_cores) as `total_vm_cores`'),
                    \DB::raw('SUM(ram) as `total_vm_ram`')
                ])->first();

            if (!$this->_vmTotals) {
                $this->_vmTotals = [
                    'total_vm_cores' => 0,
                    'total_vm_ram' => 0
                ];
            } else {
                $this->_vmTotals = (array)$this->_vmTotals;
            }

            if (!$this->isPartialCoresSupported()) {
                $this->_vmTotals['total_vm_cores'] = intval($this->_vmTotals['total_vm_cores']);
            }
        }

        return $this->_vmTotals;
    }

    /**
     * @param ServerConfiguration $configuration
     * @param bool $forJson
     * @return $this
     */
    public function copyPhysicalAttributes(ServerConfiguration $configuration = null, $forJson = true)
    {
        $configuration = $configuration ?? $this->physicalServer;

        $this->qty = ($this->qty ?: 1) * ($configuration->qty ?: 1);

        foreach($this->_vmCopyFields as $key) {
            $this->{$key} = $configuration->{$key};
        }

        foreach($this->_vmCloneObjects as $key) {
            $this->{$key} = clone $configuration->{$key};
            $this->{$key}->id = null;
            $this->{$key}->is_facade = true;
        }

        // Calculate the CPM value
        if ($configuration->getTotalCores() > 0) {
            /**
             * (1) Physical Servers without VMS
            CPM is simply the total Physical CPM number from the CPM table
            CPM = Physical CPM x Utilization x Variance

            (2) Virtual Machine on a Physical Server that IS NOT overallocated
            Virtual CPM =              Physical CPM
             *              ----------------------------------------------- x Virtual Cpu of specific VM x Utilization x Variance
                                     Physical Cores * 2

            (3) Virtual Machine on a Physical Server that IS overallocated
            Virtual CPM =              Physical CPM
                           ----------------------------------------------- x Virtual Cpu of specific VM x Utilization x Variance
                                       Total Virtual Cores
             */
            $totalCores = $configuration->getTotalCores();

            if ($configuration->isHyperThreadingSupported()) {
                // Double the cores if hyper threading is supported
                $totalCores *= 2;
            }

            $this->processor->rpm  =
                ($this->vm_cores * $configuration->getCpm()) /
                max($totalCores, $configuration->getTotalAllocatedCores());
        } else {
            $this->processor->rpm = 0;
        }

        $this->processor->setVmInfo('cores', $this->vm_cores);

        foreach($this->_vmFallbackFields as $key) {
            $this->{$key} = $this->{$key} ?: $configuration->{$key};
        }

        if(floatval($configuration->ram) < floatval($configuration->getTotalAllocatedRam())) {
            /**
             * If the server is overallocated
             * Virtual RAM =              Physical RAM
             *                 ----------------------------------------------- x Virtual RAM of specific VM x Util x Variance
             *                          Total Virtual RAM

             */
            $this->ram = intval(ceil((floatval($configuration->ram) / floatval($configuration->getTotalAllocatedRam())) * floatval($this->ram)));
        }

        if ($forJson) {
            foreach ($this->_jsonFields as $key) {
                $this->{$key} = json_decode(json_encode($this->{$key}));
            }

            $this->_addExtraJson = true;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function makeVmCompatible()
    {
        $this->vm_cores = $this->getTotalCores();
        if ($this->isHyperThreadingSupported()) {
            // Double the number of VM cores
            $this->vm_cores *= 2;
        }
        $this->vm_id = $this->serial_number;
        $this->processor->setVmInfo('cores', $this->vm_cores);

        return $this;
    }

    /**
     * @return $this
     */
    public function makePhysicalCompatible()
    {
        $this->processor = new Processor();
        $this->processor->socket_qty = 1;
        $this->processor->core_qty = $this->vm_cores;
        $this->processor->setVmInfo('cores', $this->vm_cores);

        return $this;
    }

    public function toArray()
    {
        $data = parent::toArray();

        if ($this->_addExtraJson) {
            foreach ($this->_jsonFields as $key) {
                $data[$key] = $this->{$key};
            }
        }

        return $data;
    }

    /**
     * @return float|int
     */
    public function getTotalCores()
    {
        if (!$this->processor) {
            return 0;
        }
        if ($this->isConverged() && ($this->processor->total_cores ?? 0)) {
            return $this->processor->total_cores;
        }

        return $this->processor->core_qty * $this->processor->socket_qty;
    }

    /**
     * @return float
     */
    public function getCpm()
    {
        return $this->processor->rpm;
    }

    /**
     * @param ServerConfiguration $serverConfiguration
     * @return $this
     */
    public function setComparisonServer(ServerConfiguration $serverConfiguration)
    {
        $this->_comparisonServer = $serverConfiguration;
        return $this;
    }

    /**
     * @return ServerConfiguration
     */
    public function getComparisonServer()
    {
        if (!$this->_comparisonServer) {
            return $this;
        }
        return $this->_comparisonServer;
    }

    /**
     * @return bool
     */
    public function hasComparisonServer()
    {
        return $this->_comparisonServer ? true : false;
    }

    /**
     * @return ServerConfiguration
     */
    public function getComparisonServerAttribute()
    {
        return $this->getComparisonServer();
    }

    /**
     * @param Environment|null $environment
     * @return float
     */
    public function getComputedRam(Environment $environment = null)
    {
        $environment = $environment ?? $this->environment;
        $baseRam = round($this->ram * $environment->getCagrMultiplier());
        $ramUtilization = $this->ram_utilization ?: $environment->getRamUtilization();
        return round($baseRam * ($ramUtilization / 100.0));
    }

    /**
     * @param Environment|null $environment
     * @return float
     */
    public function getComputedRpm(Environment $environment = null)
    {
        $environment = $environment ?? $this->environment;
        $baseRpm = round($this->processor->rpm * $environment->getCagrMultiplier());
        $cpuUtilization = $this->cpu_utilization ?: $environment->getCpuUtilization();
        return round($baseRpm * ($cpuUtilization / 100.0));
    }

    /**
     * @param Environment|null $environment
     * @return float
     */
    public function getComputedCores(Environment $environment = null)
    {
        $environment = $environment ?? $this->environment;
        $baseCores = $this->processor ? round($this->getTotalCores() * $environment->getCagrMultiplier()) : 0;
        $cpuUtilization = $this->cpu_utilization ?: $environment->getCpuUtilization();
        return ceil($baseCores * ($cpuUtilization / 100.0));
    }

    /**
     * @return bool
     */
    public function isHyperThreadingSupported()
    {
        if (!$this->processor || !$this->processor->manufacturer) {
            return false;
        }

        return !in_array(trim(strtoupper($this->processor->manufacturer->name)), self::$_unsupportedHyperThreadingManufacturers);
    }

    /**
     * @return bool
     */
    public function isPartialCoresSupported()
    {
        if (!$this->processor || !$this->processor->manufacturer) {
            return false;
        }

        return in_array(trim(strtoupper($this->processor->manufacturer->name)), self::$_supportedPartialCoresManufacturers);
    }

    /**
     * @return Server
     */
    public function getRealServer()
    {
        return $this->parent_configuration_id
            ? $this->parentServer->server
            : $this->server;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function setRealProcessor(&$cache = [])
    {
        /* Adding `$environment->is_optimal` check prevents this function
         from updating OT environment ServerConfiguration's processor to a
         processor with empty model_name.
        
         This issue is due to duplicate CPU entries created by the CPMImport. Which
         breaks the "Optimal Target Processor Model Filter" feature
        */
        $environment = $this->environment()->getResults();
        
        if ($this->_isRealProcessorSet || $environment->is_optimal) {
            return $this;
        }

        $data = $this->processor->getHashData();
        $server = $this->getRealServer();
        $serverName = $server ? strtoupper($server->name ?: '') : '';

        if ($data['model_name'] != $serverName) {
            $data['model_name'] = $serverName;

            // 1. Generate string key from data
            $dataKey = json_encode($data);
            if (array_key_exists($dataKey, $cache)) {
                // 2. If entry found in cache, use it
                $modelProcessor = $cache[$dataKey];
            } else {
                // 3. Search for processor with model_name included
                $modelProcessor = Processor::findByHashData($data)->first();
                if ($modelProcessor) {
                    $cache[$dataKey] = $modelProcessor;
                } else if (strlen($data['model_name'])) {
                    // Try without model_name
                    $data['model_name'] = '';
                    $modelProcessor = Processor::findByHashData($data)->first();
                    if ($modelProcessor) {
                        $cache[$dataKey] = $modelProcessor;
                    }
                }
            }

            if ($modelProcessor && $this->processor_id != $modelProcessor->id) {
                $this->processor_id = $modelProcessor->id;
                unset($this->processor);
                $this->save();
                $this->processor = $modelProcessor;
            }
        }

        $this->_isRealProcessorSet = true;

        return $this;
    }

    /**
     * @param $type
     * @param $origId
     * @param $newId
     * @return $this
     */
    public function mapSoftware($type, $origId, $newId)
    {
        if (!isset($this->_softwareMap[$type])) {
            $this->_softwareMap[$type] = [];
        }

        $this->_softwareMap[$type][$origId] = $newId;

        return $this;
    }

    /**
     * @param null $type
     * @param null $origId
     * @return array|mixed
     */
    public function getMappedSoftware($type = null, $origId = null)
    {
        if ($type && $origId) {
            return ($this->_softwareMap[$type] ?? [] )[$origId] ?? null;
        }
        return $type
            ? $this->_softwareMap[$type] ?? []
            : $this->_softwareMap;
    }
}
