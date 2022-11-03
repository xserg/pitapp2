<?php
/**
 *
 */

namespace App\Services\Analysis\Report;


use App\Models\Project\AnalysisResult;
use App\Models\Project\Project;
use App\Services\Analysis\Pricing\Project\SavingsCalculatorAccessTrait;
use App\Services\Analysis\PricingAccessTrait;
use App\Services\Currency\CurrencyConverter;
use App\Services\Filesystems;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

abstract class AbstractReport
{
    use SavingsCalculatorAccessTrait;
    use PricingAccessTrait;

    abstract public function generate(AnalysisResult $analysisResult);

    /**
     * @param $software
     * @param $envId
     * @param $applicable
     * @return int|string
     */
    public function supportForEnvironment($software, $envId, $applicable)
    {
        $retval = 0;
        $found = false;
        foreach($software->envs as $env) {
            if($env->id == $envId) {
                $found = true;
                $retval += $env->supportCost;
            }
        }
        if ($found) {
            return $applicable ? CurrencyConverter::convertAndFormat($retval) : $retval;
        }
        return $applicable ? 'N/A' : 0;
    }

    /**
     * @param $software
     * @param $envId
     * @param $applicable
     * @return int|string
     */
    public function softwareLicenseForEnvironment($software, $envId, $applicable)
    {
        foreach($software->envs as $env) {
            if($env->id == $envId && !$env->ignoreLicense) {
                $retval = $env->licenseCost;
                return $applicable ? CurrencyConverter::convertAndFormat($retval, null, 0) : $retval;
            }
        }
        return $applicable ? 'N/A' : 0;
    }

    /**
     * @param $environments
     * @param $provider
     * @return bool
     */
    public function hasCloudProvider($environments, $provider)
    {
        foreach ($environments as $e) {
            if ($e->environmentType->name == "Cloud" && ($e->provider->name == $provider || str_replace(' ', '', $e->provider->name) == $provider))
                return true;
        }
        return false;
    }

