<?php


namespace App\Http\Controllers\Api\OptimalTarget;

use App\Models\Hardware\Processor;
use App\Models\Software\Software;

/**
 * Class OptimalTargetResponse
 * @package App\Http\Controllers\Api\OptimalTarget
 * @property string $environment_name
 * @property string $environment_detail
 * @property string $workload_type
 * @property string $location
 * @property Processor $processor
 * @property int $ram
 * @property Software os
 * @property Software database
 * @property Software middleware
 * @property Software hypervisor
 */
class OptimalTargetResult
{

}
