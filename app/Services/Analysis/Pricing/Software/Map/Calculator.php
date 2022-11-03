<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Software\Map;


use App\Models\Project\Environment;
use App\Models\Software\Feature;
use App\Models\Software\FeatureCost;
use App\Models\Software\Software;
use App\Models\Software\SoftwareCost;
use App\Services\Analysis\Pricing\Software\MapAccessTrait;
use App\Services\Currency\CurrencyConverter;

class Calculator
{
    use MapAccessTrait;

    /**
     * @param Environment $environment
     * @return $this
     * @throws \App\Exceptions\AnalysisException
     */
    public function calculateCosts(Environment $environment)
    {
        if (!$this->softwareMap()->getEnvironment($environment)) {
            return $this;
        }

        $softwares = &$this->softwareMap()->mappedSoftware;

        foreach ($this->softwareMap()->getEnvironment($environment) as $softwareId => $amounts) {
            foreach ($environment->softwareCosts as $sc) {

                if ($softwareId != $sc->software_type_id) {
                    continue;
                }

                $this->softwareMap()->setScope($softwareId, $environment);

                $software = (object)[
                    'name' => $sc->software->name,
                    'full_name' => $sc->software->full_name,
                    'supportFormula' => '',
                    'licenseFormula' => '',
                    'envs' => []
                ];

                $costPerMultiplier = 1;
                $licenseCost = $sc->software->license_cost;

                $costPerMap = $this->getCostPerSoftwareMap($sc, $amounts, 'cost_per');

                $calculatedLicenseFormula =  CurrencyConverter::convertAndFormat($licenseCost) . " * "
                    . $sc->license_cost_modifier . '% discount * '
                    . mixed_number($costPerMap['amountsValue'], 0) . " " . $costPerMap['unit'];
                $software->licenseFormula = "License Cost * discount * # of " . $costPerMap['unit'];
                $costPerMultiplier *= $costPerMap['multiplier'];

                if ($sc->software->cost_per === Software::COST_PER_DISK) {
                    $costPerMultiplier *= $this->softwareMap()->getData('servers');

                    $calculatedLicenseFormula .= ' * ' . $this->softwareMap()->getData('servers');
                    $calculatedLicenseFormula .= '  node(s)';

                    $software->licenseFormula .= ' *  node(s)';
                }

                if ($sc->software->softwareType->isDatabaseOrMiddleware() && $sc->software->multiplier != null) {

                    $software->licenseFormula .= " * core multiplier or PVU";
                    $calculatedLicenseFormula .= " * " . $sc->software->multiplier;
                    $costPerMultiplier *= $sc->software->multiplier;
                }

                $licenseTotal = round($sc->software->license_cost * ((100 - $sc->license_cost_modifier) / 100) * $costPerMultiplier);

                $calculatedLicenseFormula .= ' = ' . CurrencyConverter::convertAndFormat($licenseTotal);

                if ($sc->software->support_type == Software::SUPPORT_TYPE_PERCENT_LICENSE) {
                    $totalCost = $this->softwareMap()->getData('totalCost');
                    $calculatedFormula = CurrencyConverter::convertAndFormat($totalCost) . ' * ' . $sc->support_cost_modifier . '% discount';
                    $software->supportFormula = "Percentage of license net cost * discount";
                    $total = round($this->softwareMap()->getData('totalCost') * ((100 - $sc->support_cost_modifier) / 100));
                } else {
                    $costPerMultiplier = 1;
                    $licenseCost = $sc->software->support_cost;

                    $costPerMap = $this->getCostPerSoftwareMap($sc, $amounts, 'annual_cost_per');
                    $calculatedFormula = CurrencyConverter::convertAndFormat($licenseCost) . " * "
                        . $sc->support_cost_modifier . '% discount * '
                        . mixed_number($costPerMap['amountsValue'], 0) . " " . $costPerMap['unit'];
                    $software->supportFormula = "Support cost * discount * # of " . $costPerMap['unit'];
                    $costPerMultiplier *= $costPerMap['multiplier'];

                    if ($sc->software->cost_per === Software::COST_PER_DISK) {
                        $costPerMultiplier *= $this->softwareMap()->getData('servers');
                        $calculatedFormula .= ' * ' . $this->softwareMap()->getData('servers');
                        $calculatedFormula .= ' node(s)';
                    }

                    if ($sc->software->softwareType->isDatabaseOrMiddleware() && $sc->software->support_multiplier != null) {
                        $software->supportFormula .= " * core multiplier or PVU";
                        $calculatedFormula .= " * " . $sc->software->support_multiplier;
                        $costPerMultiplier *= $sc->software->support_multiplier;
                    }

                    $total = round($sc->software->support_cost * ((100 - $sc->support_cost_modifier) / 100)
                        * $costPerMultiplier);
                }

                $software->supportFormula .= " * # of years";
                $calculatedFormula .= " * " . $environment->project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($total * $environment->project->support_years);
                $software->name = $sc->software->name;
                $software->full_name = $sc->software->full_name;
                $software->envs = [];

                if (!isset($softwares[$softwareId])) {
                    $softwares[$softwareId] = $software;
                }

                if (!isset($softwares[$softwareId]->features)) {
                    $softwares[$softwareId]->features = [];
                }

                $ignore = $this->softwareMap()->getData('ignoreLicense') || $environment->isExisting();

                /** @var FeatureCost $fc */
                foreach ($sc->featureCosts as $fc) {
                    $feature = (object)[
                        'name' => $fc->feature->name,
                        'full_name' => $fc->feature->full_name,
                        'supportFormula' => '',
                        'licenseFormula' => '',
                        'envs' => []
                    ];

                    $calculatedFeature = (object)['env' => $environment->name,
                        'featureName' => $fc->feature->full_name,
                        'id' => $environment->id,
                        'ignoreLicense' => $ignore
                    ];

                    $this->mapFeatureLicenseCost($fc, $amounts, $feature, $calculatedFeature, $softwareId, $environment);
                    $this->mapFeatureSupportCost($fc, $amounts, $feature, $calculatedFeature, $softwareId, $environment);

                    $calculatedFeature->supportFormula .= " * " . $environment->project->support_years . ' years = '
                        . CurrencyConverter::convertAndFormat($calculatedFeature->supportCostPerYear * $environment->project->support_years);

                    $calculatedFeature->supportCost = $calculatedFeature->supportCostPerYear * $environment->project->support_years;

                    if (!isset($softwares[$softwareId]->features[$fc->feature->id])) {
                        $softwares[$softwareId]->features[$fc->feature->id] = $feature;
                    }

                    $softwares[$softwareId]->features[$fc->feature->id]->envs[] = $calculatedFeature;
                }

                $softwares[$softwareId]->envs[] = (object)[
                    'env' => $environment->name,
                    'id' => $environment->id,
                    'softwareName' => $software->full_name,
                    'supportFormula' => $calculatedFormula,
                    'supportCostPerYear' => $total,
                    'supportCost' => $total * $environment->project->support_years,
                    'licenseFormula' => $calculatedLicenseFormula,
                    'licenseCost' => $licenseTotal,
                    'ignoreLicense' => $ignore,
                    'isByol' => $this->softwareMap()->getData('isByol'),
                    'featureCosts' => $this->softwareMap()->getData('featureCosts')
                ];
                break;
            }
        }

        return $this;
    }

