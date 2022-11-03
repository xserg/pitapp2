<?php


namespace App\Services;


use Carbon\Carbon;
use App\Models\Project\Company;
use App\Models\Project\Project;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\ConsolidationException;
use App\Services\Consolidation\Report\HasDataMapping;

class ProjectLicensingService
{
    use HasDataMapping;

    private $project;

    private $checksum = null;

    private $user;

    private $user_company;

    private $license_exceed_error = 'Running this analysis will exceed your organization\'s licensing agreement. Please contact us to extend your licensing terms.';

    const UNSET_FIELDS_FROM_ALL_ENV = [
        'updated_at',
        'created_at',
        'name',
        'provider',
        'is_dirty',
        'environment_name',
        'cloudServerDescription',
        'cloudServerDescription1Year',
        'cloudServerDescription3Year',
        'cloudServerDescriptionOnDemand',
        'env',
        'workload_type',
        'location',
        'environment_detail',
        'fte_salary',
        'max_utilization',
        'discount_rate',
        'cloud_bandwidth',
        'variance',
        'migration_services',
        'fte_qty',
        'cpu_utilization',
        'ram_utilization',
        'network_overhead',
        'remaining_deprecation',
        'cost_per_kwh',
        'support_years',
        'totals',
        'compute_power_cost',
        'power_cost',
        'power_cost_formula',
        'power_cost_per_year',
        'storage_power_cost_formula',
        'total_cost',
        'total_hardware_maintenance',
        'total_hardware_warranty_per_year',
        'storage_purchase_price',
        'purchase_price',
        'network_costs',
        'onDemandPurchase',
        'max_network',
        'network_costs',
        'storage_power_cost',
        'total_storage_maintenance',
        'total_fte_cost',
        'total_hardware_maintenance_per_year',
        'total_hardware_usage',
        'total_hardware_usage_per_year',
        'total_hardware_warranty_savings',
        'total_maintenance',
        'total_power',
        'total_storage',
        'total_storage_maintenance',
        'onDemandTotal',
        'totalCostOnDemand',
        'cheapestEnv',
        'onDemandHourly',
        'onDemandPerMonth',
        'onDemandPerYear',
        'onDemandSupportTotal',
        'onDemandTotal',
        'investment',
        'computedRamTotal',
        'computedRpmTotal',
        'ramTotal',
        'rpmTotal',
        'baseRam',
        'baseRpm',
        'computedRpm',
        'computedRam',
        'project',
        'analysis',
        'total_qty',
        'compute_power_cost_per_year',
        'metered_cost',
        'storage_power_cost_per_year',
        'total_system_software_maintenance',
        'total_system_software_maintenance_per_year',
        'roi',
    ];

    const UNSET_FIELDS_FROM_TARGET_ENV = [
        'os',
        'os_id',
        'os_license',
        'os_support_per_year',
        'software_costs',
        'database_support_per_year',
        'database_license',
        'database',
        'database_id',
        'database_li_ec2',
        'database_li_id',
        'database_li_name',
        'database_mod_id',
        'hypervisor',
        'hypervisor_id',
        'hypervisor_mod_id',
        'hypervisor_support_per_year',
        'hypervisor_license',
        'middleware',
        'middleware_id',
        'middleware_mod_id',
        'middleware_support_per_year',
    ];

    // Project fields to include to the checksum
    const INCLUDE_PROJECT_DATA = [
        'analysis_type' => ['analysis_type'],
        'environments' => ['environments'],
    ];

    /**
     * ProjectStatisticsService constructor.
     * @param Project $project
     */
    public function __construct(Project $project)
    {
        $this->project = $project;

        $this->user = Auth::user()->user;
        $this->user_company = $this->user->defaultCompany;
    }

    /**
     * @return ProjectLicensingService
     *
     * Calculates project environments checksum for the future comparison
     */
    public function updateAnalysisChecksum(): ProjectLicensingService
    {
        $data = $this->aggregateDataForChecksum();

        $this->checksum = (string) crc32(json_encode($data));

        $project_model = Project::find($this->project->id);

        if(!empty($project_model)) {
            $project_model->update(['last_analysis_checksum' => $this->checksum]);
        }

        return $this;
    }

