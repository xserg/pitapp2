<?php namespace App\Models\Project;

use App\Models\Hardware\Server;
use App\Models\Hardware\ServerConfiguration;
use App\Models\Software\Software;
use App\Models\Software\SoftwareCost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use App\Model\Hardware\AmazonStorage;
use App\Models\Project\{Environment\Analysis, Project, EnvironmentType, Provider, Currency, Region};
use App\Models\Hardware\InterconnectChassis;
use App\Models\Project\Cloud\PaymentOption;

/**
 * Class Environment
 * @package App\Models\Project
 * @property Collection[] $serverConfigurations
 * @property int $is_existing
 * @property int $is_optimal
 * @property string $processor_type_constraint
 * @property array $processor_models_constraint
 * @property string $name
 * @property string $existing_environment_type
 * @property float $vm_hardware_annual_maintenance
 * @property EnvironmentType $environmentType
 * @property int $is_incomplete
 * @property int $id
 * @property Provider $provider
 * @property string $instanceType
 * @property float $useable_storage
 * @property float $total_storage
 * @property float $ads_additional_storage
 * @property float $ads_free_storage
 * @property int $ads_free_instances
 * @property float $ads_free_per_instance
 * @property float $ads_total_storage
 * @property float $ads_storage_surplus
 * @property float $ghz
 * @property string $cpu_architecture
 * @property float $purchase_price
 * @property float $system_software_purchase_price
 * @property float $total_maintenance
 * @property float $total_hardware_maintenance_per_year
 * @property float $total_hardware_usage_per_year
 * @property float $total_hardware_warranty_savings
 * @property array $total_hardware_warranty_per_year
 * @property float $total_system_software_maintenance
 * @property float $total_system_software_maintenance_per_year
 * @property float $power_cost_per_year
 * @property float $os_support_per_year
 * @property float $hypervisor_support_per_year
 * @property float $middleware_support_per_year
 * @property float $database_support_per_year
 * @property float $os_license
 * @property float $hypervisor_license
 * @property float $middleware_license
 * @property float $database_license
 * @property float $total_power
 * @property float $compute_power_cost_per_year
 * @property float $storage_power_cost_per_year
 * @property float $cost_per_kwh
 * @property int $total_qty
 * @property float $metered_cost
 * @property Project $project
 * @property float $raw_storage
 * @property int $storage_type
 * @property string $power_cost_formula
 * @property float $pCost
 * @property float $cCost
 * @property float $dFactor
 * @property string $driveSize
 * @property string $driveType
 * @property float $power_cost
 * @property float $compute_power_cost
 * @property float $storage_power_cost
 * @property float $total_hardware_maintenance
 * @property float $total_hardware_usage
 * @property float $total_fte_cost
 * @property string $storage_power_cost_formula
 * @property float $fte_salary
 * @property float $fte_qty
 * @property Collection|SoftwareCost[] $softwareCosts
 * @property string $target_analysis
 * @property array|object $analysis
 * @property float $max_network
 * @property float $network_costs
 * @property float $network_overhead
 * @property float $network_per_yer
 * @property float $lowest_price
 * @property array $groupedNodes
 * @property float $migration_services
 * @property float $total_cost
 * @property float $remaining_deprecation
 * @property float $total_storage_maintenance
 * @property float $storage_purchase_price
 * @property float $iops_purchase_price
 * @property float $storage_maintenance
 * @property float $ramUtilMatch
 * @property float $cpuUtilMatch
 * @property float $storage_purchase
 * @property float $cost_per_year
 * @property float $cloud_bandwidth
 * @property float $networkGbMonth
 * @property float $bandwidths
 * @property float $bandwidthCosts
 * @property float $max_utilization
 * @property float $gbMonthPrice
 * @property float $iops_per_gb
 * @property float $monthPrice
 * @property float $storageDisks
 * @property float $monthly_storage_purchase
 * @property float $diskSize
 * @property int $iops
 * @property int $iopsSurplus
 * @property int $iopsDeficit
 * @property int $iopsGbNeeded
 * @property int $monthly_iops_purchase
 * @property float $iopsDisksNeeded
 * @property float $iopsPerDisk
 * @property int $totalIops
 * @property float $iopsMonthPrice
 * @property array $storage_maintenance_tiered
 * @property float $initial_monthly_iops_price
 * @property float $initial_iops_purchase_price
 * @property float $monthly_storage_maintenance
 * @property int $cloud_storage_type
 * @property int $provisioned_iops
 * @property float $onDemandPurchase
 * @property float $onDemandMaintenance
 * @property float $upfrontPurchase
 * @property float $upfront3Purchase
 * @property float $upfrontMaintenance
 * @property array $consolidationMap
 * @property \stdClass $cheapestEnv
 * @property int $is_dirty
 * @property float $cpu_utilization
 * @property float $ram_utilization
 * @property float $baseRpm
 * @property float $variance
 * @property Collection $analyses
 * @property int $copy_vm_os
 * @property int $copy_vm_middleware
 * @property int $copy_vm_hypervisor
 * @property int $copy_vm_database
 * @property string $converged_cloud_type
 * @property int $drive_qty
 * @property string $cloud_support_costs
 */