    /**
     * @param FeatureCost $fc
     * @param array $amounts
     * @param \stdClass $feature
     * @param \stdClass $calculatedFeature
     * @param $softwareId
     * @param Environment $environment
     * @return Calculator
     */
    public function mapFeatureLicenseCost($fc, $amounts, $feature, $calculatedFeature, $softwareId, Environment $environment)
    {
        $calculatedFeature->licenseCost = 0;

        $costPerMultiplier = 1;
        $formulaCost = $fc->feature->license_cost;

        $costPerMap = $this->getCostPerFeatureMap($fc, $amounts, 'cost_per');

        $calculatedFeature->licenseFormula = CurrencyConverter::convertAndFormat($formulaCost) ." * "
            . $fc->license_cost_discount . '% discount * '
            . number_format($costPerMap['amountsValue'],0) . " " . $costPerMap['unit'];
        $feature->licenseFormula = "License Cost * discount * # of " . $costPerMap['unit'];
        $costPerMultiplier *= $costPerMap['multiplier'];

        if($fc->softwareCost->software->softwareType->isDatabaseOrMiddleware() && $fc->feature->multiplier != null) {
            $costPerMultiplier *= $fc->feature->multiplier;
            $calculatedFeature->licenseFormula .= ' * '.$fc->feature->multiplier;
            $feature->licenseFormula .= ' *  core multiplier or PVU';
        }

        $calculatedFeature->licenseCost = round($fc->feature->license_cost * ((100 - $fc->license_cost_discount) / 100) * $costPerMultiplier);
        $calculatedFeature->licenseFormula .= ' = ' . CurrencyConverter::convertAndFormat($calculatedFeature->licenseCost);

        return $this;
    }

