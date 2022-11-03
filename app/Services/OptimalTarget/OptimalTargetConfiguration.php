<?php


namespace App\Services\OptimalTarget;

use App\Models\Software\Software;

/**
 * Minimum configuration for an OptimalTarget environment. ServerConfiguration can be computed from these
 * parameters given an existing Environment
 *
 * Class OptimalTargetEnvironment
 * @package App\Services\OptimalTarget
 * @property string manufacturer
 * @property string $environment_name
 * @property string $environment_detail
 * @property string $workload_type
 * @property string $location
 * @property float cpu_utilization
 * @property float ram_utilization
 * @property Software os
 * @property Software database
 * @property Software middleware
 * @property Software hypervisor
 * @property float cagrMult
 */
class OptimalTargetConfiguration
{

}