    /**
     * @return ProjectLicensingService
     */
    public function resetCounters(): ProjectLicensingService
    {
        $interval = null;

        if (!empty($this->user_company)) {
            $interval = $this->user_company->licensed_analyses_interval;
        }

        $is_interval_pass = $this->isIntervalPass($interval);

        if ($is_interval_pass && !empty($this->user_company)) {
            $this->user_company->users->each(function($user) {
                $user->update([
                    'analysis_counter' => 0,
                    'analysis_target_counter' => 0,
                    'analysis_counter_last_reset' => new Carbon()
                ]);
            });

            $this->project->last_analysis_checksum = null;
            $this->user_company = $this->user_company->fresh();
            $this->user = $this->user->fresh();
        }

        return $this;
    }

    /**
     * @return ProjectLicensingService
     * @throws \Exception
     *
     * Updates licensing counters of usage for the current user
     */
    public function updateUserLicensingCounters(): ProjectLicensingService
    {
        if ($this->isUniqueAnalysis()) {
            $this->user->analysis_counter += 1;

            $this->user->analysis_target_counter += $this->getTargetsCount();

            if (empty($this->user->analysis_counter_last_reset)) {
                $this->user->analysis_counter_last_reset = new Carbon();
            }

            $this->user_company = $this->user_company->fresh();
            $this->user->save();
        }

        return $this;
    }

    /**
     * @return ProjectLicensingService
     * @throws ConsolidationException
     */
    public function licensingCheck(): ProjectLicensingService
    {
        if (!empty($this->user_company)) {
            $licensed_analyses = intval($this->user_company->licensed_analyses);
            $licensed_analyses_targets = intval($this->user_company->licensed_analyses_targets);

            if (
                $licensed_analyses > 0 && $this->user_company->totalAnalysisUsage > $licensed_analyses
                || $licensed_analyses_targets > 0 && $this->user_company->totalAnalysisTargetsUsage > $licensed_analyses_targets
            ) {
                throw new ConsolidationException($this->license_exceed_error);
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isUniqueAnalysis(): bool
    {
        return $this->checksum !== $this->project->last_analysis_checksum;
    }

    /**
     * @param $interval
     * @return bool
     */
    public function isIntervalPass($interval): bool
    {
        // Interval => Carbon method
        $intervals = [
            Company::ANALYSES_INTERVAL_ONE_TIME => null,
            Company::ANALYSES_INTERVAL_WEEK => 'isCurrentWeek',
            Company::ANALYSES_INTERVAL_MONTH => 'isCurrentMonth',
            Company::ANALYSES_INTERVAL_YEAR => 'isCurrentYear',
        ];

        try {
            $last_reset_date = new Carbon($this->user->analysis_counter_last_reset);

            $carbon_method = key_exists($interval, $intervals) ? $intervals[$interval] : null;

            if (empty($carbon_method)) {
                return false;
            }

            return !$last_reset_date->{$carbon_method}();
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * @return array
     *
     * Retrieves project data witch needed for the checksum
     */
    protected function aggregateDataForChecksum(): array
    {
        $data = $this->getDataByPathAsMap($this->project, self::INCLUDE_PROJECT_DATA, '');

        $data['environments'] = json_decode($data['environments'], true);

        $data = json_decode(json_encode($data), true);

        foreach ($data['environments'] as &$environment) {
            if ($environment['is_existing']) {
                $this->unsetFields($environment, $this->getFieldsToUnsetFromExisting());
            } else {
                $this->unsetFields($environment, $this->getFieldsToUnsetFromTarget());
            }
        }

        return $data;
    }

    /**
     * @return int
     */
    private function getTargetsCount(): int
    {
        $targets = $this->project->getTargetEnvironments();

        if (!empty($targets)) {
            return count($targets);
        }

        return 1;
    }

    /**
     * @return array
     */
    private function getFieldsToUnsetFromExisting(): array
    {
        return self::UNSET_FIELDS_FROM_ALL_ENV;
    }

    /**
     * @return array
     */
    private function getFieldsToUnsetFromTarget(): array
    {
        return array_merge(
            self::UNSET_FIELDS_FROM_ALL_ENV,
            self::UNSET_FIELDS_FROM_TARGET_ENV
        );
    }

    /**
     * @param array $array
     * @param array $unwanted_keys
     */
    private function unsetFields(array &$array, array $unwanted_keys) {
        foreach ($unwanted_keys as $key) {
            unset($array[$key]);
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->unsetFields($value, $unwanted_keys);
            }
        }
    }
}