    /**
     * @param FeatureCost $fc
     * @param array $amounts
     * @param \stdClass $feature
     * @param \stdClass $calculatedFeature
     * @param $softwareId
     * @param Environment $environment
     * @return Calculator
     */
    public function mapFeatureSupportCost($fc, $amounts, &$feature, &$calculatedFeature, $softwareId, Environment $environment)
    {
        $calculatedFeature->supportCostPerYear = 0;

        if($fc->feature->support_type == Feature::SUPPORT_TYPE_PERCENT_LICENSE) {
            $formulaCost = $calculatedFeature->licenseCost;
            $calculatedFeature->supportFormula = CurrencyConverter::convertAndFormat($formulaCost) . ' * ' . $fc->support_cost_discount . '% discount';
            $feature->supportFormula = "Percentage of license net cost * discount";
            $calculatedFeature->supportCostPerYear = round($calculatedFeature->licenseCost * ((100 - $fc->support_cost_discount) / 100));
        } else {
            $costPerMultiplier = 1;
            $formulaCost = $fc->feature->support_cost;

            $costPerMap = $this->getCostPerFeatureMap($fc, $amounts, 'annual_cost_per');

            $calculatedFeature->supportFormula = CurrencyConverter::convertAndFormat($formulaCost) ." * "
                . $fc->support_cost_discount . '% discount * '
                . number_format($costPerMap['amountsValue'],0) . " " . $costPerMap['unit'];
            $feature->supportFormula = "Support Cost * discount * # of " . $costPerMap['unit'];
            $costPerMultiplier *= $costPerMap['multiplier'];

            if($fc->softwareCost->software->softwareType->isDatabaseOrMiddleware() && $fc->feature->support_multiplier != null) {
                $costPerMultiplier *= $fc->feature->support_multiplier;
                $calculatedFeature->supportFormula .= ' * '.$fc->feature->support_multiplier;
                $feature->supportFormula .= ' *  core multiplier or PVU';
            }
            $calculatedFeature->supportCostPerYear = round($fc->feature->support_cost * ((100 - $fc->support_cost_discount) / 100)
                * $costPerMultiplier);
        }
        $feature->supportFormula .= " * # of years";

        $feature->name = $fc->feature->name;
        $feature->full_name = $fc->feature->full_name;
        $feature->envs = [];

        return $this;
    }

    /**
     * @param SoftwareCost $sc
     * @param $amounts
     * @return array
     */
    public function getCostPerSoftwareMap(SoftwareCost $sc, $amounts, $type)
    {
        switch ($sc->software->{$type}) {
            case Software::COST_PER_NUP:
                $costPerMap = [
                    'multiplier' => $this->softwareMap()->getData('cores') * $sc->software->nup,
                    'amountsValue' => $amounts['cores'] * $sc->software->nup,
                    'unit' => 'NUPs'
                ];
                break;
            case Software::COST_PER_CORE:
                $costPerMap = [
                    'multiplier' => ceil(!empty($amounts['licensed_cores']) ? $amounts['licensed_cores'] : $amounts['cores']),
                    'amountsValue' => ceil(!empty($amounts['licensed_cores']) ? $amounts['licensed_cores'] : $amounts['cores']),
                    'unit' => $this->softwareMap()->getData('core_unit')
                ];
                break;
            case Software::COST_PER_PROCESSOR:
                $costPerMap = [
                    'multiplier' => $this->softwareMap()->getData('processors'),
                    'amountsValue' => $amounts['processors'],
                    'unit' => 'processors'
                ];
                break;
            case Software::COST_PER_DISK:
                $costPerMap = [
                    'multiplier' => $this->softwareMap()->getData('drive_qty'),
                    'amountsValue' => $amounts['drive_qty'],
                    'unit' => 'disk(s)'
                ];
                break;
            default:
                $costPerMap = [
                    'multiplier' => $this->softwareMap()->getData('servers'),
                    'amountsValue' => $amounts['servers'],
                    'unit' => 'servers'
                ];
                break;
        }

        return $costPerMap;
    }

    /**
     * @param FeatureCost $fc
     * @param $amounts
     * @param $type
     * @return array
     */
    public function getCostPerFeatureMap(FeatureCost $fc, $amounts, $type)
    {
        switch($fc->feature->{$type}) {
            case Feature::COST_PER_NUP:
                $costPerMap = [
                    'multiplier' => $this->softwareMap()->getData('cores') * $fc->feature->nup,
                    'amountsValue' => $amounts['cores'] * $fc->feature->nup,
                    'unit' => 'NUPs'
                ];
                break;
            case Feature::COST_PER_CORE:
                $costPerMap = [
                    'multiplier' => ceil(!empty($amounts['licensed_cores']) ? $amounts['licensed_cores'] : $amounts['cores']),
                    'amountsValue' => ceil(!empty($amounts['licensed_cores']) ? $amounts['licensed_cores'] : $amounts['cores']),
                    'unit' => $this->softwareMap()->getData('core_unit')
                ];
                break;
            case Feature::COST_PER_PROCESSOR:
                $costPerMap = [
                    'multiplier' => $this->softwareMap()->getData('processors'),
                    'amountsValue' => $amounts['processors'],
                    'unit' => 'processors'
                ];
                break;
            default:
                $costPerMap = [
                    'multiplier' => $this->softwareMap()->getData('servers'),
                    'amountsValue' => $amounts['servers'],
                    'unit' => 'servers'
                ];
                break;
        }

        return $costPerMap;
    }
}