    /**
     * @param $environments
     * @param array $types
     * @return bool
     */
    public function hasAWSStorageType($environments, $types)
    {
        foreach ($environments as $environment) {
            if ($environment->provider && $environment->provider['name'] === "AWS" && in_array($environment->cloud_storage_type, $types)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $environments
     * @param $provider
     * @return bool
     */
    public function doesntHaveAWSStorageType($environments, $provider)
    {
        foreach ($environments as $e) {
            if ($e->environmentType->name == "Cloud" && $e->cloud_storage_type == $provider)
                return false;
        }
        return true;
    }

    /**
     * @param $environments
     * @return bool
     */
    public function hasCloud($environments)
    {
        foreach ($environments as $e) {
            if ($e->environmentType->name == "Cloud")
                return true;
        }
        return false;
    }

    /**
     * @param $environment
     * @param $provider
     * @return bool
     */
    public function envHasCloudProvider($environment, $provider)
    {
        return $environment->environmentType->name == "Cloud" && ($environment->provider->name == $provider || str_replace(' ', '', $environment->provider->name) == $provider);
    }

    /**
     * @param $environments
     * @return bool
     */
    public function hasConverged($environments)
    {
        foreach ($environments as $e) {
            if ($e->environmentType->name == "Converged")
                return true;
        }
        return false;
    }

    /**
     * @param $environments
     * @return bool
     */
    public function hasRDS($environments)
    {
        foreach ($environments as $e) {
            if ($e->instanceType == "RDS")
                return true;
        }
        return false;
    }

    /**
     * @param $environments
     * @return bool
     */
    public function hasStorage($environments)
    {
        foreach ($environments as $e) {
            if ($e->total_storage > 0)
                return true;
        }
        return false;
    }

    /**
     * @param $environments
     * @param $provider
     * @return bool
     */
    public function hasBandwidth($environments, $provider)
    {
        foreach ($environments as $e) {
            if ($e->cloud_bandwidth > 0 && $e->provider && $e->provider->name == $provider)
                return true;
        }
        return false;
    }

    /**
     * @param $type
     * @return string
     */
    public function mapStorageType($type)
    {
        switch ($type) {
            case 1:
                return "HDD";
            case 2:
                return "SSD";
            case 3:
                return "SED";
            case 4:
                return "HDD/SSD";
        }
    }

    /**
     * @param $cost
     * @param $offset
     * @return string
     */
    public function printTieredSupport($cost, $offset)
    {
        return Arr::exists($cost, $offset) ? " = " . CurrencyConverter::convertAndFormat($cost[$offset]) . '/mo' : '';
    }

    /**
     * @param $name
     * @return string
     */
    protected function mapLIName($name)
    {
        switch ($name) {
            case "Oracle Standard One":
                return "Oracle Standard Edition 1 (LI-RDS)";
            case "Oracle Standard Two":
                return "Oracle Standard Edition 2 (LI-RDS)";
            case "MySQL":
                return "MySQL (LI-RDS)";
            case "Amazon Aurora":
                return "Amazon Aurora (LI-RDS)";
            case "Aurora MySQL":
                return "Aurora MySQL (LI-RDS)";
            case "Aurora PostgreSQL":
                return "Aurora PostgreSQL (LI-RDS)";
            case "PostGreSQL":
                return "PostGreSQL (LI-RDS)";
            case "MariaDB":
                return "MariaDB (LI-RDS)";
            case "SQL Server Standard":
                return "Microsoft SQL Server Standard (LI-RDS)";
            case "SQL Server Enterprise":
                return "Microsoft SQL Server Enterprise (LI-RDS)";
            default:
                return "Linux";
        }
    }

    /***
     * @param $project
     * @return string
     */
    public function awsString($project)
    {
        $string = "AWS - EC2";
        if ($this->hasRDS($project->environments)) {
            $string .= ", RDS";
        }
        if ($this->hasStorage($project->environments)) {
            $string .= ", EBS Storage";
        }
        $string .= " and Business Level Support Costs (" . $project->support_years . "-year)";
        $string .= " - " . $this->getCloudEnvironmentPaymentOption($project->environments, 'AWS');
        return $string;
    }

    /**
     * @param $environment
     * @param $type
     * @return bool|string
     */
    public function getCloudEnvironmentPaymentOption($environments, $type)
    {
        foreach ($environments as $environment) {
            if ($environment->provider &&  $environment->provider->name === $type && $environment->payment_option && isset($environment->payment_option['name'])) {
                return str_replace(' With Azure Hybrid Benefit', '', $environment->payment_option['name']);
            }
        }
        return false;
    }

    /**
     * @param $server
     * @param $option
     * @param bool
     */
    public function isPaymentOption($server, $option)
    {
        return strpos($server->payment_option['name'], $option) !== false ? true : false;
    }

    protected function copyLocal($path) {
        $dir = str_replace('images/', '', File::dirname($path));
        $tempLogoPath = storage_path('app/public/' . $dir);
        if (config('app.debug')) {
            logger("Creating temp path $tempLogoPath");
        }
        if (!File::exists($tempLogoPath)) {
            File::ensureDirectoryExists($tempLogoPath);
        }
        $fileName = File::basename($path);
        $localPath = "$tempLogoPath/$fileName";
        if (File::exists($localPath)) {
            File::delete($localPath);
        }

        if (config('app.debug')) {
            logger("Attempting to copy file to $localPath from images filesystem");
        }

        try {
            $fileContents = Filesystems::imagesFilesystem()->get($path);
            File::put($localPath, $fileContents);
        } catch (FileNotFoundException $e) {
            logger()->error("image file not found $path");
            logger()->error($e->getMessage());
        }
        return $localPath;
    }

    /**
     * @param Project $project
     * @return array
     */
    protected function copyToLocal(Project $project): string
    {
        return $this->copyLocal($project->logo);

    }
}
