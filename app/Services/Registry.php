<?php
/**
 *
 */

namespace App\Services;


use App\Http\Controllers\Api\Project\AnalysisController;
use App\Models\Hardware\Manufacturer;
use App\Models\Project\Project;
use App\Models\Project\Provider;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

abstract class Registry
{
    /**
     * @var
     */
    static protected $_registry = [];

    /**
     * @param $key
     * @param $value
     */
    public static function register($key, $value)
    {
        self::$_registry[$key] = $value;
    }

    /**
     * @param $key
     */
    public static function unregister($key)
    {
        unset(self::$_registry[$key]);
    }

    /**
     * @param $key
     * @param null $default
     * @return null
     */
    public static function registry($key, $default = null)
    {
        return self::$_registry[$key] ?? $default;
    }
}