class Environment extends Model
{
    const EXISTING_ENVIRONMENT_TYPE_PHYSICAL = 'physical_servers';
    const EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM = 'physical_servers_vm';
    const EXISTING_ENVIRONMENT_TYPE_VM = 'vm';

    const ENVIRONMENT_TYPE_CLOUD = 'Cloud';
    const ENVIRONMENT_TYPE_CONVERGED = 'Converged';
    const ENVIRONMENT_TYPE_COMPUTE = 'Compute';
    const ENVIRONMENT_TYPE_COMPUTE_STORAGE = 'Compute + Storage';

    const INSTANCE_TYPE_RDS = 'RDS';
    const INSTANCE_TYPE_EC2 = 'EC2';
    const INSTANCE_TYPE_AZURE = 'Azure';
    const INSTANCE_TYPE_GOOGLE = 'Google';
    const INSTANCE_TYPE_IBMPVS = 'IBMPVS';

    const STORAGE_TYPE_HDD_15K = 1;
    const STORAGE_TYPE_HDD_10K = 2;
    const STORAGE_TYPE_HDD_7_2K = 3;
    const STORAGE_TYPE_SSD = 4;

    const CLOUD_STORAGE_TYPE_AZURE = 1;
    const CLOUD_STORAGE_TYPE_AWS_GENERAL_PURPOSE = 2;
    const CLOUD_STORAGE_TYPE_AWS_PROVISIONED = [3, 7];
    const CLOUD_STORAGE_TYPE_GOOGLE = 8;

    const CLOUD_SUPPORT_COSTS_NONE = 'none';
    const CLOUD_SUPPORT_COSTS_DEFAULT = 'default';
    const CLOUD_SUPPORT_COSTS_CUSTOM = 'custom';

    const DEFAULT_RAM_UTILIZATION = 100;
    const DEFAULT_CPU_UTILIZATION = 50;

    const ANALYSIS_CHUNK_SIZE = 14170689;

    protected $table = 'environments';

    protected $guarded = ['id'];
    
    protected $attributes = [
        /** @var array environments's servers processor models filter */
        'processor_models_constraint' => '[]',
    ];
    
    protected $casts = [
        'processor_models_constraint' => 'array',
    ];

    /**
     * @var bool
     */
    public $cpuUtilMatch;

    /**
     * @var bool
     */
    public $ramUtilMatch;

    /**
     * @var null|ServerConfiguration
     */
    protected $_lastPhysicalServer;

    /**
     * @var null|\stdClass
     */
    protected $_cloudSummary;

    /**
     * @var bool
     */
    protected $_treatAsExisting = false;

    /**
     * @var Collection
     */
    protected $_additionalServerConfigurations;

