<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Project;


class AbstractSavingsCalculator
{
    /**
     * @param $SorF
     * @param $environmentId
     * @return int
     */
    public function supportCostNoFeatures($SorF, $environmentId)
    {
        $total = 0;
        foreach ($SorF->envs as $env) {
            if ($env->id == $environmentId) {
                $total += $env->supportCost;
            }
        }
        return $total;
    }

    /**
     * @param $SorF
     * @param $environmentId
     * @return int
     */
    public function licenseCostNoFeatures($SorF, $environmentId)
    {
        $total = 0;
        foreach ($SorF->envs as $env) {
            if ($env->id == $environmentId && !$env->ignoreLicense) {
                $total += $env->licenseCost;
            }
        }
        return $total;
    }

    /**
     * @param $software
     * @param $environmentId
     * @return int
     */
    public function softwareSupportForEnvironment($software, $environmentId)
    {
        $total = 0;
        foreach ($software->envs as $env) {
            if ($env->id == $environmentId) {
                $total += $env->supportCost;
            }
        }
        foreach ($software->features as $feature) {
            foreach ($feature->envs as $env) {
                if ($env->id == $environmentId) {
                    $total += $env->supportCost;
                }
            }
        }
        return $total;
    }

    /**
     * @param $software
     * @param $environmentId
     * @return int
     */
    public function licenseForEnvironment($software, $environmentId)
    {
        $total = 0;
        foreach ($software->envs as $env) {
            if ($env->id == $environmentId && !$env->ignoreLicense) {
                $total += $env->licenseCost;
            }
        }
        foreach ($software->features as $feature) {
            foreach ($feature->envs as $env) {
                if ($env->id == $environmentId && !$env->ignoreLicense) {
                    $total += $env->licenseCost;
                }
            }
        }
        return $total;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function savingsCategoryName($name)
    {
        $retName = str_replace(" ", "\n", $name);
        $retName = str_replace("aintenance", "ain", $retName);
        $retName = str_replace("anagement", "gmt", $retName);
        $retName = str_replace("pplication", "pp", $retName);
        $retName = str_replace("nterprise", "nt", $retName);
        $retName = str_replace("rocessor", "roc", $retName);
        $retName = str_replace("perations", "ps", $retName);
        $retName = str_replace("dvantage", "dvt", $retName);
        $retName = str_replace("iagnostic", "iag", $retName);
        $retName = str_replace("/", "/\n", $retName);
        return $retName;
    }
}