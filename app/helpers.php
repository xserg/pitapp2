<?php

use App\Services\Deployment;
use \Illuminate\Support\Str;
use \Illuminate\Support\HtmlString;
if (! function_exists('mix')) {
    /**
     * Get the path to a versioned Mix file.
     *
     * @param  string  $path
     * @param  string  $manifestDirectory
     * @return \Illuminate\Support\HtmlString
     *
     * @throws \Exception
     */
    function mix($path, $manifestDirectory = '')
    {
        static $manifests = [];

        if (! Str::startsWith($path, '/')) {
            $path = "/{$path}";
        }

        if ($manifestDirectory && ! Str::startsWith($manifestDirectory, '/')) {
            $manifestDirectory = "/{$manifestDirectory}";
        }

        if (file_exists(public_path($manifestDirectory.'/hot'))) {
            return new HtmlString("//localhost:8080{$path}");
        }

        $manifestPath = public_path($manifestDirectory.'/mix-manifest.json');

        if (! isset($manifests[$manifestPath])) {
            if (! file_exists($manifestPath)) {
                throw new Exception('The Mix manifest does not exist.');
            }

            $manifests[$manifestPath] = json_decode(file_get_contents($manifestPath), true);
        }

        $manifest = $manifests[$manifestPath];

        if (\App::environment('local')) {
            foreach($manifest as $key => &$value) {
                $value .= '?key=' . md5(microtime(true));
            }
        }

        if (! isset($manifest[$path])) {
            if (! app('config')->get('app.debug')) {
                return $path;
            }
        }

        return new HtmlString($manifestDirectory.$manifest[$path]);
    }
}

/**
 * @param $number
 * @param int $optionalDecimalPlaces - NOT USED only made so it can be swapped for number_format
 * @return string
 */
function mixed_number($number, $optionalDecimalPlaces = 0)
{
    $number = floatval($number);
    $decimals = round($number, 2);
    $noDecimals = round($number, 0);

    if ($decimals === $noDecimals) {
        return (string)$noDecimals;
    }

    $oneDecimal = round($number, 1);


    if ($decimals === $oneDecimal) {
        $whole = floor($oneDecimal);      // 1
        $fraction = round($oneDecimal - $whole, 1); // .25
        return number_format((string)$whole) . '.' . str_replace("0.", "", $fraction);
    }

    $whole = floor($decimals);      // 1
    $fraction = round($decimals - $whole, 2); // .25

    return number_format((string)$decimals) . '.' . str_replace("0.", "", $fraction);
}

/**
 * @param $number
 * @param $decimalPlaces
 * @return string
 */
function rformat($number, $decimalPlaces = 0)
{
    return number_format(round($number, $decimalPlaces));
}

/**
 * @param array|\stdClass|\App\Models\Hardware\ServerConfiguration $mixed
 * @return \App\Models\Hardware\ServerConfiguration|array|bool
 */
function server_config($mixed)
{
    if (!is_object($mixed) && !is_array($mixed)) {
        return false;
    }
    if (is_object($mixed) && $mixed instanceof \App\Models\Hardware\ServerConfiguration) {
        return $mixed;
    }

    $mixed = is_object($mixed) ? (array)$mixed : $mixed;

    $serverConfig = new \App\Models\Hardware\ServerConfiguration();
    foreach($mixed as $key => $value) {
        $serverConfig->{$key} = $value;
    }

    return $serverConfig;
}