    /**
     * @var bool
     */
    public $reset_analysis = false;

    /**
     * @var string
     */
    public $make_up = 'no';

    /**
     * @var null | string
     */
    protected $_target_analysis;

    /**
     * @var null|bool
     */
    protected $_hasTargetAnalysis;

    /**
     * @var array
     */
    protected $appends = [
        'target_analysis',
        'investment',
        'cloud_support_costs',
    ];

    /**
     * @var array
     */
    protected $hidden = ['old_target_analysis'];

    /**
     * @var array
     */
    protected $_softwareColumns = ['os_id', 'hypervisor_id', 'middleware_id', 'database_id'];

    /**
     * @var float
     */
    private $investment;

    /**
     * @var array
     */
    private $softwareByNames;


    //region Relationships

    public function project() {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function environmentType() {
        return $this->belongsTo(EnvironmentType::class,'environment_type');
    }

    /**
     * @return HasMany
     */
    public function serverConfigurations(): HasMany
    {
        return $this->hasMany(ServerConfiguration::class);
    }

    public function provider() {
        return $this->belongsTo(Provider::class,'provider_id');
    }

    public function currency() {
        return $this->belongsTo(Currency::class,'currency_id');
    }

    /**
     * The environment's `PaymentOption`
     */
    public function paymentOption() {
        return $this->belongsTo(PaymentOption::class, 'payment_option_id');
    }

    public function region() {
        return $this->belongsTo(Region::class,'region_id');
    }

    public function softwareCosts() {
        return $this->hasMany(SoftwareCost::class);
    }

    public function interconnects() {
        return $this->hasMany(InterconnectChassis::class);
    }

    public function analyses() {
        return $this->hasMany(Analysis::class);
    }

    //endregion

    /**
     * @return bool
     */
    public function isExisting()
    {
        return $this->is_existing ? true : false;
    }

    /**
     * @return bool
     */
    public function isOptimal()
    {
        return (bool)$this->is_optimal;
    }

    /**
     * @return bool
     */
    public function isPhysical()
    {
        return $this->isExisting() && $this->getExistingEnvironmentType() == self::EXISTING_ENVIRONMENT_TYPE_PHYSICAL;
    }

    /**
     * @return bool
     */
    public function isPhysicalVm()
    {
        return $this->isExisting() && $this->getExistingEnvironmentType() == self::EXISTING_ENVIRONMENT_TYPE_PHYSICAL_VM;
    }

    /**
     * @return bool
     */
    public function isVm()
    {
        return $this->isExisting() && $this->getExistingEnvironmentType() == self::EXISTING_ENVIRONMENT_TYPE_VM;
    }

    /**
     * @return bool
     */
    public function isIncomplete()
    {
        return (bool)$this->is_incomplete;
    }

    /**
     * @return string|null
     */
    public function getExistingEnvironmentType()
    {
        return $this->existing_environment_type;
    }

    /**
     * @return EnvironmentType|null
     */
    public function getEnvironmentType()
    {
        $this->setDefaultExistingEnvironmentType();
        return $this->environmentType;
    }

    /**
     * @return $this
     */
    public function setDefaultExistingEnvironmentType()
    {
        if ($this->isExisting() && !$this->environmentType) {
            $this->environmentType()->associate(EnvironmentType::findByType(EnvironmentType::ID_COMPUTE));
            $this->save();
        }

        return $this;
    }

    /**
     * @param $type
     * @return $this
     */
    public function setEnvironmentType($type)
    {
        $this->environmentType()->associate($type);

        return $this;
    }

    /**
     * @return bool
     */
    public function isCloud()
    {
        return $this->getEnvironmentType()->name == self::ENVIRONMENT_TYPE_CLOUD;
    }

    /**
     * @return bool
     */
    public function isConverged()
    {
        return $this->getEnvironmentType()->name == self::ENVIRONMENT_TYPE_CONVERGED;
    }

    /**
     * @return bool
     */
    public function isCompute()
    {
        return $this->getEnvironmentType()->name == self::ENVIRONMENT_TYPE_COMPUTE;
    }

    /**
     * @return bool
     */
    public function isComputeStorage()
    {
        return $this->getEnvironmentType()->name == self::ENVIRONMENT_TYPE_COMPUTE_STORAGE;
    }

    /**
     * @return Provider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @return bool
     */
    public function isAws()
    {
        return $this->isCloud() && $this->getProvider()->isAws();
    }

    /**
     * @return bool
     */
    public function isAzure()
    {
        return $this->isCloud() && $this->getProvider()->isAzure();
    }

    /**
     * @return bool
     */
    public function isGoogle()
    {
        return $this->isCloud() && $this->getProvider()->isGoogle();
    }

    /**
     * @return bool
     */
    public function isIBMPVS()
    {
        return $this->isCloud() && $this->getProvider()->isIBMPVS();
    }

    /**
     * @return bool
     */
    public function isAwsGeneralPurpose()
    {
        return $this->isAws() && $this->cloud_storage_type == self::CLOUD_STORAGE_TYPE_AWS_GENERAL_PURPOSE;
    }

    /**
     * @return bool
     */
    public function isAwsProvisionedIops()
    {
        return $this->isAws() && in_array($this->cloud_storage_type, self::CLOUD_STORAGE_TYPE_AWS_PROVISIONED);
    }

    /**
     * @return $this
     */
    public function setCloudInstanceType()
    {
        /** @var ServerConfiguration $serverConfiguration */
        foreach($this->serverConfigurations as $serverConfiguration) {
            if ($serverConfiguration->database_li_name) {
                $this->instanceType = self::INSTANCE_TYPE_RDS;
                return $this;
            }
        }

        $this->instanceType = self::INSTANCE_TYPE_EC2;

        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function setCloudSummary($data)
    {
        if (is_array($data)) {
            $data = (object)$data;
        }

        $this->_cloudSummary = $data;

        return $this;
    }

    /**
     * @return null|\stdClass
     */
    public function getCloudSummary()
    {
        return $this->_cloudSummary;
    }

    /**
     * @return float
     */
    public function getCloudSummaryTotal()
    {
        return $this->_cloudSummary->purchase_support ?? 0.00;
    }

    /**
     * @return string
     */
    public function getCloudSummaryType()
    {
        return $this->_cloudSummary->type ?? '';
    }

    /**
     * @param bool $treatAsExisting
     * @return Environment
     */
    public function setTreatAsExisting(bool $treatAsExisting)
    {
        $this->_treatAsExisting = $treatAsExisting;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTreatAsExisting()
    {
        return $this->_treatAsExisting;
    }

    /**
     * Return the number of nodes if this is an existing environment. If this is NewVsNew,
     * converged existing ONLY have the actual appliance nodes counted (not the individual server)
     * @return int
     */
    public function getExistingCount()
    {
        return $this->serverConfigurations->reduce(function($carry, ServerConfiguration $serverConfiguration){
            if ($this->isConverged()) {
                return $carry + ($serverConfiguration->isConverged() ? $serverConfiguration->qty : 0);
            }

            return $carry + $serverConfiguration->qty;
        }, 0);
    }

    /**
     * @param ServerConfiguration $serverConfiguration
     * @return $this
     */
    public function addAdditionalServerConfiguration(ServerConfiguration $serverConfiguration)
    {
        $this->initAdditionalServerConfigurations();
        $this->_additionalServerConfigurations->push($serverConfiguration);

        return $this;
    }

    public function getAdditionalServerConfigurations()
    {
        return $this->_additionalServerConfigurations;
    }

    /**
     * @return $this
     */
    public function initAdditionalServerConfigurations()
    {
        if (is_null($this->_additionalServerConfigurations)) {
            $this->_additionalServerConfigurations = new Collection();
        }

        return $this;
    }

    /**
     * @param $softwareId
     * @return false|SoftwareCost
     */
    public function getSoftwareCostBySoftware($softwareId)
    {
        $softwareId = is_object($softwareId) ? $softwareId->id : $softwareId;

        return $this->softwareCosts->where('software_type_id', $softwareId)->first();
    }

    /**
     * @return int
     */
    public function getRamUtilization()
    {
        return $this->ram_utilization ?: self::DEFAULT_RAM_UTILIZATION;
    }

    /**
     * @return int
     */
    public function getCpuUtilization()
    {
        return $this->cpu_utilization ?: self::DEFAULT_CPU_UTILIZATION;
    }

    /**
     * @return float|int
     */
    public function getCagrMultiplier()
    {
        // Cagr
        $cagrMult = 1;
        if($this->project->cagr) {
            for($i = 0; $i < $this->project->support_years; ++$i) {
                $cagrMult *= 1 + ($this->project->cagr / 100.0);
            }
        }

        return $cagrMult;
    }

    /**
     * @param $physicalServerId
     * @return ServerConfiguration|false
     */
    public function getVmParent($physicalServerId)
    {
        $items = $this->serverConfigurations->filter(function(ServerConfiguration $serverConfiguration) use ($physicalServerId) {
            return $serverConfiguration->isPhysical() && $serverConfiguration->id = $physicalServerId;
        })->all();

        return count($items) ? $items[0] : false;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        global $targetAnalysisOnlyBool;
        $orig = $targetAnalysisOnlyBool;
        $targetAnalysisOnlyBool = true;
        $ret = parent::toArray(); // TODO: Change the autogenerated stub
        $targetAnalysisOnlyBool = $orig;
        return $ret;
    }

    /**
     * @return mixed
     */
    public function getTargetAnalysis()
    {
        /** @var Collection $sortedChunks */
        $sortedChunks = $this->analyses()->orderBy('sequence', 'ASC')->get();
        if (!$sortedChunks) {
            return '';
        }

        return $sortedChunks->reduce(function($carry, Analysis $item){
            return $carry . ($item->target_analysis ?: '');
        }, '');
    }

    /**
     * @return mixed
     */
    public function getTargetAnalysisAttribute()
    {
        global $targetAnalysisOnlyBool;
        if ($targetAnalysisOnlyBool) {
            return $this->hasTargetAnalyses() ? true : null;
        }

        if (is_null($this->_target_analysis)) {
            $this->_target_analysis = $this->getTargetAnalysis();
        }

        return $this->_target_analysis;
    }

    /**
     * @return mixed
     */
    public function hasTargetAnalyses()
    {
        return $this->analyses()->whereRaw('LENGTH(IFNULL(target_analysis, \'\')) > ?', [0])->count() > 0;
    }

    /**
     * Save a target analysis and break it up into multiple files
     * @param $value
     * @return $this
     */
    public function saveTargetAnalysis($value)
    {
        $this->_target_analysis = $value;
        $this->analyses()->delete();
        $saveString = $value;
        $sortOrder = 0;
        try {
            do {
                $myString = substr($saveString, 0, self::ANALYSIS_CHUNK_SIZE);
                $saveString = substr($saveString, self::ANALYSIS_CHUNK_SIZE);
                $this->analyses()->create([
                    'target_analysis' => $myString,
                    'sequence' => $sortOrder++
                ]);

            } while (strlen($saveString) > 0);
        } catch (\Throwable $e) {
        }
        $this->_hasTargetAnalysis = null;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setTargetAnalysisAttribute($value)
    {
        $this->_target_analysis = $value;
        $this->_hasTargetAnalysis = $value ? true : false;
        return $this;
    }

    /**
     * @param $softwareByNames
     * @return $this
     */
    public function setSoftwareByNames($softwareByNames) {
      $this->softwareByNames = $softwareByNames;
      return $this;
    }

    /**
     * @return array
     */
    public function getDistinctSoftware()
    {
        if (!$this->id) {
            return collect([]);
        }
        $allSoftwareIds = collect($this->_softwareColumns)->reduce(function(Collection $softwareIds, $key){
            return $softwareIds->merge(\DB::table('server_configurations')
                ->select($key)
                ->distinct()
                ->whereNotNull($key)
                ->where('environment_id', $this->id)
                ->pluck($key));
        }, collect([]));

        return collect([
            'ids' => $allSoftwareIds,
            'names' => Software::findMany($allSoftwareIds)->pluck('name')->unique()
        ]);
    }

    /**
     * @return $this
     */
    public function resetCopyVmSoftware()
    {
        $this->copy_vm_os = 1;
        $this->copy_vm_middleware = 1;
        $this->copy_vm_hypervisor = 1;
        $this->copy_vm_database = 1;

        return $this;
    }

    /**
     * @param string $type
     * @return int
     */
    public function copyVmSoftware(string $type)
    {
        $field = 'copy_vm_' . $type;
        return intval($this->{$field});
    }

    /**
     * @return mixed
     */
    public function getInvestmentAttribute()
    {
      if (!isset($this->investment) && isset($this->softwareByNames)) {
        $this->investment = $this->purchase_price ? $this->purchase_price : 0;
        $this->investment += $this->total_hardware_maintenance ? $this->total_hardware_maintenance : 0;
        $this->investment += $this->total_hardware_usage ? $this->total_hardware_usage : 0;
        $this->investment += $this->system_software_purchase_price ? $this->system_software_purchase_price : 0;
        $this->investment += $this->total_system_software_maintenance ? $this->total_system_software_maintenance : 0;
        $this->investment += $this->storage_purchase_price ? $this->storage_purchase_price : 0;
        $this->investment += $this->total_storage_maintenance && $this->storage_purchase_price ? $this->total_storage_maintenance : 0;
        $this->investment += $this->migration_services ? $this->migration_services : 0;
        if ($this->investment > 0 && !$this->is_existing) {
          $software = $this->getDistinctSoftware();
          $softwareIds = $software ? $software['ids'] : NULL;
          $softwareNames = $software ? $software['names'] : NULL;
          $existingNames = array();
          if ($softwareIds && ($existing = $this->project->getExistingEnvironment()) && ($existingSoftware = $existing->getDistinctSoftware())) {
            $existingNames = $existingSoftware['names'];
          }
          if ($softwareIds) {
            foreach($softwareNames as $i => $softwareName) {
              $inExisting = FALSE;
              foreach($existingNames as $existingName) {
                if ($existingName == $softwareName) $inExisting = TRUE;
              }
              if (!$inExisting && ($cost = $this->getSoftwareCostBySoftware($softwareIds[$i])) && ($cost->software->license_cost > 0 || $cost->software->support_cost > 0)) {
                foreach($this->softwareByNames as $softwareByName) {
                  if ($softwareByName->name == $softwareName) {
                    foreach($softwareByName->envs as $env) {
                      if ($env->env == $this->name) {
                        $this->investment += $env->licenseCost ? $env->licenseCost : 0;
                        $this->investment += $env->supportCost ? $env->supportCost : 0;
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
      return $this->investment;
    }

    /**
     * @return string
     */
    public function getCloudSupportCostsAttribute()
    {
        return $this->attributes['cloud_support_costs'] ?? self::CLOUD_SUPPORT_COSTS_DEFAULT;
    }

    /**
     * @return int|mixed
     */
    public function getCloudSupportCostPerYear()
    {
        if ($this->cloud_support_costs === self::CLOUD_SUPPORT_COSTS_DEFAULT) {
            return isset($this->provider) ? $this->provider->hardware_maintenance_per_year : 0;
        }

        return (int) $this->custom_cloud_support_cost;
    }

    /**
     * @return bool
     */
    public function isShowSupportCost($type = self::CLOUD_SUPPORT_COSTS_DEFAULT): bool
    {
        return $this->cloud_support_costs === $type;
    }
}
